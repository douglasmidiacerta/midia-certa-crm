<?php
// EXPEDIÇÃO - OS prontas para entrega
require_login();
require_role(['admin', 'financeiro']);

// Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $os_id = (int)($_POST['os_id'] ?? 0);
    
    if (!$os_id) {
        flash_set('danger', 'OS inválida');
        redirect($base . '/app.php?page=producao_expedicao');
    }
    
    if ($action === 'confirmar_retirada') {
        // Cliente retirou no balcão - finaliza
        $pdo->prepare("UPDATE os SET status = 'finalizada' WHERE id = ?")->execute([$os_id]);
        audit($pdo, 'delivery_confirmed', 'os', $os_id, ['method' => 'balcao']);
        flash_set('success', 'Retirada confirmada! OS finalizada.');
        redirect($base . '/app.php?page=producao_expedicao');
    }
    
    if ($action === 'confirmar_entrega_motoboy') {
        $motoboy = trim($_POST['motoboy'] ?? '');
        // Entregue via motoboy - finaliza
        $pdo->prepare("UPDATE os SET status = 'finalizada', delivery_motoboy = ? WHERE id = ?")->execute([$motoboy, $os_id]);
        audit($pdo, 'delivery_confirmed', 'os', $os_id, ['method' => 'motoboy', 'motoboy' => $motoboy]);
        flash_set('success', 'Entrega via motoboy confirmada! OS finalizada.');
        redirect($base . '/app.php?page=producao_expedicao');
    }
    
    if ($action === 'gerar_link_retirada') {
        // Gera token único para o cliente confirmar retirada
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE os SET delivery_notes = ? WHERE id = ?")->execute(["TOKEN_RETIRADA:$token", $os_id]);
        flash_set('success', 'Link de confirmação gerado! Envie ao cliente.');
        redirect($base . '/app.php?page=producao_expedicao');
    }
}

// Buscar OS disponíveis para expedição
$q = trim($_GET['q'] ?? '');
$sql = "SELECT 
    o.id,
    o.code,
    o.created_at,
    o.due_date,
    o.delivery_method,
    o.delivery_notes,
    c.name AS client_name,
    c.whatsapp,
    c.address_street,
    c.address_number,
    c.address_neighborhood,
    c.address_city,
    u.name AS seller_name,
    (SELECT SUM(qty * unit_price) FROM os_lines WHERE os_id = o.id) AS total
FROM os o
JOIN clients c ON c.id = o.client_id
JOIN users u ON u.id = o.seller_user_id
WHERE o.status = 'disponivel'";

