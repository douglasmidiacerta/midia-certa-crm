<?php
require_role(['admin','vendas']);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $whatsapp = trim($_POST['whatsapp'] ?? '');
  $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? ''); // Campo único
  $cep = trim($_POST['cep'] ?? '');
  $address_street = trim($_POST['address_street'] ?? '');
  $address_number = trim($_POST['address_number'] ?? '');
  $address_neighborhood = trim($_POST['address_neighborhood'] ?? '');
  $address_city = trim($_POST['address_city'] ?? '');
  $address_state = trim($_POST['address_state'] ?? '');
  $address_complement = trim($_POST['address_complement'] ?? '');
  
  // Separa CPF ou CNPJ
  $cpf_cnpj_limpo = preg_replace('/[^\d]/', '', $cpf_cnpj);
  $cpf = strlen($cpf_cnpj_limpo) === 11 ? $cpf_cnpj : '';
  $cnpj = strlen($cpf_cnpj_limpo) === 14 ? $cpf_cnpj : '';
  
  // Campos do portal
  $portal_enabled = isset($_POST['portal_enabled']) ? 1 : 0;
  $portal_email = trim($_POST['portal_email'] ?? $email);
  $portal_password = $_POST['portal_password'] ?? '';
  
  if(!$name || !$whatsapp){
    flash_set('danger','Nome e WhatsApp são obrigatórios.');
    redirect($base.'/app.php?page=clients_new');
  }
  
  $pdo->beginTransaction();
  
  try {
    // Cria cliente
    $st = $pdo->prepare("INSERT INTO clients (name, email, whatsapp, cpf, cnpj, cep, address_street, address_number, address_neighborhood, address_city, address_state, address_complement, portal_enabled, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $st->execute([$name, $email, $whatsapp, $cpf, $cnpj, $cep, $address_street, $address_number, $address_neighborhood, $address_city, $address_state, $address_complement, $portal_enabled]);
    $client_id = (int)$pdo->lastInsertId();
    
    // Cria acesso ao portal se solicitado
    if($portal_enabled && $portal_email && $portal_password){
      require_once __DIR__ . '/../config/client_auth.php';
      
      $password_hash = password_hash($portal_password, PASSWORD_DEFAULT);
      $st = $pdo->prepare("INSERT INTO client_auth (client_id, email, password_hash, active, email_verified) VALUES (?, ?, ?, 1, 1)");
      $st->execute([$client_id, $portal_email, $password_hash]);
    }
    
    $pdo->commit();
    audit($pdo,'create','client',$client_id);
    flash_set('success','Cliente cadastrado com sucesso!');
    redirect($base.'/app.php?page=clients');
    
  } catch(Exception $e){
    $pdo->rollBack();
    flash_set('danger','Erro ao cadastrar cliente: '.$e->getMessage());
    redirect($base.'/app.php?page=clients_new');
  }
}
?>

