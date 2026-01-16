<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/client_auth.php';
require_once 'config/utils.php';

$base = $config['base_path'] ?? '';

// Se já estiver logado, redireciona para o portal
if (is_client_logged_in()) {
  header('Location: client_portal.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  
  if ($email && $password) {
    $result = client_login($pdo, $email, $password);
    
    if ($result['success']) {
      header('Location: client_portal.php');
      exit;
    } else {
      $error = $result['error'];
    }
  } else {
    $error = 'Por favor, preencha email e senha.';
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Portal do Cliente | Mídia Certa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
  <?php 
  $branding = require_once 'config/branding.php';
  ?>
  <style>
    body {
      background: <?= $branding['gradiente_principal'] ?>;
      min-height: 100vh;
      display: flex;
      align-items: center;
    }
    .login-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      max-width: 450px;
      margin: 0 auto;
    }
    .login-header {
      background: <?= $branding['gradiente_secundario'] ?>;
      color: white;
      padding: 50px 30px;
      text-align: center;
    }
    .logo-container {
      margin-bottom: 20px;
    }
    .logo-container img {
      max-width: 200px;
      height: auto;
      /* Removido filtro - logo já está nas cores corretas */
    }
    .login-header h1 {
      font-size: 2.5rem;
      font-weight: 900;
      margin-bottom: 10px;
    }
    .login-header p {
      opacity: 0.9;
      margin: 0;
    }
    .login-body {
      padding: 40px;
    }
    .form-label {
      font-weight: 600;
      color: #333;
    }
    .form-control {
      padding: 12px 15px;
      border-radius: 10px;
      border: 2px solid #e0e0e0;
      transition: border-color 0.3s;
    }
    .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    .btn-login {
      background: <?= $branding['gradiente_principal'] ?>;
      border: none;
      padding: 15px;
      font-weight: 700;
      font-size: 1.1rem;
      border-radius: 10px;
      transition: transform 0.2s;
    }
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(141, 198, 63, 0.4);
    }
    .form-control:focus {
      border-color: <?= $branding['cores']['primaria'] ?>;
      box-shadow: 0 0 0 0.2rem rgba(141, 198, 63, 0.25);
    }
    .divider {
      text-align: center;
      margin: 30px 0;
      position: relative;
    }
    .divider:before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: #e0e0e0;
    }
    .divider span {
      background: white;
      padding: 0 15px;
      position: relative;
      color: #999;
      font-size: 0.9rem;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="login-card">
    <div class="login-header">
      <div class="logo-container">
        <?php 
        $logo_path = ltrim($branding['logo_path'], '/');
        $logo_file = __DIR__ . '/' . $logo_path;
        if(file_exists($logo_file)): 
        ?>
          <img src="<?= h($logo_path) ?>" alt="<?= h($branding['logo_alt']) ?>">
        <?php else: ?>
          <h2><?= h($branding['nome_empresa']) ?></h2>
        <?php endif; ?>
      </div>
      <h1><?= h($branding['nome_empresa']) ?></h1>
      <p><?= h($branding['slogan']) ?></p>
      <p class="mb-0"><small>Portal do Cliente</small></p>
    </div>
    
    <div class="login-body">
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <strong>Erro:</strong> <?= h($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" required 
                 placeholder="seu@email.com" value="<?= h($_GET['email'] ?? '') ?>" autofocus>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Senha</label>
          <input type="password" class="form-control" name="password" required 
                 placeholder="••••••••">
        </div>
        
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="remember" name="remember">
          <label class="form-check-label" for="remember">
            Lembrar-me
          </label>
        </div>
        
        <button type="submit" class="btn btn-primary btn-login w-100">
          🔐 Entrar no Portal
        </button>
        
        <div class="text-center mt-3">
          <a href="#" class="text-muted small">Esqueceu sua senha?</a>
        </div>
      </form>
      
      <div class="divider">
        <span>ou</span>
      </div>
      
      <div class="text-center">
        <p class="mb-2 text-muted">Ainda não tem uma conta?</p>
        <a href="client_register.php" class="btn btn-outline-primary w-100">
          ✨ Criar Conta Grátis
        </a>
      </div>
    </div>
  </div>
  
  <div class="text-center mt-4">
    <a href="index.php" class="text-white text-decoration-none">
      ← Voltar para o site
    </a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
