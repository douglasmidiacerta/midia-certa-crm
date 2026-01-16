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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $result = client_register($pdo, $_POST);
  
  if ($result['success']) {
    $success = $result['message'];
    // Auto-login após cadastro (pode desabilitar se quiser forçar verificação de email)
    // client_login($pdo, $_POST['email'], $_POST['password']);
    // header('Location: client_portal.php');
    // exit;
  } else {
    $error = $result['error'];
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro - Portal do Cliente | Mídia Certa</title>
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
      padding: 20px 0;
    }
    .register-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      max-width: 900px;
      margin: 0 auto;
    }
    .register-header {
      background: <?= $branding['gradiente_secundario'] ?>;
      color: white;
      padding: 40px;
      text-align: center;
    }
    .register-header h1 {
      font-size: 2rem;
      font-weight: 900;
      margin-bottom: 10px;
    }
    .register-body {
      padding: 40px;
    }
    .form-label {
      font-weight: 600;
      color: #333;
    }
    .btn-register {
      background: <?= $branding['gradiente_principal'] ?>;
      border: none;
      padding: 15px;
      font-weight: 700;
      font-size: 1.1rem;
      border-radius: 10px;
      transition: transform 0.2s;
    }
    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(141, 198, 63, 0.4);
    }
    .benefits {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
    }
    .benefits h5 {
      color: #1B3B6F;
      font-weight: 700;
      margin-bottom: 15px;
    }
    .benefits ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .benefits li {
      padding: 8px 0;
      padding-left: 30px;
      position: relative;
    }
    .benefits li:before {
      content: "✓";
      position: absolute;
      left: 0;
      color: #28a745;
      font-weight: bold;
      font-size: 1.2rem;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="register-card">
    <div class="register-header">
      <?php 
      $logo_path = ltrim($branding['logo_path'], '/');
      $logo_file = __DIR__ . '/' . $logo_path;
      if(file_exists($logo_file)): 
      ?>
        <img src="<?= h($logo_path) ?>" alt="<?= h($branding['logo_alt']) ?>" style="max-width: 200px; margin-bottom: 20px;">
      <?php else: ?>
        <h2><?= h($branding['nome_empresa']) ?></h2>
      <?php endif; ?>
      <h1>Bem-vindo ao Portal do Cliente</h1>
      <p class="mb-0"><?= h($branding['nome_empresa']) ?> - <?= h($branding['slogan']) ?></p>
    </div>
    
    <div class="register-body">
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <strong>Erro:</strong> <?= h($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if ($success): ?>
        <div class="alert alert-success">
          <strong>Sucesso!</strong> <?= h($success) ?>
          <div class="mt-3">
            <a href="client_login.php" class="btn btn-success">Fazer Login</a>
          </div>
        </div>
      <?php else: ?>
        
        <div class="benefits">
          <h5>🌟 Benefícios do Portal do Cliente</h5>
          <div class="row">
            <div class="col-md-6">
              <ul>
                <li>Acompanhe seus pedidos em tempo real</li>
                <li>Aprove artes diretamente pelo portal</li>
                <li>Histórico completo de todas as suas compras</li>
              </ul>
            </div>
            <div class="col-md-6">
              <ul>
                <li>Acesso a orçamentos e notas fiscais</li>
                <li>Suporte direto com nossa equipe</li>
                <li>Notificações sobre o status dos pedidos</li>
              </ul>
            </div>
          </div>
        </div>
        
        <form method="post" class="row g-3" data-cep="1">
          <div class="col-12">
            <h5 class="mb-3">Dados Pessoais</h5>
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Nome Completo / Razão Social *</label>
            <input type="text" class="form-control" name="name" required 
                   placeholder="Seu nome ou empresa">
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" class="form-control" name="email" required 
                   placeholder="seu@email.com">
            <small class="text-muted">Será usado para login</small>
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Senha *</label>
            <input type="password" class="form-control" name="password" required 
                   minlength="6" placeholder="Mínimo 6 caracteres">
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Telefone / WhatsApp *</label>
            <input type="text" class="form-control" name="phone" required
                   placeholder="(00) 00000-0000">
          </div>
          
          <div class="col-md-6">
            <label class="form-label">CPF ou CNPJ *</label>
            <input type="text" class="form-control" name="cpf_cnpj" required
                   data-cpf-cnpj placeholder="Digite CPF ou CNPJ">
            <small class="text-muted">Validação automática</small>
          </div>
          
          <div class="col-12 mt-4">
            <h5 class="mb-3">Endereço</h5>
          </div>
          
          <div class="col-md-4">
            <label class="form-label">CEP *</label>
            <input type="text" class="form-control" name="cep" required
                   placeholder="00000-000" maxlength="9">
            <small class="text-muted">
              Preenche endereço automaticamente | 
              <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener">
                🔍 Buscar CEP
              </a>
            </small>
          </div>
          
          <div class="col-md-8">
            <label class="form-label">Rua/Logradouro *</label>
            <input type="text" class="form-control" name="address_street" required
                   placeholder="Nome da rua">
          </div>
          
          <div class="col-md-3">
            <label class="form-label">Número *</label>
            <input type="text" class="form-control" name="address_number" required
                   placeholder="123">
          </div>
          
          <div class="col-md-5">
            <label class="form-label">Bairro *</label>
            <input type="text" class="form-control" name="address_neighborhood" required
                   placeholder="Bairro">
          </div>
          
          <div class="col-md-4">
            <label class="form-label">Complemento</label>
            <input type="text" class="form-control" name="address_complement" 
                   placeholder="Apto, sala, etc.">
          </div>
          
          <div class="col-md-8">
            <label class="form-label">Cidade *</label>
            <input type="text" class="form-control" name="address_city" required
                   placeholder="Sua cidade">
          </div>
          
          <div class="col-md-4">
            <label class="form-label">Estado *</label>
            <input type="text" class="form-control" name="address_state" required
                   placeholder="UF" maxlength="2" style="text-transform: uppercase;">
          </div>
          
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="terms" required>
              <label class="form-check-label" for="terms">
                Aceito os <a href="#" target="_blank">termos de uso</a> e 
                <a href="#" target="_blank">política de privacidade</a> *
              </label>
            </div>
          </div>
          
          <div class="col-12 mt-4">
            <button type="submit" class="btn btn-primary btn-register w-100">
              ✨ Criar Minha Conta
            </button>
          </div>
          
          <div class="col-12 text-center mt-3">
            <p class="text-muted mb-0">
              Já tem uma conta? 
              <a href="client_login.php" class="fw-bold">Fazer Login</a>
            </p>
          </div>
        </form>
        
      <?php endif; ?>
    </div>
  </div>
  
  <div class="text-center mt-4">
    <a href="index.php" class="text-white text-decoration-none">
      ← Voltar para o site
    </a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/cep.js"></script>
<script src="assets/js/cpf_cnpj.js"></script>
<script>
</body>
</html>
