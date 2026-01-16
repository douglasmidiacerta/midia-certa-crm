<?php
// Página de Gestão de Produção - 4 Setores
// 1. Pedidos Pendentes (pedidos feitos pelo site)
// 2. OS aguardando finalização (sem arte ou abandonada)
// 3. OS aguardando cliente (esperando confirmação)
// 4. OS Refugada (devolvida pelo financeiro ou recusada pelo cliente)

$page_title = 'Gestão de Produção';
$base = $config['base_path'] ?? '';

// 1. Pedidos Pendentes (pedidos do site aguardando processamento)
$sql_pedidos_pendentes = "
  SELECT o.*, 
         c.name as client_name,
         c.whatsapp as client_whatsapp,
         c.email as client_email,
         u.name as seller_name,
         DATEDIFF(NOW(), o.created_at) as days_waiting,
         (SELECT SUM(qty * unit_price) FROM os_lines WHERE os_id = o.id) as total_calculado
  FROM os o
  LEFT JOIN clients c ON c.id = o.client_id
  LEFT JOIN users u ON u.id = o.seller_user_id
  WHERE o.status = 'pedido_pendente'
    AND o.origem = 'site'
  ORDER BY o.created_at ASC
";

// 2. OS aguardando finalização (sem arte ou abandonada)
// - OS em atendimento sem arquivos ou com status que indica abandono
$sql_aguardando_finalizacao = "
  SELECT DISTINCT o.*, 
         c.name as client_name, 
         u.name as seller_name,
         (SELECT COUNT(*) FROM os_files WHERE os_id = o.id) as files_count,
         DATEDIFF(NOW(), o.created_at) as days_waiting
  FROM os o
  LEFT JOIN clients c ON c.id = o.client_id
  LEFT JOIN users u ON u.id = o.seller_user_id
  WHERE o.doc_kind = 'sale'
    AND o.status = 'atendimento'
    AND (
      (SELECT COUNT(*) FROM os_files WHERE os_id = o.id) = 0
      OR DATEDIFF(NOW(), o.created_at) > 3
    )
  ORDER BY o.created_at ASC
";

// 2. OS aguardando cliente (esperando confirmação de arte)
$sql_aguardando_cliente = "
  SELECT o.*, 
         c.name as client_name, 
         u.name as seller_name,
         t.token as approval_token,
         t.created_at as approval_sent_at,
         DATEDIFF(NOW(), t.created_at) as days_waiting
  FROM os o
  JOIN os_approval_tokens t ON t.os_id = o.id
  LEFT JOIN clients c ON c.id = o.client_id
  LEFT JOIN users u ON u.id = o.seller_user_id
  WHERE t.used_at IS NULL 
    AND t.expires_at > NOW()
    AND t.rejected = 0
  ORDER BY t.created_at ASC
";

// 3. OS Refugada (devolvida ou recusada)
$sql_refugada = "
  SELECT o.*, 
         c.name as client_name, 
         u.name as seller_name,
         t.rejection_reason,
         t.used_at as rejected_at,
         DATEDIFF(NOW(), t.used_at) as days_since_rejection
  FROM os o
  LEFT JOIN os_approval_tokens t ON t.os_id = o.id AND t.rejected = 1
  LEFT JOIN clients c ON c.id = o.client_id
  LEFT JOIN users u ON u.id = o.seller_user_id
  WHERE o.doc_kind = 'sale'
    AND (
      o.status = 'refugada'
      OR t.rejected = 1
    )
  ORDER BY COALESCE(t.used_at, o.created_at) DESC
";

$os_pendentes = $pdo->query($sql_pedidos_pendentes)->fetchAll();
$os_finalizacao = $pdo->query($sql_aguardando_finalizacao)->fetchAll();
$os_cliente = $pdo->query($sql_aguardando_cliente)->fetchAll();
$os_refugada = $pdo->query($sql_refugada)->fetchAll();