<div class="card p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 style="font-weight:900">➕ Novo Cliente</h5>
    <a href="<?= h($base) ?>/app.php?page=clients" class="btn btn-outline-secondary">← Voltar</a>
  </div>

  <form method="post" data-cep="1">
    <div class="row g-3">
      
      <!-- Dados Principais -->
      <div class="col-12">
        <h6 class="border-bottom pb-2">📋 Dados Principais</h6>
      </div>
      
      <div class="col-md-6">
        <label class="form-label">Nome / Razão Social *</label>
        <input type="text" class="form-control" name="name" required placeholder="Nome completo ou empresa">
      </div>
      
      <div class="col-md-6">
        <label class="form-label">WhatsApp *</label>
        <input type="text" class="form-control" name="whatsapp" required placeholder="(00) 00000-0000">
      </div>
      
      <div class="col-md-6">
        <label class="form-label">Email *</label>
        <input type="email" class="form-control" name="email" required placeholder="cliente@email.com">
      </div>
      
      <div class="col-md-6">
        <label class="form-label">CPF ou CNPJ *</label>
        <input type="text" class="form-control" name="cpf_cnpj" data-cpf-cnpj required placeholder="Digite CPF ou CNPJ">
        <small class="text-muted">Validação automática</small>
      </div>
      
      <!-- Endereço -->
      <div class="col-12 mt-4">
        <h6 class="border-bottom pb-2">📍 Endereço</h6>
      </div>
      
      <div class="col-md-3">
        <label class="form-label">CEP *</label>
        <input type="text" class="form-control" name="cep" required placeholder="00000-000" maxlength="9">
        <small class="text-muted">
          Preenche endereço automaticamente | 
          <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener">
            🔍 Buscar CEP
          </a>
        </small>
      </div>
      
      <div class="col-md-7">
        <label class="form-label">Rua/Logradouro *</label>
        <input type="text" class="form-control" name="address_street" required placeholder="Nome da rua">
      </div>
      
      <div class="col-md-2">
        <label class="form-label">Número *</label>
        <input type="text" class="form-control" name="address_number" required placeholder="123">
      </div>
      
      <div class="col-md-4">
        <label class="form-label">Bairro *</label>
        <input type="text" class="form-control" name="address_neighborhood" required placeholder="Bairro">
      </div>
      
      <div class="col-md-6">
        <label class="form-label">Cidade *</label>
        <input type="text" class="form-control" name="address_city" required placeholder="Cidade">
      </div>
      
      <div class="col-md-2">
        <label class="form-label">UF *</label>
        <input type="text" class="form-control" name="address_state" required placeholder="SP" maxlength="2">
      </div>
      
      <div class="col-md-12">
        <label class="form-label">Complemento</label>
        <input type="text" class="form-control" name="address_complement" placeholder="Apartamento, sala, etc.">
      </div>
      
      <!-- Acesso ao Portal -->
      <div class="col-12 mt-4">
        <h6 class="border-bottom pb-2">🔐 Acesso ao Portal do Cliente</h6>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="portal_enabled" id="portal_enabled" onchange="togglePortalFields()">
          <label class="form-check-label" for="portal_enabled">
            <strong>Habilitar acesso ao Portal do Cliente</strong>
          </label>
        </div>
      </div>
      
      <div id="portal_fields" style="display: none; width: 100%;">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Email para Login *</label>
            <input type="email" class="form-control" name="portal_email" required placeholder="Email de acesso ao portal">
          </div>
          
          <div class="col-md-6">
            <label class="form-label">Senha Inicial *</label>
            <input type="password" class="form-control" name="portal_password" required placeholder="Mínimo 6 caracteres" minlength="6">
          </div>
          
          <div class="col-12">
            <div class="alert alert-info mb-0">
              💡 <strong>Dica:</strong> Após criar, você poderá gerar um link de primeiro acesso para enviar ao cliente.
            </div>
          </div>
        </div>
      </div>
      
      <!-- Botões -->
      <div class="col-12 mt-4">
        <button type="submit" class="btn btn-success">💾 Salvar Cliente</button>
        <a href="<?= h($base) ?>/app.php?page=clients" class="btn btn-outline-secondary">Cancelar</a>
      </div>
      
    </div>
  </form>
</div>

<script src="<?= h($base) ?>/assets/js/cpf_cnpj.js"></script>
<script>
function togglePortalFields() {
  const checkbox = document.getElementById('portal_enabled');
  const fields = document.getElementById('portal_fields');
  fields.style.display = checkbox.checked ? 'block' : 'none';
  
  // Copia email principal para email do portal se estiver vazio
  if(checkbox.checked){
    const emailPrincipal = document.querySelector('input[name="email"]').value;
    const emailPortal = document.querySelector('input[name="portal_email"]');
    if(emailPrincipal && !emailPortal.value){
      emailPortal.value = emailPrincipal;
    }
  }
}
</script>
