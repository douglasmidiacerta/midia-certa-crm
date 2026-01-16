<?php
require_once __DIR__.'/../config/utils.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if($id<=0){
  flash_set('danger','Cliente inválido.');
  header('Location: '.$base.'/app.php?page=clients');
  exit;
}

// Carrega cliente
$st = $pdo->prepare("SELECT * FROM clients WHERE id=? LIMIT 1");
$st->execute([$id]);
$c = $st->fetch(PDO::FETCH_ASSOC);
if(!$c){
  flash_set('danger','Cliente não encontrado.');
  header('Location: '.$base.'/app.php?page=clients');
  exit;
}

?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Editar cliente</h4>
    <div class="text-muted small">ID #<?= (int)$c['id'] ?></div>
  </div>
  <div>
    <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=clients">Voltar</a>
  </div>
</div>

<?php
// Processamento POST
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? 'save';
  if($action==='save'){
    $name = trim($_POST['name'] ?? '');
    $whatsapp = preg_replace('/\D+/', '', $_POST['whatsapp'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $street = trim($_POST['address_street'] ?? '');
    $number = trim($_POST['address_number'] ?? '');
    $neigh = trim($_POST['address_neighborhood'] ?? '');
    $city = trim($_POST['address_city'] ?? '');
    $state = trim($_POST['address_state'] ?? '');
    $comp = trim($_POST['address_complement'] ?? '');

    if($name==='' || $whatsapp===''){
      echo '<div class="alert alert-danger">Nome e WhatsApp são obrigatórios.</div>';
    } else {
      $st = $pdo->prepare("UPDATE clients SET name=?, whatsapp=?, contact_name=?, phone=?, email=?, cpf=?, cnpj=?, cep=?, address_street=?, address_number=?, address_neighborhood=?, address_city=?, address_state=?, address_complement=? WHERE id=?");
      $st->execute([$name,$whatsapp,$contact_name,$phone,$email,$cpf,$cnpj,$cep,$street,$number,$neigh,$city,$state,$comp,$id]);
      
      // Atualiza/cria acesso ao portal do cliente
      $portal_email = trim($_POST['portal_email'] ?? '');
      $portal_password = $_POST['portal_password'] ?? '';
      $portal_enabled = isset($_POST['portal_enabled']) ? 1 : 0;
      
      if($portal_email && $portal_password){
        require_once __DIR__ . '/../config/client_auth.php';
        
        // Verifica se já tem auth
        $check = $pdo->prepare("SELECT id FROM client_auth WHERE client_id = ?");
        $check->execute([$id]);
        $existing = $check->fetch();
        
        if($existing){
          // Atualiza
          $password_hash = password_hash($portal_password, PASSWORD_DEFAULT);
          $st = $pdo->prepare("UPDATE client_auth SET email = ?, password_hash = ?, active = ?, email_verified = 1 WHERE client_id = ?");
          $st->execute([$portal_email, $password_hash, $portal_enabled, $id]);
        } else {
          // Cria novo
          $password_hash = password_hash($portal_password, PASSWORD_DEFAULT);
          $st = $pdo->prepare("INSERT INTO client_auth (client_id, email, password_hash, active, email_verified) VALUES (?, ?, ?, ?, 1)");
          $st->execute([$id, $portal_email, $password_hash, $portal_enabled]);
        }
        
        // Atualiza portal_enabled no cliente
        $pdo->prepare("UPDATE clients SET portal_enabled = ? WHERE id = ?")->execute([$portal_enabled, $id]);
      }
      
      flash_set('success','Cliente atualizado.');
      header('Location: '.$base.'/app.php?page=clients');
      exit;
    }
  }
  if($action==='delete'){
    // Admin pode excluir de verdade se não tiver O.S; senão soft-delete.
    $hasOs = (int)$pdo->query("SELECT COUNT(*) FROM os WHERE client_id=".$id)->fetchColumn();
    if($hasOs>0){
      $pdo->prepare("UPDATE clients SET active=0 WHERE id=?")->execute([$id]);
      flash_set('warning','Cliente desativado (possui O.S vinculadas).');
    } else {
      $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
      flash_set('success','Cliente excluído.');
    }
    header('Location: '.$base.'/app.php?page=clients');
    exit;
  }
}
?>

<div class="card p-3">
  <form method="post" data-cep="1">
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">Nome *</label>
        <input name="name" class="form-control" required value="<?= h($c['name']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">WhatsApp *</label>
        <input name="whatsapp" class="form-control" required value="<?= h($c['whatsapp']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Contato do responsável</label>
        <input name="contact_name" class="form-control" value="<?= h($c['contact_name']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Telefone fixo</label>
        <input name="phone" class="form-control" value="<?= h($c['phone']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">E-mail</label>
        <input name="email" class="form-control" value="<?= h($c['email']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">CPF</label>
        <input name="cpf" class="form-control" value="<?= h($c['cpf']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">CNPJ</label>
        <input name="cnpj" class="form-control" value="<?= h($c['cnpj']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">CEP</label>
        <input name="cep" class="form-control" value="<?= h($c['cep']) ?>" maxlength="9">
        <small class="text-muted">
          <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener">
            🔍 Buscar CEP
          </a>
        </small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Rua</label>
        <input name="address_street" class="form-control" value="<?= h($c['address_street']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Número</label>
        <input name="address_number" class="form-control" value="<?= h($c['address_number']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Bairro</label>
        <input name="address_neighborhood" class="form-control" value="<?= h($c['address_neighborhood']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Cidade</label>
        <input name="address_city" class="form-control" value="<?= h($c['address_city']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">UF</label>
        <input name="address_state" class="form-control" value="<?= h($c['address_state']) ?>">
      </div>
      <div class="col-md-12">
        <label class="form-label">Complemento</label>
        <input name="address_complement" class="form-control" value="<?= h($c['address_complement']) ?>">
      </div>
      
      <!-- Acesso ao Portal do Cliente -->
      <div class="col-12 mt-3">
        <hr>
        <h6>🔐 Acesso ao Portal do Cliente</h6>
        <div class="text-muted small mb-2">Configure o acesso para que o cliente possa acompanhar seus pedidos online</div>
      </div>
      
      <?php
      // Busca dados de autenticação existentes
      $auth_st = $pdo->prepare("SELECT * FROM client_auth WHERE client_id = ?");
      $auth_st->execute([$id]);
      $auth = $auth_st->fetch();
      ?>
      
      <div class="col-md-6">
        <label class="form-label">Email do Portal</label>
        <input name="portal_email" type="email" class="form-control" value="<?= h($auth['email'] ?? $c['email']) ?>" placeholder="email@cliente.com">
        <small class="text-muted">Email que o cliente usará para fazer login</small>
      </div>
      
      <div class="col-md-4">
        <label class="form-label">Senha do Portal</label>
        <input name="portal_password" type="password" class="form-control" placeholder="<?= $auth ? 'Deixe em branco para manter' : 'Defina uma senha' ?>">
        <small class="text-muted">Mínimo 6 caracteres</small>
      </div>
      
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="portal_enabled" id="portal_enabled" <?= ($auth && $auth['active']) || (!$auth && $c['portal_enabled']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="portal_enabled">
            Portal Ativo
          </label>
        </div>
      </div>
      
      <?php if($auth): ?>
        <div class="col-12">
          <div class="alert alert-info mb-0">
            ✅ Cliente já tem acesso ao portal! <br>
            <small>Último login: <?= $auth['last_login'] ? h($auth['last_login']) : 'Nunca' ?></small><br>
            <div class="d-flex gap-2 mt-2">
              <a href="<?= h($base) ?>/client_portal.php" target="_blank" class="btn btn-sm btn-primary">
                🌐 Abrir Portal do Cliente
              </a>
              <?php if($c['whatsapp']): 
                $portal_url = rtrim($base ?? '', '/') . '/client_login.php?email=' . urlencode($auth['email']);
                $whatsapp_message = "Olá! Você tem acesso ao nosso Portal do Cliente! 🎉\n\n";
                $whatsapp_message .= "📱 *Como acessar:*\n";
                $whatsapp_message .= "1. Clique no link: " . $portal_url . "\n";
                $whatsapp_message .= "2. Digite sua senha (que você cadastrou)\n";
                $whatsapp_message .= "3. Pronto! Você pode acompanhar seus pedidos em tempo real\n\n";
                $whatsapp_message .= "💡 *No portal você pode:*\n";
                $whatsapp_message .= "• Ver todos seus pedidos\n";
                $whatsapp_message .= "• Aprovar artes online\n";
                $whatsapp_message .= "• Acompanhar status em tempo real\n";
                $whatsapp_message .= "• Ver promoções exclusivas\n\n";
                $whatsapp_message .= "Qualquer dúvida, estamos à disposição! 😊";
                $whatsapp_link = "https://wa.me/55" . preg_replace('/\D/', '', $c['whatsapp']) . "?text=" . urlencode($whatsapp_message);
              ?>
                <a href="<?= h($whatsapp_link) ?>" target="_blank" class="btn btn-sm btn-success">
                  📱 Enviar Acesso por WhatsApp
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-primary" name="action" value="save">Salvar</button>
      <?php if(is_admin()): ?>
        <button class="btn btn-outline-danger" name="action" value="delete" onclick="return confirm('Excluir/desativar este cliente?')">Excluir</button>
      <?php endif; ?>
    </div>
  </form>
</div>

<script src="<?= h($base) ?>/assets/js/cep.js"></script>
