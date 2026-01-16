<?php
// CONFERÊNCIA - OS aprovadas pelo cliente aguardando conferência do financeiro
require_login();
require_role(['admin', 'financeiro']);

function fetch_os_suppliers($pdo, $os_id) {
    $st = $pdo->prepare("
        SELECT DISTINCT s.id, s.name
        FROM os_lines l
        JOIN item_supplier_costs isc ON isc.item_id = l.item_id
        JOIN suppliers s ON s.id = isc.supplier_id
        WHERE l.os_id = ?
        ORDER BY s.name
    ");
    $st->execute([$os_id]);
    return $st->fetchAll();
}

function next_purchase_code($pdo) {
    $st = $pdo->query("SELECT code FROM purchases ORDER BY id DESC LIMIT 200");
    $max = 0;
    foreach ($st->fetchAll() as $row) {
        $digits = preg_replace('/\D+/', '', (string)($row['code'] ?? ''));
        if ($digits !== '') {
            $num = (int)$digits;
            if ($num > $max) $max = $num;
        }
    }
    if ($max < 1700) $max = 1699;
    $next = $max + 1;
    return 'OC#' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

// Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $os_id = (int)($_POST['os_id'] ?? 0);
    
    if (!$os_id) {
        flash_set('danger', 'OS inválida');
        redirect($base . '/app.php?page=producao_conferencia');
    }
    
    if ($action === 'aprovar') {
        try {
            $pdo->beginTransaction();

            $selected_supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $available_suppliers = fetch_os_suppliers($pdo, $os_id);
            $available_ids = array_map(fn($s) => (int)$s['id'], $available_suppliers);

            if ($selected_supplier_id > 0 && !in_array($selected_supplier_id, $available_ids, true)) {
                $pdo->rollBack();
                flash_set('danger', 'Fornecedor inválido para os itens desta OS.');
                redirect($base . '/app.php?page=producao_conferencia');
            }

            if (count($available_suppliers) > 1 && $selected_supplier_id <= 0) {
                $pdo->rollBack();
                flash_set('danger', 'Selecione o fornecedor para gerar a O.C.');
                redirect($base . '/app.php?page=producao_conferencia');
            }

            $supplier_id = $selected_supplier_id;
            if (!$supplier_id && count($available_suppliers) === 1) {
                $supplier_id = (int)$available_suppliers[0]['id'];
            }
            if ($supplier_id <= 0) $supplier_id = null;

            // Move para o próximo status: producao
            $pdo->prepare("UPDATE os SET status = 'producao' WHERE id = ?")->execute([$os_id]);
            audit($pdo, 'status_change', 'os', $os_id, ['from' => 'conferencia', 'to' => 'producao']);

            // Gera automaticamente Ordem de Compra (Purchase) com os mesmos produtos da OS
            $os_st = $pdo->prepare("SELECT * FROM os WHERE id = ?");
            $os_st->execute([$os_id]);
            $os_data = $os_st->fetch();

            $lines_st = $pdo->prepare("
                SELECT l.*, i.name as item_name, i.type as item_type
                FROM os_lines l
                JOIN items i ON i.id = l.item_id
                WHERE l.os_id = ?
            ");
            $lines_st->execute([$os_id]);
            $os_lines = $lines_st->fetchAll();

            if (!empty($os_lines)) {
                $exists_st = $pdo->prepare("SELECT id, code FROM purchases WHERE os_id = ? LIMIT 1");
                $exists_st->execute([$os_id]);
                $existing_purchase = $exists_st->fetch();
                if ($existing_purchase) {
                    flash_set('warning', "OS aprovada! Já existe uma O.C vinculada (#{$existing_purchase['code']}).");
                    $pdo->commit();
                    redirect($base . '/app.php?page=producao_conferencia');
                }

                // Gera código único para compra
                $purchase_code = next_purchase_code($pdo);

                $total_purchase = 0;

                // Cria Purchase vinculada à OS
                $pdo->prepare("
                    INSERT INTO purchases (code, os_id, supplier_id, total, status, notes, created_by_user_id, created_at)
                    VALUES (?, ?, ?, 0, 'aberta', ?, ?, NOW())
                ")->execute([
                    $purchase_code, 
                    $os_id,
                    $supplier_id,
                    "Compra gerada automaticamente a partir da OS #" . $os_data['code'],
                    user_id()
                ]);
                
                $purchase_id = $pdo->lastInsertId();
                
                // Adiciona itens na compra
                foreach ($os_lines as $line) {
                    $cost_info = null;
                    if ($supplier_id) {
                        $stCost = $pdo->prepare("
                            SELECT cost 
                            FROM item_supplier_costs 
                            WHERE item_id = ? AND supplier_id = ?
                            ORDER BY cost ASC LIMIT 1
                        ");
                        $stCost->execute([$line['item_id'], $supplier_id]);
                        $cost_info = $stCost->fetch();
                        if (!$cost_info) {
                            $cost_info = ['cost' => $line['unit_price'] * 0.6];
                        }
                    } else {
                        $stBest = $pdo->prepare("
                            SELECT cost 
                            FROM item_supplier_costs 
                            WHERE item_id = ?
                            ORDER BY cost ASC LIMIT 1
                        ");
                        $stBest->execute([$line['item_id']]);
                        $cost_info = $stBest->fetch();
                        if (!$cost_info) {
                            $cost_info = ['cost' => $line['unit_price'] * 0.6];
                        }
                    }

                    $line_total = $line['qty'] * $cost_info['cost'];
                    $total_purchase += $line_total;

                    $pdo->prepare("
                        INSERT INTO purchase_lines (purchase_id, description, category, qty, unit_price, item_id, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ")->execute([
                        $purchase_id,
                        $line['item_name'] . ' (' . $line['item_type'] . ')',
                        'Produção',
                        $line['qty'],
                        $cost_info['cost'],
                        $line['item_id']
                    ]);
                }
                
                // Atualiza total da compra
                $pdo->prepare("UPDATE purchases SET total = ? WHERE id = ?")->execute([$total_purchase, $purchase_id]);
                
                // Cria conta a pagar automaticamente
                if ($total_purchase > 0) {
                    $pdo->prepare("
                        INSERT INTO ap_titles (purchase_id, supplier_id, description, amount, due_date, status, created_at)
                        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 'aberto', NOW())
                    ")->execute([
                        $purchase_id,
                        $supplier_id,
                        "Pagamento da compra {$purchase_code} - OS #{$os_data['code']}",
                        $total_purchase
                    ]);
                }
                
                audit($pdo, 'create', 'purchases', $purchase_id, ['auto_generated' => true, 'from_os' => $os_id]);

                if ($supplier_id) {
                    $supplier_st = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
                    $supplier_st->execute([$supplier_id]);
                    $supplier = $supplier_st->fetch();
                    flash_set('success', "OS aprovada! Compra #{$purchase_code} gerada para fornecedor: {$supplier['name']}");
                } else {
                    flash_set('warning', "OS aprovada! Compra #{$purchase_code} gerada. ATENÇÃO: Selecione o fornecedor em Compras.");
                }
            } else {
                flash_set('success', 'OS aprovada e enviada para produção!');
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('danger', 'Erro ao aprovar OS: ' . $e->getMessage());
        }
        
        redirect($base . '/app.php?page=producao_conferencia');
    }
    
    if ($action === 'rejeitar') {
        $motivo = trim($_POST['motivo'] ?? '');
        // Volta para atendimento para correção
        $pdo->prepare("UPDATE os SET status = 'atendimento', notes = CONCAT(COALESCE(notes, ''), '\n[REJEITADO NA CONFERÊNCIA]: ', ?) WHERE id = ?")->execute([$motivo, $os_id]);
        audit($pdo, 'status_change', 'os', $os_id, ['from' => 'conferencia', 'to' => 'atendimento', 'reason' => $motivo]);
        flash_set('warning', 'OS rejeitada e retornada para atendimento.');
        redirect($base . '/app.php?page=producao_conferencia');
    }
}

// Buscar OS em conferência
$q = trim($_GET['q'] ?? '');
$sql = "SELECT 
    o.id,
    o.code,
    o.created_at,
    o.approved_at,
    c.name AS client_name,
    c.whatsapp,
    u.name AS seller_name,
    (SELECT SUM(qty * unit_price) FROM os_lines WHERE os_id = o.id) AS total,
    (SELECT COUNT(*) FROM os_lines WHERE os_id = o.id) AS items_count
FROM os o
JOIN clients c ON c.id = o.client_id
JOIN users u ON u.id = o.seller_user_id
WHERE o.status = 'conferencia'";

$params = [];
if ($q) {
    $sql .= " AND (o.code LIKE ? OR c.name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$sql .= " ORDER BY o.created_at ASC LIMIT 100";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
$os_suppliers = [];
foreach ($rows as $row) {
    $os_suppliers[$row['id']] = fetch_os_suppliers($pdo, (int)$row['id']);
}
?>

<style>
.os-card {
    border-radius: 8px;
    border-left: 4px solid #f59e0b;
    transition: all 0.3s;
}
.os-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
</style>

<div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 style="font-weight: 900;">✅ Conferência de OS</h4>
            <p class="text-muted small mb-0">OS aprovadas pelo cliente aguardando conferência do financeiro</p>
        </div>
        <div class="badge bg-warning text-dark" style="font-size: 1.5rem; padding: 0.75rem 1.5rem;">
            <?= count($rows) ?> OS
        </div>
    </div>
    
    <form class="row g-2" method="get">
        <input type="hidden" name="page" value="producao_conferencia">
        <div class="col-md-9">
            <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="🔍 Buscar por código ou cliente">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100" type="submit">Buscar</button>
        </div>
    </form>
</div>

<?php if (empty($rows)): ?>
<div class="alert alert-info">
    <h6 style="font-weight: 900;">✨ Nenhuma OS aguardando conferência!</h6>
    <p class="mb-0">Todas as OS foram conferidas ou não há OS no status de conferência.</p>
</div>
<?php else: ?>

<div class="row g-3">
    <?php foreach ($rows as $os): ?>
    <div class="col-md-6">
        <div class="card os-card p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h5 style="font-weight: 900; margin-bottom: 0.25rem;">
                        <a href="?page=os_view&id=<?= h($os['id']) ?>" class="text-decoration-none">
                            #<?= h($os['code']) ?>
                        </a>
                    </h5>
                    <div class="text-muted small">
                        📅 Criado: <?= date('d/m/Y H:i', strtotime($os['created_at'])) ?>
                    </div>
                    <?php if ($os['approved_at']): ?>
                    <div class="text-success small">
                        ✅ Aprovado: <?= date('d/m/Y H:i', strtotime($os['approved_at'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <div style="font-size: 1.5rem; font-weight: 900; color: #059669;">
                        <?= h(money($os['total'])) ?>
                    </div>
                    <small class="text-muted"><?= h($os['items_count']) ?> itens</small>
                </div>
            </div>
            
            <hr class="my-2">
            
            <div class="mb-2">
                <div><strong>Cliente:</strong> <?= h($os['client_name']) ?></div>
                <div class="small text-muted">📱 <?= h($os['whatsapp']) ?></div>
                <div class="small"><strong>Vendedor:</strong> <?= h($os['seller_name']) ?></div>
            </div>
            
            <div class="d-flex gap-2 mt-3">
                <a href="?page=os_view&id=<?= h($os['id']) ?>" class="btn btn-sm btn-outline-primary flex-fill">
                    👁️ Visualizar
                </a>
                <button type="button" class="btn btn-sm btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#aprovar<?= h($os['id']) ?>">
                    ✅ Aprovar
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejeitar<?= h($os['id']) ?>">
                    ❌
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Aprovar -->
    <div class="modal fade" id="aprovar<?= h($os['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">✅ Aprovar OS #<?= h($os['code']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Confirma que conferiu todos os dados e deseja enviar para produção?</p>
                        <div class="alert alert-info small mb-0">
                            <strong>Próximo passo:</strong> A OS será enviada para o status de <strong>Produção</strong> para ser encaminhada ao fornecedor.
                        </div>
                        <?php $suppliers_for_os = $os_suppliers[$os['id']] ?? []; ?>
                        <?php if (count($suppliers_for_os) > 1): ?>
                        <div class="mt-3">
                            <label class="form-label"><strong>Fornecedor *</strong></label>
                            <select class="form-select" name="supplier_id" required>
                                <option value="">Selecione o fornecedor</option>
                                <?php foreach ($suppliers_for_os as $sup): ?>
                                    <option value="<?= h($sup['id']) ?>"><?= h($sup['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Este fornecedor será usado para gerar a O.C.</small>
                        </div>
                        <?php elseif (count($suppliers_for_os) === 1): ?>
                        <div class="mt-3">
                            <strong>Fornecedor:</strong> <?= h($suppliers_for_os[0]['name']) ?>
                            <input type="hidden" name="supplier_id" value="<?= h($suppliers_for_os[0]['id']) ?>">
                        </div>
                        <?php else: ?>
                        <div class="mt-3 text-muted small">
                            Nenhum fornecedor cadastrado para os itens desta OS.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="aprovar">
                        <input type="hidden" name="os_id" value="<?= h($os['id']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">✅ Confirmar Aprovação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Rejeitar -->
    <div class="modal fade" id="rejeitar<?= h($os['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">❌ Rejeitar OS #<?= h($os['code']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Por que está rejeitando esta OS?</p>
                        <textarea class="form-control" name="motivo" rows="3" required placeholder="Ex: Valores incorretos, dados do cliente incompletos, etc."></textarea>
                        <div class="alert alert-warning small mt-3 mb-0">
                            <strong>⚠️ Atenção:</strong> A OS voltará para o status de <strong>Atendimento</strong> para correção pelo vendedor.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="rejeitar">
                        <input type="hidden" name="os_id" value="<?= h($os['id']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">❌ Confirmar Rejeição</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<div class="card p-3 mt-4" style="background: #f0fdf4; border-left: 4px solid #059669;">
    <h6 style="font-weight: 900;">💡 Como funciona a Conferência?</h6>
    <ul class="mb-0 small">
        <li><strong>O vendedor</strong> cria a OS e o cliente aprova</li>
        <li><strong>O financeiro</strong> confere valores, dados do cliente e itens</li>
        <li><strong>Se OK:</strong> Aprova e envia para Produção (fornecedor)</li>
        <li><strong>Se há erro:</strong> Rejeita e volta para o vendedor corrigir</li>
    </ul>
</div>
