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

// Se vier com token de aprovação ou tracking, redireciona para a página correta
$approval_token = $_GET['approval_token'] ?? '';
$tracking_token = $_GET['tracking_token'] ?? '';

if($approval_token){
  // Redireciona para aprovação dentro do portal
  header("Location: {$base}/public_approval.php?token=" . urlencode($approval_token));
  exit;
}

if($tracking_token){
  // Redireciona para tracking dentro do portal
  header("Location: {$base}/public_tracking.php?token=" . urlencode($tracking_token));
  exit;
}

// Estatísticas do cliente
$stats = $pdo->prepare("
  SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN doc_kind = 'budget' THEN 1 ELSE 0 END) as total_budgets,
    SUM(CASE WHEN doc_kind = 'sale' AND status IN ('atendimento', 'conferencia', 'producao') THEN 1 ELSE 0 END) as orders_in_progress,
    SUM(CASE WHEN doc_kind = 'sale' AND status = 'finalizada' THEN 1 ELSE 0 END) as orders_completed,
    SUM(CASE WHEN doc_kind = 'sale' AND status IN ('disponivel', 'finalizada') THEN 1 ELSE 0 END) as orders_ready
  FROM os 
  WHERE client_id = ?
");
$stats->execute([$client_id]);
$stats = $stats->fetch();

// Busca pedidos do cliente
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT o.*, u.name as seller_name 
        FROM os o 
        LEFT JOIN users u ON u.id = o.seller_user_id
        WHERE o.client_id = ?";

$params = [$client_id];

if ($filter === 'budgets') {
  $sql .= " AND o.doc_kind = 'budget'";
} elseif ($filter === 'in_progress') {
  $sql .= " AND o.doc_kind = 'sale' AND o.status IN ('atendimento', 'conferencia', 'producao')";
} elseif ($filter === 'completed') {
  $sql .= " AND o.doc_kind = 'sale' AND o.status = 'finalizada'";
} elseif ($filter === 'ready') {
  $sql .= " AND o.doc_kind = 'sale' AND o.status = 'disponivel'";
}

if ($search) {
  $sql .= " AND o.code LIKE ?";
  $params[] = "%$search%";
}

$sql .= " ORDER BY o.created_at DESC LIMIT 50";

$st = $pdo->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

