<?php require_login(); $u=user(); ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($appName) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
</head>
<body class="bg-light">
<div class="d-flex" style="min-width: 100vw;">
  <aside class="p-3 text-white" style="width:260px; min-width:260px; flex-shrink:0; background:#0B1E3B; min-height:100vh; position: sticky; top: 0; height: 100vh; overflow-y: auto;">
    <a href="<?= h($base) ?>/app.php?page=dashboard" class="d-block mb-3" style="text-decoration: none;">
      <div class="d-flex align-items-center gap-2">
        <img src="<?= h($base) ?>/assets/images/midia-certa-432x107.png" style="height:34px" alt="Mídia Certa">
      </div>
      <div class="small opacity-75 text-white">CRM/ERP Gráfica</div>
    </a>

    
<nav class="nav flex-column gap-1">

  <div class="text-uppercase small opacity-75 mt-1">Dashboard</div>
  <a class="nav-link text-white <?= $page==='dashboard'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=dashboard">🏠 Dashboard</a>

  <?php if(can_sales()): ?>
    <div class="text-uppercase small opacity-75 mt-3">Vendas</div>
    <a class="nav-link text-white <?= $page==='os'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=os">📋 O.S</a>
    <a class="nav-link text-white <?= $page==='os_new'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=os_new">➕ Nova venda</a>
    <a class="nav-link text-white <?= $page==='reports_sales_advanced'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=reports_sales_advanced">📊 Relatórios</a>
  <?php endif; ?>

  <?php if(can_admin() || can_finance()): ?>
    <div class="text-uppercase small opacity-75 mt-3">Cadastros</div>
    <a class="nav-link text-white <?= $page==='clients'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=clients">👥 Clientes</a>
    <a class="nav-link text-white <?= $page==='items'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=items">📦 Produtos</a>
    <a class="nav-link text-white <?= $page==='suppliers'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=suppliers">🏭 Fornecedores</a>

    <?php if(can_admin()): ?>
      <a class="nav-link text-white <?= $page==='employees'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=employees">👤 Funcionários</a>
      <a class="nav-link text-white <?= $page==='accounts'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=accounts">🔐 Permissões</a>
    <?php endif; ?>

    <!-- NOVO: Menu de Produção -->
    <div class="text-uppercase small opacity-75 mt-3">Produção</div>
    <a class="nav-link text-white <?= $page==='producao_gestao'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=producao_gestao">🏭 Gestão de Produção</a>
    <a class="nav-link text-white <?= $page==='producao_conferencia'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=producao_conferencia">✅ Conferência</a>
    <a class="nav-link text-white <?= $page==='producao_producao'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=producao_producao">🏭 Produção</a>
    <a class="nav-link text-white <?= $page==='producao_expedicao'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=producao_expedicao">📦 Expedição</a>

    <?php if(can_finance()): ?>
      <div class="text-uppercase small opacity-75 mt-3">Financeiro</div>
      <a class="nav-link text-white <?= $page==='finance'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=finance">💰 Financeiro</a>
      <a class="nav-link text-white <?= $page==='cash'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=cash">💵 Caixa</a>
      <a class="nav-link text-white <?= $page==='fin_receber'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=fin_receber">📥 A Receber</a>
      <a class="nav-link text-white <?= $page==='fin_pagar'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=fin_pagar">📤 A Pagar</a>
      <a class="nav-link text-white <?= $page==='card_acquirers'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=card_acquirers">💳 Adquirentes</a>
      <a class="nav-link text-white <?= $page==='reports_finance_advanced'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=reports_finance_advanced">📊 Relatórios</a>

      <div class="text-uppercase small opacity-75 mt-3">Compras</div>
      <a class="nav-link text-white <?= $page==='oc'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=oc">📋 O.C</a>
      <a class="nav-link text-white <?= $page==='purchases'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=purchases">➕ Nova compra</a>
      <a class="nav-link text-white <?= $page==='reports_compras_advanced'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=reports_compras_advanced">📊 Relatórios</a>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Marketing -->
  <?php if(can_admin()): ?>
  <div class="text-uppercase small opacity-75 mt-3" style="color:#f59e0b;font-weight:900">Marketing</div>
  <a class="nav-link text-white <?= $page==='marketing_site'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=marketing_site" style="background:rgba(245,158,11,0.2);border-left:3px solid #f59e0b">
    <span style="color:#f59e0b;font-size:1.2rem">🎨</span> Gerenciar Site
  </a>
  <a class="nav-link text-white <?= $page==='carousel_slides'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=carousel_slides">
    🎠 Slides do Carousel
  </a>
  <a class="nav-link text-white <?= $page==='marketing_produtos'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=marketing_produtos">
    ⭐ Produtos Destaque
  </a>
  <a class="nav-link text-white <?= $page==='marketing_site'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=marketing_site#articles">
    📰 Artigos do Site
  </a>
  <a class="nav-link text-white <?= $page==='marketing_artigos'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=marketing_artigos">
    📝 Gerenciar Artigos
  </a>
  <a class="nav-link text-white small opacity-75" href="<?= h($base) ?>/site/" target="_blank">
    👁️ Ver Site Público
  </a>
  <?php endif; ?>

  <!-- ADMINISTRAÇÃO (Master Only) -->
  <?php if(can_admin()): ?>
  <div class="text-uppercase small opacity-75 mt-3" style="color:#8b5cf6;font-weight:900">ADMINISTRAÇÃO</div>
  <?php
    // Conta solicitações pendentes
    try {
      $pending_requests_st = $pdo->query("SELECT COUNT(*) as total FROM os_change_requests WHERE status='pending'");
      $pending_requests = $pending_requests_st->fetch();
      $pending_count = (int)($pending_requests['total'] ?? 0);
    } catch (PDOException $e) {
      // Se tabela não existe, ignorar
      $pending_count = 0;
    }
  ?>
  <a class="nav-link text-white <?= $page==='os_requests'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=os_requests">
    🔔 Solicitações de Alteração/Exclusão
    <?php if($pending_count > 0): ?>
      <span class="badge bg-danger ms-1"><?= $pending_count ?></span>
    <?php endif; ?>
  </a>
  <a class="nav-link text-white <?= $page==='dre_professional'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=dre_professional">
    📊 DRE Profissional
  </a>
  <a class="nav-link text-white <?= $page==='dre_enhanced'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=dre_enhanced">
    💼 DRE Avançado
  </a>
  <a class="nav-link text-white <?= $page==='dre_export'?'active fw-bold':'' ?>" href="<?= h($base) ?>/app.php?page=dre_export">
    📥 Exportar DRE
  </a>
  <?php endif; ?>

  <!-- Área do Cliente -->
  <div class="text-uppercase small opacity-75 mt-3">Área do Cliente</div>
  <a class="nav-link text-white" href="<?= h($base) ?>/client_portal.php" target="_blank" style="background:rgba(102,126,234,0.2);border-left:3px solid #667eea">
    <span style="color:#667eea;font-size:1.2rem">🌐</span> Portal do Cliente
  </a>
  <a class="nav-link text-white small opacity-75" href="<?= h($base) ?>/client_login.php" target="_blank">
    🔐 Login do Cliente
  </a>
  <a class="nav-link text-white small opacity-75" href="<?= h($base) ?>/client_register.php" target="_blank">
    ✨ Cadastro do Cliente
  </a>

  <hr class="border-light opacity-25 mt-3">
  <a class="nav-link text-white" href="<?= h($base) ?>/logout.php">🚪 Sair</a>
</nav>

  </aside>

  <main style="flex: 1; min-width: 0; padding: 1rem;">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="small text-muted"><?= h($appName) ?></div>
        <div style="font-weight:900">Olá, <?= h($u['name'] ?? '') ?></div>
      </div>
      <div class="small text-muted">
        Perfil: <b><?= strtoupper(h($u['role'] ?? '')) ?></b>
      </div>
    </div>
    <?= flash_html(); ?>
