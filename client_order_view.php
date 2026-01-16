<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/client_auth.php';
require_once 'config/utils.php';

require_client_login();

$base = $config['base_path'] ?? '';
$branding = include 'config/branding.php';
$client = get_client_data($pdo);
$client_id = get_client_id();
$order_id = (int)($_GET['id'] ?? 0);

// Busca pedido (apenas do cliente logado)
$st = $pdo->prepare("
  SELECT o.*, u.name as seller_name 
  FROM os o 
  LEFT JOIN users u ON u.id = o.seller_user_id
  WHERE o.id = ? AND o.client_id = ?
");
$st->execute([$order_id, $client_id]);
$order = $st->fetch();

if (!$order) {
  header('Location: client_portal.php');
  exit;
}

// Busca itens do pedido
$st = $pdo->prepare("
  SELECT l.*, i.name as item_name, i.type as item_type
  FROM os_lines l
  JOIN items i ON i.id = l.item_id
  WHERE l.os_id = ?
");
$st->execute([$order_id]);
$items = $st->fetchAll();

// Busca arquivos anexados
$st = $pdo->prepare("SELECT * FROM os_files WHERE os_id = ? ORDER BY id DESC");
$st->execute([$order_id]);
$files = $st->fetchAll();

// Calcula totais
$subtotal = 0;
foreach($items as $item) {
  $subtotal += $item['qty'] * $item['unit_price'];
}
$delivery_fee = (float)$order['delivery_fee_charged'];
$total = $subtotal + $delivery_fee;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedido #<?= h($order['code']) ?> - Portal do Cliente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
  <style>
    body { background: #f5f7fa; }
    .navbar-portal {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .order-header {
      background: white;
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .info-card {
      background: white;
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin-bottom: 20px;
    }
    .status-timeline {
      position: relative;
      padding-left: 30px;
    }
    .status-timeline::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: #e0e0e0;
    }
    .timeline-item {
      position: relative;
      padding: 10px 0;
    }
    .timeline-item::before {
      content: '';
      position: absolute;
      left: -25px;
      top: 15px;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #e0e0e0;
      border: 3px solid white;
      box-shadow: 0 0 0 2px #e0e0e0;
    }
    .timeline-item.active::before {
      background: #667eea;
      box-shadow: 0 0 0 2px #667eea;
    }
    .timeline-item.completed::before {
      background: #28a745;
      box-shadow: 0 0 0 2px #28a745;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand navbar-dark shadow-sm" style="background: <?= h($branding['cores']['primaria'] ?? '#8DC63F') ?>;">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="client_portal.php">
      <?php 
      $logo_path = ltrim($branding['logo_path'], '/');
      $logo_file = __DIR__ . '/' . $logo_path;
      ?>
      <?php if(file_exists($logo_file)): ?>
        <img src="<?= h($logo_path) ?>" alt="<?= h($branding['logo_alt']) ?>" style="height: 40px; margin-right: 10px; background: white; padding: 5px; border-radius: 5px;">
      <?php else: ?>
        <img src="assets/images/midia-certa-432x107.png" alt="<?= h($branding['logo_alt']) ?>" style="height: 40px; margin-right: 10px; background: white; padding: 5px; border-radius: 5px;">
      <?php endif; ?>
      <span><?= h($branding['nome_empresa']) ?> - Portal</span>
    </a>
    <div class="d-flex align-items-center gap-2">
      <span class="text-white">Olá, <strong><?= h($client['name']) ?></strong></span>
      <a href="client_portal.php" class="btn btn-outline-light btn-sm">← Voltar</a>
    </div>
  </div>
</nav>

<div class="container py-5">
  
  <div class="order-header">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h2 class="mb-2">Pedido #<?= h($order['code']) ?></h2>
        <p class="text-muted mb-0">Criado em <?= date('d/m/Y \à\s H:i', strtotime($order['created_at'])) ?></p>
      </div>
      <div class="text-end">
        <?php if($order['doc_kind']==='budget'): ?>
          <span class="badge bg-warning text-dark fs-6">ORÇAMENTO</span>
        <?php else: ?>
          <span class="badge bg-success fs-6">VENDA</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="row">
    <div class="col-lg-8">
      
      <!-- Status do Pedido -->
      <div class="info-card">
        <h5 class="mb-4">📊 Status do Pedido</h5>
        <div class="status-timeline">
          <div class="timeline-item completed">
            <strong>Atendimento</strong>
            <p class="text-muted small mb-0">Pedido registrado</p>
          </div>
          <div class="timeline-item <?= in_array($order['status'], ['conferencia','producao','disponivel','finalizada'])?'completed':'active' ?>">
            <strong>Conferência</strong>
            <p class="text-muted small mb-0">Análise de arquivos</p>
          </div>
          <div class="timeline-item <?= in_array($order['status'], ['producao','disponivel','finalizada'])?'completed':'' ?>">
            <strong>Produção</strong>
            <p class="text-muted small mb-0">Em processo de produção</p>
          </div>
          <div class="timeline-item <?= in_array($order['status'], ['disponivel','finalizada'])?'completed':'' ?>">
            <strong>Disponível</strong>
            <p class="text-muted small mb-0">Pronto para retirada/entrega</p>
          </div>
          <div class="timeline-item <?= $order['status']==='finalizada'?'completed':'' ?>">
            <strong>Finalizado</strong>
            <p class="text-muted small mb-0">Pedido concluído</p>
          </div>
        </div>
      </div>
      
      <!-- Itens do Pedido -->
      <div class="info-card">
        <h5 class="mb-3">📦 Itens do Pedido</h5>
        <div class="table-responsive">
          <table class="table">
            <thead class="table-light">
              <tr>
                <th>Produto</th>
                <th class="text-center">Qtd</th>
                <th class="text-end">Preço Unit.</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($items as $item): ?>
                <tr>
                  <td>
                    <strong><?= h($item['item_name']) ?></strong><br>
                    <small class="text-muted"><?= h($item['item_type']) ?></small>
                    <?php if($item['notes']): ?>
                      <br><small class="text-info">💬 <?= h($item['notes']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><?= h($item['qty']) ?></td>
                  <td class="text-end"><?= money($item['unit_price']) ?></td>
                  <td class="text-end"><strong><?= money($item['qty'] * $item['unit_price']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                <td class="text-end"><strong><?= money($subtotal) ?></strong></td>
              </tr>
              <?php if($delivery_fee > 0): ?>
                <tr>
                  <td colspan="3" class="text-end">Frete (<?= h($order['delivery_method']) ?>):</td>
                  <td class="text-end"><?= money($delivery_fee) ?></td>
                </tr>
              <?php endif; ?>
              <tr class="table-primary">
                <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                <td class="text-end"><h5 class="mb-0"><?= money($total) ?></h5></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      
      <!-- Arquivos Anexados -->
      <?php if(!empty($files)): ?>
        <div class="info-card">
          <h5 class="mb-3">📎 Arquivos Anexados</h5>
          <div class="list-group">
            <?php foreach($files as $file): ?>
              <a href="<?= h($base) ?>/<?= h($file['file_path']) ?>" 
                 target="_blank" 
                 class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= h($file['original_name']) ?></strong><br>
                    <small class="text-muted"><?= h($file['file_type'] ?? 'Arquivo') ?></small>
                  </div>
                  <span class="badge bg-primary">Ver arquivo</span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      
    </div>
    
    <div class="col-lg-4">
      
      <!-- Informações de Entrega -->
      <div class="info-card">
        <h5 class="mb-3">🚚 Entrega</h5>
        <p><strong>Método:</strong> <?= h(ucfirst($order['delivery_method'] ?? 'Retirada')) ?></p>
        <?php if($order['delivery_deadline']): ?>
          <p><strong>Prazo:</strong> <?= date('d/m/Y', strtotime($order['delivery_deadline'])) ?></p>
        <?php endif; ?>
        <?php if($delivery_fee > 0): ?>
          <p><strong>Custo do frete:</strong> <?= money($delivery_fee) ?></p>
        <?php endif; ?>
      </div>
      
      <!-- Vendedor -->
      <div class="info-card">
        <h5 class="mb-3">👤 Vendedor Responsável</h5>
        <p class="mb-0"><strong><?= h($order['seller_name']) ?></strong></p>
      </div>
      
      <!-- Observações -->
      <?php if($order['notes']): ?>
        <div class="info-card">
          <h5 class="mb-3">📝 Observações</h5>
          <p class="mb-0"><?= nl2br(h($order['notes'])) ?></p>
        </div>
      <?php endif; ?>
      
    </div>
  </div>
  
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
