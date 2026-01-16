<?php
// PRODUÇÃO - OS que foram enviadas ao fornecedor
require_login();
require_role(['admin', 'financeiro']);

// Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $os_id = (int)($_POST['os_id'] ?? 0);
    
    if (!$os_id) {
        flash_set('danger', 'OS inválida');
        redirect($base . '/app.php?page=producao_producao');
    }
    
    if ($action === 'recebido') {
        // Move para expedição (disponivel)
        $pdo->prepare("UPDATE os SET status = 'disponivel' WHERE id = ?")->execute([$os_id]);
        audit($pdo, 'status_change', 'os', $os_id, ['from' => 'producao', 'to' => 'disponivel']);
        flash_set('success', 'Material recebido! OS movida para Expedição.');
        redirect($base . '/app.php?page=producao_producao');
    }
    
    if ($action === 'adicionar_nota') {
        $nota = trim($_POST['nota'] ?? '');
        if ($nota) {
            $pdo->prepare("UPDATE os SET notes = CONCAT(COALESCE(notes, ''), '\n[PRODUÇÃO]: ', ?) WHERE id = ?")->execute([$nota, $os_id]);
            flash_set('success', 'Nota adicionada!');
        }
        redirect($base . '/app.php?page=producao_producao');
    }
}

// Buscar OS em produção
$q = trim($_GET['q'] ?? '');
$sql = "SELECT 
    o.id,
    o.code,
    o.created_at,
    o.due_date,
    o.notes,
    c.name AS client_name,
    c.whatsapp,
    u.name AS seller_name,
    (SELECT SUM(qty * unit_price) FROM os_lines WHERE os_id = o.id) AS total,
    (SELECT COUNT(*) FROM os_lines WHERE os_id = o.id) AS items_count,
    DATEDIFF(o.due_date, CURDATE()) AS dias_prazo
FROM os o
JOIN clients c ON c.id = o.client_id
JOIN users u ON u.id = o.seller_user_id
WHERE o.status = 'producao'";

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
.producao-card {
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
    transition: all 0.3s;
}
.producao-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.producao-card.urgente {
    border-left-color: #dc2626;
    background: #fef2f2;
}
.producao-card.atencao {
    border-left-color: #f59e0b;
    background: #fffbeb;
}
</style>

<div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 style="font-weight: 900;">🏭 Produção / Fornecedor</h4>
            <p class="text-muted small mb-0">OS enviadas ao fornecedor aguardando retorno</p>
        </div>
        <div class="badge bg-primary" style="font-size: 1.5rem; padding: 0.75rem 1.5rem;">
            <?= count($rows) ?> OS
        </div>
    </div>
    
    <form class="row g-2" method="get">
        <input type="hidden" name="page" value="producao_producao">
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
    <h6 style="font-weight: 900;">✨ Nenhuma OS em produção!</h6>
    <p class="mb-0">Não há OS aguardando retorno do fornecedor no momento.</p>
</div>
<?php else: ?>

