<?php
if (!function_exists('get_config') && isset($pdo)) {
  $site_config = [];
  try {
    $configs = $pdo->query("SELECT config_key, config_value FROM site_config")->fetchAll();
    foreach ($configs as $cfg) {
      $site_config[$cfg['config_key']] = $cfg['config_value'];
    }
  } catch (Exception $e) {
    $site_config = [];
  }

  function get_config($key, $default = '') {
    global $site_config;
    return $site_config[$key] ?? $default;
  }
}

$articles_url = $base ? rtrim($base, '/') . '/site/artigos.php' : '/site/artigos.php';
$articles_text = function_exists('get_config') ? get_config('articles_button_text', 'Artigos') : 'Artigos';
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <?php 
      $logo_path = ltrim($branding['logo_path'], '/');
      if(file_exists(__DIR__ . '/../../' . $logo_path)): 
      ?>
        <img src="../<?= h($logo_path) ?>" alt="<?= h($branding['logo_alt']) ?>" style="height: 40px; margin-right: 10px;">
      <?php endif; ?>
      <strong><?= h($branding['nome_empresa']) ?></strong>
    </a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="produtos.php">Produtos</a>
        </li>
        <?php if ($articles_url): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= h($articles_url) ?>"> <?= h($articles_text) ?></a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link" href="contato.php">Contato</a>
        </li>
        <li class="nav-item">
          <a class="btn btn-primary ms-2" href="../client_login.php">Area do Cliente</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