// Busca pedidos aguardando aprovação de arte
$pending_approval = $pdo->prepare("
  SELECT o.id, o.code, o.created_at, t.token
  FROM os o
  JOIN os_approval_tokens t ON t.os_id = o.id
  WHERE o.client_id = ? AND t.used_at IS NULL AND t.expires_at > NOW()
  ORDER BY o.created_at DESC
");
$pending_approval->execute([$client_id]);
$pending_approval = $pending_approval->fetchAll();

// Busca pedidos com arte rejeitada (para informar o cliente)
$rejected_arts = $pdo->prepare("
  SELECT o.id, o.code, o.created_at, t.rejection_reason, t.used_at
  FROM os o
  JOIN os_approval_tokens t ON t.os_id = o.id
  WHERE o.client_id = ? AND t.rejected = 1
  ORDER BY t.used_at DESC
  LIMIT 5
");
$rejected_arts->execute([$client_id]);
$rejected_arts = $rejected_arts->fetchAll();

// Ação de logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  client_logout($pdo);
  header('Location: client_login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meus Pedidos - Portal do Cliente | Mídia Certa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
  <style>
    body {
      background: #f5f7fa;
    }
    .navbar-portal {
      background: <?= $branding['gradiente_secundario'] ?>;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .navbar-brand {
      font-weight: 900;
      font-size: 1.5rem;
      color: white !important;
    }
    .welcome-section {
      background: white;
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .stat-card {
      background: white;
      border-radius: 15px;
      padding: 25px;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      transition: transform 0.2s;
      border-left: 4px solid;
      cursor: pointer;
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 20px rgba(141, 198, 63, 0.2);
    }
    .stat-card h3 {
      font-size: 2.5rem;
      font-weight: 900;
      margin: 10px 0;
    }
    .stat-card p {
      color: #666;
      margin: 0;
      font-weight: 600;
    }
    .orders-section {
      background: white;
      border-radius: 15px;
      padding: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .order-card {
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 15px;
      transition: all 0.2s;
    }
    .order-card:hover {
      border-color: #8DC63F;
      box-shadow: 0 3px 15px rgba(141, 198, 63, 0.15);
    }
    .order-code {
      font-size: 1.3rem;
      font-weight: 900;
      color: #667eea;
    }
    .status-badge {
      padding: 8px 15px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
    }
    .alert-approval {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
      border: none;
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 30px;
    }
    .alert-approval h5 {
      font-weight: 900;
    }
    #clientCarousel .carousel-item img {
      display: block;
      width: 100%;
      height: auto;
    }
    #clientCarousel .carousel-banner-link {
      display: block;
      width: 100%;
      height: 100%;
      color: inherit;
      text-decoration: none;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-portal navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="client_portal.php">
      <?php 
      // Remove a barra inicial do caminho para funcionar corretamente
      $logo_path = ltrim($branding['logo_path'], '/');
      $logo_file = __DIR__ . '/' . $logo_path;
      ?>
      <!-- DEBUG: 
           Logo path: <?= $logo_path ?> 
           File exists: <?= file_exists($logo_file) ? 'YES' : 'NO' ?>
           Full path: <?= $logo_file ?>
      -->
      <?php if(file_exists($logo_file)): ?>
        <img src="<?= h($logo_path) ?>" alt="<?= h($branding['logo_alt']) ?>" style="height: 40px; margin-right: 10px; background: white; padding: 5px; border-radius: 5px;" onerror="console.error('Erro ao carregar logo:', this.src)">
      <?php else: ?>
        <img src="assets/images/midia-certa-432x107.png" alt="<?= h($branding['logo_alt']) ?>" style="height: 40px; margin-right: 10px; background: white; padding: 5px; border-radius: 5px;" onerror="console.error('Erro ao carregar logo fallback:', this.src)">
        <!-- Logo não encontrado: <?= h($logo_file) ?> -->
      <?php endif; ?>
      <span><?= h($branding['nome_empresa']) ?> - Portal</span>
    </a>
    <div class="d-flex align-items-center">
      <span class="text-white me-3">Olá, <strong><?= h($client['name']) ?></strong></span>
      <a href="?action=logout" class="btn btn-outline-light btn-sm">Sair</a>
    </div>
  </div>
</nav>

<div class="container py-5">
  
  <!-- Alertas de Rejeição - Informativo -->
  <?php if (!empty($rejected_arts)): ?>
    <div class="alert alert-info" style="border-left: 5px solid #0dcaf0;">
      <h5 class="mb-3">📝 Artes Rejeitadas Recentemente</h5>
      <p>Você rejeitou algumas artes. Nossa equipe está fazendo as correções e em breve enviará uma nova versão para sua aprovação.</p>
      <div class="accordion" id="rejectedArtsAccordion">
        <?php foreach($rejected_arts as $idx => $r): ?>
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#reject<?= $idx ?>">
                Pedido #<?= h($r['code']) ?> - Rejeitado em <?= date('d/m/Y H:i', strtotime($r['used_at'])) ?>
              </button>
            </h2>
            <div id="reject<?= $idx ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#rejectedArtsAccordion">
              <div class="accordion-body">
                <strong>Motivo da rejeição:</strong><br>
                <?= nl2br(h($r['rejection_reason'])) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Alertas de Aprovação Pendente - ALERTA GRANDE -->
  <?php if (!empty($pending_approval)): ?>
    <div class="alert-approval" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); border: 3px solid #c92a2a; animation: pulse 2s infinite; border-radius: 15px; padding: 30px; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.2);">
      <div class="d-flex align-items-center mb-3">
        <div style="font-size: 4rem; margin-right: 20px; animation: shake 1s infinite;">⚠️</div>
        <div>
          <h2 class="mb-2" style="font-weight: 900; color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
            🚨 ATENÇÃO! Pedidos Aguardando sua Aprovação
          </h2>
          <p class="mb-0" style="font-size: 1.2rem; font-weight: 600;">
            Você tem <strong style="font-size: 2rem; color: #ffd700; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"><?= count($pending_approval) ?></strong> arte(s) aguardando aprovação!
          </p>
        </div>
      </div>
      
      <div class="alert alert-warning mb-3" style="border: 2px solid #ff9800; background: #fff3cd;">
        <h5 class="mb-2" style="color: #856404;">🚨 IMPORTANTE - PRODUÇÃO PAUSADA!</h5>
        <p class="mb-2" style="color: #856404; font-size: 1.05rem;">
          <strong>Seus pedidos NÃO entrarão em produção até que você aprove a arte.</strong>
        </p>
        <ul class="mb-2" style="color: #856404;">
          <li>✅ Após aprovar, o pedido vai <strong>AUTOMATICAMENTE</strong> para produção</li>
          <li>⏱️ Quanto mais rápido aprovar, mais rápido recebe</li>
          <li>🎨 Revise com atenção: você é responsável pela conferência</li>
        </ul>
        <div style="background: #dc3545; color: white; padding: 15px; border-radius: 8px; margin-top: 10px; border: 2px solid #a71d2a;">
          <p class="mb-1" style="font-size: 1.1rem; font-weight: 700;">
            ⚠️ ATENÇÃO: APÓS APROVAR NÃO É POSSÍVEL FAZER ALTERAÇÕES!
          </p>
          <p class="mb-0" style="font-size: 0.95rem;">
            A arte aprovada vai direto para impressão. Confira TUDO com cuidado antes de aprovar: 
            textos, cores, tamanhos, imagens e detalhes. Você será responsável pela conferência.
          </p>
        </div>
      </div>
      
      <div class="d-grid gap-2">
        <?php foreach($pending_approval as $p): 
          // Garante que o caminho base esteja correto
          $approval_url = rtrim($base, '/') . '/public_approval.php?token=' . urlencode($p['token']);
        ?>
          <a href="<?= h($approval_url) ?>" 
             class="btn btn-light btn-lg" target="_blank" style="font-weight: 700; border: 2px solid white;">
            📄 APROVAR AGORA - Pedido #<?= h($p['code']) ?> 
            <small class="d-block" style="font-weight: normal; opacity: 0.9;">
              Criado em <?= date('d/m/Y', strtotime($p['created_at'])) ?>
            </small>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    
    <style>
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.02); }
    }
    @keyframes shake {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(-10deg); }
      75% { transform: rotate(10deg); }
    }
    </style>
  <?php endif; ?>
  
  <!-- Carousel de Promoções -->
  <?php
  try {
    $carousel_slides = $pdo->query("
      SELECT * FROM carousel_slides 
      WHERE active = 1 
      ORDER BY id ASC
    ")->fetchAll();
  } catch (Exception $e) {
    $carousel_slides = [];
  }
  
  if (!empty($carousel_slides)): ?>
  <div id="clientCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
    <div class="carousel-indicators">
      <?php foreach($carousel_slides as $idx => $slide): ?>
        <button type="button" data-bs-target="#clientCarousel" data-bs-slide-to="<?= $idx ?>" 
                class="<?= $idx === 0 ? 'active' : '' ?>" aria-current="<?= $idx === 0 ? 'true' : 'false' ?>" 
                aria-label="Slide <?= $idx + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <div class="carousel-inner" style="border-radius: 10px; overflow: hidden;">
      <?php foreach($carousel_slides as $idx => $slide): ?>
        <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
          <?php 
          // Suporta tanto button_link quanto link_url
          $link = $slide['button_link'] ?? $slide['link_url'] ?? '';
          // Suporta tanto image_path quanto outras variações
          $image = $slide['image_path'] ?? '';
          // Suporta title e subtitle
          $title = $slide['title'] ?? '';
          $desc = $slide['subtitle'] ?? $slide['description'] ?? '';
          ?>
          
          <?php if (!empty($link)): ?>
            <a href="<?= h($link) ?>" target="_blank" class="carousel-banner-link">
          <?php endif; ?>
              <?php if (!empty($image)): ?>
                <img src="<?= h($base . '/' . $image) ?>" class="d-block w-100" 
                     alt="<?= h($title) ?>">
              <?php else: ?>
                <div class="d-block w-100" style="height: 400px; background: <?= h($slide['background_color'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)') ?>; display: flex; align-items: center; justify-content: center;">
                  <div class="text-center" style="color: <?= h($slide['text_color'] ?? '#ffffff') ?>;">
                    <h2><?= h($title) ?></h2>
                    <?php if ($desc): ?>
                      <p><?= h($desc) ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
          <?php if (!empty($link)): ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#clientCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Anterior</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#clientCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Próximo</span>
    </button>
  </div>
  <?php endif; ?>
  
  <!-- Seção de Boas-vindas -->
  <div class="welcome-section">
    <h2 class="mb-1">Bem-vindo de volta! 👋</h2>
    <p class="text-muted mb-0">Aqui você pode acompanhar todos os seus pedidos em tempo real</p>
  </div>
  
  <!-- Estatísticas -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="stat-card" style="border-left-color: #667eea;" onclick="filterOrders('all')">
        <p>Total de Pedidos</p>
        <h3><?= h($stats['total_orders'] ?? 0) ?></h3>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card" style="border-left-color: #ffc107;" onclick="filterOrders('budgets')">
        <p>Orçamentos</p>
        <h3><?= h($stats['total_budgets'] ?? 0) ?></h3>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card" style="border-left-color: #fd7e14;" onclick="filterOrders('in_progress')">
        <p>Em Produção</p>
        <h3><?= h($stats['orders_in_progress'] ?? 0) ?></h3>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card" style="border-left-color: #28a745;" onclick="filterOrders('completed')">
        <p>Finalizados</p>
        <h3><?= h($stats['orders_completed'] ?? 0) ?></h3>
      </div>
    </div>
  </div>
  
  <!-- Lista de Pedidos -->
  <div class="orders-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">📦 Meus Pedidos</h4>
      <div class="d-flex gap-2">
        <form method="get" class="d-flex gap-2">
          <input type="text" class="form-control" name="search" placeholder="Buscar por código" 
                 value="<?= h($search) ?>">
          <button class="btn btn-primary" type="submit">Buscar</button>
        </form>
      </div>
    </div>
    
    <!-- Filtros -->
    <div class="btn-group mb-4" role="group">
      <a href="?filter=all" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline-primary' ?>">
        Todos
      </a>
      <a href="?filter=budgets" class="btn btn-sm <?= $filter==='budgets'?'btn-primary':'btn-outline-primary' ?>">
        Orçamentos
      </a>
      <a href="?filter=in_progress" class="btn btn-sm <?= $filter==='in_progress'?'btn-primary':'btn-outline-primary' ?>">
        Em Produção
      </a>
      <a href="?filter=ready" class="btn btn-sm <?= $filter==='ready'?'btn-primary':'btn-outline-primary' ?>">
        Prontos
      </a>
      <a href="?filter=completed" class="btn btn-sm <?= $filter==='completed'?'btn-primary':'btn-outline-primary' ?>">
        Finalizados
      </a>
    </div>
    
    <!-- Cards de Pedidos -->
    <?php if (empty($orders)): ?>
      <div class="text-center py-5">
        <p class="text-muted">Nenhum pedido encontrado com os filtros selecionados.</p>
      </div>
    <?php else: ?>
      <?php foreach($orders as $order): ?>
        <div class="order-card">
          <div class="row align-items-center">
            <div class="col-md-2">
              <div class="order-code">#<?= h($order['code']) ?></div>
              <small class="text-muted"><?= date('d/m/Y', strtotime($order['created_at'])) ?></small>
            </div>
            
            <div class="col-md-2">
              <?php if($order['doc_kind']==='budget'): ?>
                <span class="badge bg-warning text-dark">ORÇAMENTO</span>
              <?php else: ?>
                <span class="badge bg-success">VENDA</span>
              <?php endif; ?>
            </div>
            
            <div class="col-md-3">
              <?php
                $statusColors = [
                  'atendimento' => 'info',
                  'aguardando_aprovacao' => 'warning',
                  'conferencia' => 'primary',
                  'producao' => 'warning',
                  'disponivel' => 'success',
                  'finalizada' => 'secondary',
                  'refugado' => 'danger',
                  'cancelada' => 'dark'
                ];
                $statusLabels = [
                  'atendimento' => 'Em Atendimento',
                  'aguardando_aprovacao' => 'Aguardando sua Aprovação',
                  'conferencia' => 'Em Conferência',
                  'producao' => 'Em Produção',
                  'disponivel' => 'Pronto para Retirada',
                  'finalizada' => 'Finalizado',
                  'refugado' => 'Refugado',
                  'cancelada' => 'Cancelado'
                ];
                $color = $statusColors[$order['status']] ?? 'secondary';
                $label = $statusLabels[$order['status']] ?? ucfirst($order['status']);
              ?>
              <span class="status-badge bg-<?= $color ?> text-white">
                <?= h($label) ?>
              </span>
            </div>
            
            <div class="col-md-3">
              <small class="text-muted">Vendedor:</small><br>
              <strong><?= h($order['seller_name']) ?></strong>
            </div>
            
            <div class="col-md-2 text-end">
              <a href="client_order_view.php?id=<?= h($order['id']) ?>" class="btn btn-outline-primary btn-sm">
                Ver Detalhes →
              </a>
            </div>
          </div>
          
          <?php if($order['delivery_method'] && $order['delivery_method'] !== 'retirada'): ?>
            <div class="mt-2">
              <small class="text-muted">
                🚚 Entrega: <strong><?= h(ucfirst($order['delivery_method'])) ?></strong>
                <?php if($order['delivery_deadline']): ?>
                  • Prazo: <?= date('d/m/Y', strtotime($order['delivery_deadline'])) ?>
                <?php endif; ?>
              </small>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterOrders(filter) {
  window.location.href = '?filter=' + filter;
}
</script>
</body>
</html>
