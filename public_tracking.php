<?php
// Página PÚBLICA para acompanhamento do pedido pelo cliente
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/utils.php';
$config = require __DIR__ . '/config/config.php';

$token = $_GET['token'] ?? '';
if(!$token){
  die('Token inválido.');
}

// Busca o token
$st = $pdo->prepare("SELECT * FROM os_tracking_tokens WHERE token=? LIMIT 1");
$st->execute([$token]);
$tok = $st->fetch();

if(!$tok){
  die('Link inválido.');
}

// Atualiza contadores
$pdo->prepare("UPDATE os_tracking_tokens SET last_accessed_at=NOW(), access_count=access_count+1 WHERE id=?")
    ->execute([$tok['id']]);

// Busca a OS
$st = $pdo->prepare("SELECT o.*, c.name as client_name, c.whatsapp
                     FROM os o
                     JOIN clients c ON c.id = o.client_id
                     WHERE o.id=?");
$st->execute([$tok['os_id']]);
$os = $st->fetch();

if(!$os){
  die('Pedido não encontrado.');
}

$statusLabels = [
  'atendimento' => ['label' => 'Em Atendimento', 'icon' => '📝', 'color' => '#6c757d'],
  'arte' => ['label' => 'Aguardando Aprovação da Arte', 'icon' => '🎨', 'color' => '#ffc107'],
  'conferencia' => ['label' => 'Em Conferência', 'icon' => '🔍', 'color' => '#17a2b8'],
  'producao' => ['label' => 'Em Produção', 'icon' => '⚙️', 'color' => '#007bff'],
  'disponivel' => ['label' => 'Disponível para Retirada/Entrega', 'icon' => '✅', 'color' => '#28a745'],
  'finalizada' => ['label' => 'Finalizada', 'icon' => '🎉', 'color' => '#28a745'],
  'cancelada' => ['label' => 'Cancelada', 'icon' => '❌', 'color' => '#dc3545'],
];

$currentStatus = $statusLabels[$os['status']] ?? ['label' => $os['status'], 'icon' => '•', 'color' => '#000'];

$base = $config['base_path'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acompanhamento do Pedido - Mídia Certa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; padding: 20px; }
    .card { max-width: 700px; margin: 0 auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .header-logo { text-align: center; padding: 20px; background: #0b1f3a; color: white; border-radius: 8px 8px 0 0; }
    .status-badge { font-size: 1.2rem; padding: 15px; border-radius: 8px; text-align: center; font-weight: bold; }
    .timeline { position: relative; padding: 20px 0; }
    .timeline-item { position: relative; padding-left: 40px; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; left: 10px; top: 5px; width: 15px; height: 15px; border-radius: 50%; background: #ddd; }
    .timeline-item.active::before { background: #28a745; }
    .timeline-item.current::before { background: #007bff; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
  </style>
</head>
<body>
  <div class="card">
    <div class="header-logo">
      <h2>Mídia Certa</h2>
      <p class="mb-0">Acompanhamento do Pedido</p>
    </div>
    
    <div class="card-body">
      <h4>Pedido #<?= h($os['code']) ?></h4>
      <p class="mb-3"><strong>Cliente:</strong> <?= h($os['client_name']) ?></p>
      
      <div class="status-badge mb-4" style="background-color: <?= h($currentStatus['color']) ?>; color: white;">
        <?= h($currentStatus['icon']) ?> <?= h($currentStatus['label']) ?>
      </div>
      
      <?php if($os['status'] === 'arte'): ?>
        <div class="alert alert-warning">
          <strong>⚠️ Ação Necessária:</strong> Aguardando sua aprovação da arte para seguir para produção.
          <p class="mb-0 mt-2">Verifique seu email ou WhatsApp para o link de aprovação.</p>
        </div>
      <?php endif; ?>
      
      <h5 class="mt-4 mb-3">Jornada do Pedido</h5>
      <div class="timeline">
        <div class="timeline-item <?= in_array($os['status'], ['atendimento','arte','conferencia','producao','disponivel','finalizada']) ? 'active' : '' ?> <?= $os['status']==='atendimento' ? 'current' : '' ?>">
          <strong>📝 Em Atendimento</strong>
          <p class="text-muted small mb-0">Pedido em análise pela equipe de vendas</p>
        </div>
        
        <div class="timeline-item <?= in_array($os['status'], ['arte','conferencia','producao','disponivel','finalizada']) ? 'active' : '' ?> <?= $os['status']==='arte' ? 'current' : '' ?>">
          <strong>🎨 Aprovação da Arte</strong>
          <p class="text-muted small mb-0">Aguardando cliente aprovar a arte para impressão</p>
        </div>
        
        <div class="timeline-item <?= in_array($os['status'], ['conferencia','producao','disponivel','finalizada']) ? 'active' : '' ?> <?= $os['status']==='conferencia' ? 'current' : '' ?>">
          <strong>🔍 Em Conferência</strong>
          <p class="text-muted small mb-0">Arte sendo conferida pela equipe técnica</p>
        </div>
        
        <div class="timeline-item <?= in_array($os['status'], ['producao','disponivel','finalizada']) ? 'active' : '' ?> <?= $os['status']==='producao' ? 'current' : '' ?>">
          <strong>⚙️ Em Produção</strong>
          <p class="text-muted small mb-0">Seu pedido está sendo produzido</p>
        </div>
        
        <div class="timeline-item <?= in_array($os['status'], ['disponivel','finalizada']) ? 'active' : '' ?> <?= $os['status']==='disponivel' ? 'current' : '' ?>">
          <strong>✅ Disponível</strong>
          <p class="text-muted small mb-0">Pedido pronto para retirada ou entrega</p>
        </div>
        
        <div class="timeline-item <?= $os['status']==='finalizada' ? 'active current' : '' ?>">
          <strong>🎉 Finalizado</strong>
          <p class="text-muted small mb-0">Pedido entregue com sucesso</p>
        </div>
      </div>
      
      <?php if($os['status'] === 'cancelada'): ?>
        <div class="alert alert-danger mt-3">
          <strong>❌ Pedido Cancelado</strong>
        </div>
      <?php endif; ?>
      
      <div class="alert alert-info mt-4">
        <strong>💬 Dúvidas?</strong> Entre em contato conosco pelo WhatsApp!
      </div>
      
      <div class="text-center">
        <button onclick="location.reload()" class="btn btn-outline-primary">🔄 Atualizar Status</button>
      </div>
    </div>
    
    <div class="card-footer text-center text-muted">
      <small>Mídia Certa © <?= date('Y') ?></small>
    </div>
  </div>
</body>
</html>