$params = [];
if ($q) {
    $sql .= " AND (o.code LIKE ? OR c.name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$sql .= " ORDER BY o.due_date ASC, o.created_at ASC LIMIT 100";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>

<style>
.expedicao-card {
    border-radius: 8px;
    border-left: 4px solid #059669;
    transition: all 0.3s;
}
.expedicao-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.badge-retirada {
    background: #059669;
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}
.badge-motoboy {
    background: #3b82f6;
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}
.badge-correios {
    background: #f59e0b;
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}
</style>

<div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 style="font-weight: 900;">📦 Expedição - Pronto para Entrega</h4>
            <p class="text-muted small mb-0">OS prontas aguardando retirada/entrega ao cliente</p>
        </div>
        <div class="badge bg-success" style="font-size: 1.5rem; padding: 0.75rem 1.5rem;">
            <?= count($rows) ?> OS
        </div>
    </div>
    
    <form class="row g-2" method="get">
        <input type="hidden" name="page" value="producao_expedicao">
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
    <h6 style="font-weight: 900;">✨ Nenhuma OS aguardando expedição!</h6>
    <p class="mb-0">Todas as OS foram entregues ou não há OS prontas no momento.</p>
</div>
<?php else: ?>

<div class="row g-3">
    <?php foreach ($rows as $os): 
        $hasToken = strpos($os['delivery_notes'] ?? '', 'TOKEN_RETIRADA:') !== false;
        $token = $hasToken ? substr($os['delivery_notes'], strpos($os['delivery_notes'], 'TOKEN_RETIRADA:') + 16, 32) : null;
    ?>
    <div class="col-md-6">
        <div class="card expedicao-card p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h5 style="font-weight: 900; margin-bottom: 0.25rem;">
                        <a href="?page=os_view&id=<?= h($os['id']) ?>" class="text-decoration-none">
                            #<?= h($os['code']) ?>
                        </a>
                    </h5>
                    <div class="text-muted small">
                        📅 Criado: <?= date('d/m/Y', strtotime($os['created_at'])) ?>
                    </div>
                    <?php if ($os['due_date']): ?>
                    <div class="small">
                        🎯 Prazo: <?= date('d/m/Y', strtotime($os['due_date'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($os['delivery_method'] === 'retirada'): ?>
                        <span class="badge badge-retirada">🏪 Retirada</span>
                    <?php elseif ($os['delivery_method'] === 'motoboy'): ?>
                        <span class="badge badge-motoboy">🏍️ Motoboy</span>
                    <?php else: ?>
                        <span class="badge badge-correios">📮 Correios</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <hr class="my-2">
            
            <div class="mb-2">
                <div><strong>Cliente:</strong> <?= h($os['client_name']) ?></div>
                <div class="small text-muted">📱 <?= h($os['whatsapp']) ?></div>
                <?php if ($os['delivery_method'] !== 'retirada' && $os['address_street']): ?>
                <div class="small">
                    📍 <?= h($os['address_street']) ?>, <?= h($os['address_number']) ?> - <?= h($os['address_neighborhood']) ?>, <?= h($os['address_city']) ?>
                </div>
                <?php endif; ?>
                <div class="small"><strong>Vendedor:</strong> <?= h($os['seller_name']) ?></div>
                <div style="font-size: 1.2rem; font-weight: 900; color: #059669; margin-top: 0.5rem;">
                    <?= h(money($os['total'])) ?>
                </div>
            </div>
            
            <?php if ($os['delivery_method'] === 'retirada'): ?>
                <!-- Retirada no Balcão -->
                <div class="d-flex gap-2 mt-3">
                    <?php if (!$hasToken): ?>
                        <button type="button" class="btn btn-sm btn-info flex-fill" data-bs-toggle="modal" data-bs-target="#gerarLink<?= h($os['id']) ?>">
                            🔗 Gerar Link
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-info flex-fill" onclick="copyLink<?= h($os['id']) ?>()">
                            📋 Copiar Link
                        </button>
                        <script>
                        function copyLink<?= h($os['id']) ?>() {
                            const link = '<?= h($base) ?>/public_tracking.php?token=<?= h($token) ?>';
                            navigator.clipboard.writeText(link).then(() => {
                                alert('Link copiado! Envie ao cliente via WhatsApp.');
                            });
                        }
                        </script>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#confirmarRetirada<?= h($os['id']) ?>">
                        ✅ Confirmar Retirada
                    </button>
                </div>
            <?php else: ?>
                <!-- Entrega via Motoboy/Correios -->
                <div class="d-flex gap-2 mt-3">
                    <a href="?page=os_view&id=<?= h($os['id']) ?>" class="btn btn-sm btn-outline-primary">
                        👁️ Ver
                    </a>
                    <button type="button" class="btn btn-sm btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#confirmarEntrega<?= h($os['id']) ?>">
                        ✅ Confirmar Entrega
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Gerar Link -->
    <div class="modal fade" id="gerarLink<?= h($os['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">🔗 Gerar Link de Confirmação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Deseja gerar um link público para o cliente confirmar a retirada?</p>
                        <div class="alert alert-info small mb-0">
                            <strong>Como funciona:</strong><br>
                            1. Geramos um link único<br>
                            2. Você envia ao cliente via WhatsApp<br>
                            3. Cliente clica e confirma que retirou<br>
                            4. OS é automaticamente finalizada
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="gerar_link_retirada">
                        <input type="hidden" name="os_id" value="<?= h($os['id']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info">🔗 Gerar Link</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Confirmar Retirada -->
    <div class="modal fade" id="confirmarRetirada<?= h($os['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">✅ Confirmar Retirada - OS #<?= h($os['code']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Cliente <strong><?= h($os['client_name']) ?></strong> retirou o material no balcão?</p>
                        <div class="alert alert-success small mb-0">
                            <strong>✅ A OS será finalizada</strong> após a confirmação.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="confirmar_retirada">
                        <input type="hidden" name="os_id" value="<?= h($os['id']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">✅ Sim, Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Confirmar Entrega -->
    <div class="modal fade" id="confirmarEntrega<?= h($os['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">✅ Confirmar Entrega - OS #<?= h($os['code']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Material foi entregue ao cliente <strong><?= h($os['client_name']) ?></strong>?</p>
                        <?php if ($os['delivery_method'] === 'motoboy'): ?>
                        <div class="mb-3">
                            <label class="form-label">Nome do Motoboy:</label>
                            <input type="text" class="form-control" name="motoboy" placeholder="Ex: João da Silva" required>
                        </div>
                        <?php endif; ?>
                        <div class="alert alert-success small mb-0">
                            <strong>✅ A OS será finalizada</strong> após a confirmação.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="confirmar_entrega_motoboy">
                        <input type="hidden" name="os_id" value="<?= h($os['id']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">✅ Confirmar Entrega</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<div class="card p-3 mt-4" style="background: #f0fdf4; border-left: 4px solid #059669;">
    <h6 style="font-weight: 900;">💡 Como funciona a Expedição?</h6>
    <ul class="mb-0 small">
        <li><strong>Retirada no Balcão:</strong> Gere um link para o cliente confirmar ou confirme manualmente</li>
        <li><strong>Entrega via Motoboy:</strong> Informe quem fez a entrega e confirme</li>
        <li><strong>Correios:</strong> Configure rastreio e confirme o envio</li>
        <li><strong>Ao confirmar:</strong> A OS é automaticamente finalizada</li>
    </ul>
</div>