<div class="row g-3">
    <?php foreach ($rows as $os): 
        $urgente = $os['dias_prazo'] !== null && $os['dias_prazo'] <= 1;
        $atencao = $os['dias_prazo'] !== null && $os['dias_prazo'] > 1 && $os['dias_prazo'] <= 3;
        $cardClass = $urgente ? 'urgente' : ($atencao ? 'atencao' : '');
    ?>
    <div class="col-md-6">
        <div class="card producao-card <?= $cardClass ?> p-3">
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
                    <div class="small" style="font-weight: 700; color: <?= $urgente ? '#dc2626' : ($atencao ? '#f59e0b' : '#059669') ?>">
                        🎯 Prazo: <?= date('d/m/Y', strtotime($os['due_date'])) ?>
                        <?php if ($os['dias_prazo'] !== null): ?>
                            (<?= $os['dias_prazo'] >= 0 ? 'faltam ' . $os['dias_prazo'] . ' dias' : 'ATRASADO ' . abs($os['dias_prazo']) . ' dias' ?>)
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <div style="font-size: 1.3rem; font-weight: 900; color: #059669;">
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
            
            <?php if ($os['notes']): ?>
            <div class="alert alert-secondary small mb-2" style="max-height: 80px; overflow-y: auto;">
                <strong>📝 Observações:</strong><br>
                <?= nl2br(h($os['notes'])) ?>
            </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2 mt-3">
                <a href="?page=os_view&id=<?= h($os['id']) ?>" class="btn btn-sm btn-outline-primary">
                    👁️ Ver
                </a>
                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#nota<?= h($os['id']) ?>">
                    📝 Nota
                </button>
                <button type="button" class="btn btn-sm btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#recebido<?= h($os['id']) ?>">
                    ✅ Material Recebido
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Material Recebido -->
    <div class="modal fade" id="recebido<?= h($os['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">✅ Material Recebido - OS #<?= h($os['code']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Confirma que o material foi recebido do fornecedor e está pronto para expedição?</p>
                        <div class="alert alert-success small mb-0">
                            <strong>Próximo passo:</strong> A OS será movida para <strong>Expedição</strong> para preparar a entrega ao cliente.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="recebido">
                        <input type="hidden" name="os_id" value="<?= h($os['id']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">✅ Confirmar Recebimento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Adicionar Nota -->
    <div class="modal fade" id="nota<?= h($os['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📝 Adicionar Nota - OS #<?= h($os['code']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <label class="form-label">Observação sobre a produção:</label>
                        <textarea class="form-control" name="nota" rows="3" placeholder="Ex: Fornecedor enviou amostra, ajuste de cor necessário, etc."></textarea>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="adicionar_nota">
                        <input type="hidden" name="os_id" value="<?= h($os['id']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">💾 Salvar Nota</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<div class="row g-3 mt-3">
    <div class="col-md-4">
        <div class="card p-3" style="background: #fef2f2; border-left: 4px solid #dc2626;">
            <h6 style="font-weight: 900; color: #dc2626;">🚨 Urgente</h6>
            <div style="font-size: 2rem; font-weight: 900; color: #dc2626;">
                <?= count(array_filter($rows, fn($r) => $r['dias_prazo'] !== null && $r['dias_prazo'] <= 1)) ?>
            </div>
            <small class="text-muted">Prazo ≤ 1 dia</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3" style="background: #fffbeb; border-left: 4px solid #f59e0b;">
            <h6 style="font-weight: 900; color: #f59e0b;">⚠️ Atenção</h6>
            <div style="font-size: 2rem; font-weight: 900; color: #f59e0b;">
                <?= count(array_filter($rows, fn($r) => $r['dias_prazo'] !== null && $r['dias_prazo'] > 1 && $r['dias_prazo'] <= 3)) ?>
            </div>
            <small class="text-muted">Prazo entre 2-3 dias</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3" style="background: #f0fdf4; border-left: 4px solid #059669;">
            <h6 style="font-weight: 900; color: #059669;">✅ No Prazo</h6>
            <div style="font-size: 2rem; font-weight: 900; color: #059669;">
                <?= count(array_filter($rows, fn($r) => $r['dias_prazo'] === null || $r['dias_prazo'] > 3)) ?>
            </div>
            <small class="text-muted">Prazo confortável</small>
        </div>
    </div>
</div>

<div class="card p-3 mt-4" style="background: #f0f9ff; border-left: 4px solid #3b82f6;">
    <h6 style="font-weight: 900;">💡 Como funciona a Produção?</h6>
    <ul class="mb-0 small">
        <li><strong>Financeiro aprova</strong> na conferência e envia para produção</li>
        <li><strong>Fornecedor produz</strong> o material solicitado</li>
        <li><strong>Ao receber</strong> o material de volta, marque como "Material Recebido"</li>
        <li><strong>A OS vai para Expedição</strong> para preparar a entrega ao cliente</li>
    </ul>
</div>