?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>🏭 Gestão de Produção</h2>
    <div class="d-flex gap-2">
      <span class="badge bg-primary fs-6"><?= count($os_pendentes) ?> Pedidos Pendentes</span>
      <span class="badge bg-danger fs-6"><?= count($os_finalizacao) ?> Aguard. Finalização</span>
      <span class="badge bg-warning text-dark fs-6"><?= count($os_cliente) ?> Aguard. Cliente</span>
      <span class="badge bg-secondary fs-6"><?= count($os_refugada) ?> Refugadas</span>
    </div>
  </div>

  <div class="row g-3">
    
    <!-- SETOR 1: Pedidos Pendentes (Site) -->
    <div class="col-xl-3 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">
            <i class="bi bi-cart-check-fill"></i> 
            Pedidos Pendentes (<?= count($os_pendentes) ?>)
          </h5>
          <small>Pedidos do site aguardando processamento</small>
        </div>
        <div class="card-body p-0" style="max-height: 70vh; overflow-y: auto;">
          <?php if (empty($os_pendentes)): ?>
            <div class="p-4 text-center text-muted">
              <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
              <p class="mt-2">Nenhum pedido pendente! 🎉</p>
            </div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach($os_pendentes as $os): ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="flex-grow-1">
                      <h6 class="mb-1">
                        <a href="?page=os_edit&id=<?= $os['id'] ?>">OS #<?= h($os['code']) ?></a>
                        <span class="badge bg-info text-dark ms-1">Site</span>
                      </h6>
                      <p class="mb-1 small">
                        <i class="bi bi-person-fill"></i> <?= h($os['client_name']) ?>
                      </p>
                      <p class="mb-1 small text-muted">
                        <i class="bi bi-calendar"></i> <?= date('d/m/Y H:i', strtotime($os['created_at'])) ?>
                      </p>
                      <?php if ($os['days_waiting'] > 0): ?>
                        <span class="badge bg-warning text-dark">
                          <?= $os['days_waiting'] ?> dia<?= $os['days_waiting'] > 1 ? 's' : '' ?> aguardando
                        </span>
                      <?php else: ?>
                        <span class="badge bg-success">NOVO!</span>
                      <?php endif; ?>
                    </div>
                    <div class="text-end">
                      <small class="text-muted d-block">
                        R$ <?= number_format($os['total_calculado'] ?? 0, 2, ',', '.') ?>
                      </small>
                      <div class="btn-group-vertical btn-group-sm mt-2" role="group">
                        <a href="?page=os_edit&id=<?= $os['id'] ?>" 
                           class="btn btn-outline-primary btn-sm" title="Processar pedido">
                          <i class="bi bi-arrow-right-circle"></i> Processar
                        </a>
                        <?php if ($os['client_whatsapp']): ?>
                          <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $os['client_whatsapp']) ?>" 
                             target="_blank" class="btn btn-outline-success btn-sm" title="Contatar via WhatsApp">
                            <i class="bi bi-whatsapp"></i>
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  
                  <?php if ($os['notes']): ?>
                    <div class="alert alert-info alert-sm mb-0 mt-2" style="font-size: 0.85rem;">
                      <strong>Observações:</strong><br>
                      <?= nl2br(h($os['notes'])) ?>
                    </div>
                  <?php endif; ?>
                  
                  <?php if ($os['prazo_desejado']): ?>
                    <div class="mt-2">
                      <small class="text-muted">
                        <i class="bi bi-clock"></i> Prazo desejado: 
                        <strong><?= date('d/m/Y', strtotime($os['prazo_desejado'])) ?></strong>
                      </small>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- SETOR 2: OS Aguardando Finalização -->
    <div class="col-xl-3 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-danger text-white">
          <h5 class="mb-0">
            <i class="bi bi-exclamation-triangle-fill"></i> 
            OS Aguardando Finalização (<?= count($os_finalizacao) ?>)
          </h5>
          <small>OS sem arte ou abandonadas</small>
        </div>
        <div class="card-body p-0" style="max-height: 70vh; overflow-y: auto;">
          <?php if (empty($os_finalizacao)): ?>
            <div class="p-4 text-center text-muted">
              <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
              <p class="mt-2">Nenhuma OS aguardando finalização! 🎉</p>
            </div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach($os_finalizacao as $os): ?>
                <a href="?page=os_edit&id=<?= $os['id'] ?>" class="list-group-item list-group-item-action">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h6 class="mb-1">OS #<?= h($os['code']) ?></h6>
                      <p class="mb-1 text-muted small">
                        <i class="bi bi-person"></i> <?= h($os['client_name']) ?>
                      </p>
                      <p class="mb-0 text-muted small">
                        <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($os['created_at'])) ?>
                        <span class="badge bg-warning text-dark ms-2">
                          <?= $os['days_waiting'] ?> dias esperando
                        </span>
                      </p>
                    </div>
                    <div class="text-end">
                      <?php if ($os['files_count'] == 0): ?>
                        <span class="badge bg-danger">Sem arte</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Abandonada</span>
                      <?php endif; ?>
                      <br>
                      <small class="text-muted">R$ <?= number_format($os['total'], 2, ',', '.') ?></small>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- SETOR 3: OS Aguardando Cliente -->
    <div class="col-xl-3 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-warning text-dark">
          <h5 class="mb-0">
            <i class="bi bi-clock-history"></i> 
            OS Aguardando Cliente (<?= count($os_cliente) ?>)
          </h5>
          <small>Esperando aprovação de arte</small>
        </div>
        <div class="card-body p-0" style="max-height: 70vh; overflow-y: auto;">
          <?php if (empty($os_cliente)): ?>
            <div class="p-4 text-center text-muted">
              <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
              <p class="mt-2">Nenhuma OS aguardando cliente! 🎉</p>
            </div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach($os_cliente as $os): ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h6 class="mb-1">
                        <a href="?page=os_view&id=<?= $os['id'] ?>">OS #<?= h($os['code']) ?></a>
                      </h6>
                      <p class="mb-1 text-muted small">
                        <i class="bi bi-person"></i> <?= h($os['client_name']) ?>
                      </p>
                      <p class="mb-1 text-muted small">
                        <i class="bi bi-envelope"></i> Enviado em: <?= date('d/m/Y H:i', strtotime($os['approval_sent_at'])) ?>
                      </p>
                      <p class="mb-0">
                        <span class="badge bg-info">
                          <?= $os['days_waiting'] ?> dias aguardando
                        </span>
                      </p>
                    </div>
                    <div class="text-end">
                      <a href="<?= h($base ?? '') ?>/public_approval.php?token=<?= h($os['approval_token']) ?>" 
                         class="btn btn-sm btn-outline-primary" target="_blank" title="Ver página de aprovação">
                        <i class="bi bi-box-arrow-up-right"></i>
                      </a>
                      <br>
                      <small class="text-muted mt-1 d-block">R$ <?= number_format($os['total'] ?? 0, 2, ',', '.') ?></small>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- SETOR 4: OS Refugada -->
    <div class="col-xl-3 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-secondary text-white">
          <h5 class="mb-0">
            <i class="bi bi-arrow-counterclockwise"></i> 
            OS Refugada (<?= count($os_refugada) ?>)
          </h5>
          <small>Devolvida ou recusada - precisa correção</small>
        </div>
        <div class="card-body p-0" style="max-height: 70vh; overflow-y: auto;">
          <?php if (empty($os_refugada)): ?>
            <div class="p-4 text-center text-muted">
              <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
              <p class="mt-2">Nenhuma OS refugada! 🎉</p>
            </div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach($os_refugada as $os): ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <h6 class="mb-1">
                        <a href="?page=os_edit&id=<?= $os['id'] ?>&mode=art_only">OS #<?= h($os['code']) ?></a>
                      </h6>
                      <p class="mb-1 text-muted small">
                        <i class="bi bi-person"></i> <?= h($os['client_name']) ?>
                      </p>
                      <?php if ($os['rejected_at']): ?>
                        <p class="mb-0 text-muted small">
                          <i class="bi bi-x-circle"></i> Rejeitada em: <?= date('d/m/Y H:i', strtotime($os['rejected_at'])) ?>
                          <span class="badge bg-danger ms-2">
                            há <?= $os['days_since_rejection'] ?> dias
                          </span>
                        </p>
                      <?php endif; ?>
                    </div>
                    <div class="text-end">
                      <a href="?page=os_edit&id=<?= $os['id'] ?>&mode=art_only" 
                         class="btn btn-sm btn-outline-secondary" title="Editar apenas arte">
                        <i class="bi bi-pencil"></i> Arte
                      </a>
                      <br>
                      <small class="text-muted mt-1 d-block">R$ <?= number_format($os['total'], 2, ',', '.') ?></small>
                    </div>
                  </div>
                  
                  <?php if ($os['rejection_reason']): ?>
                    <div class="alert alert-danger alert-sm mb-0 mt-2" style="font-size: 0.85rem;">
                      <strong>Motivo da rejeição:</strong><br>
                      <?= nl2br(h($os['rejection_reason'])) ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
.list-group-item-action:hover {
  background-color: #f8f9fa;
  transform: translateX(5px);
  transition: all 0.2s ease;
}

.card-header {
  font-weight: 600;
}

.badge {
  font-weight: 500;
}

.alert-sm {
  padding: 0.5rem;
}
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
