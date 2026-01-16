<?php
require_login();
require_role(['admin','vendas','financeiro']);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $name = trim($_POST['name'] ?? '');
  $whatsapp = trim($_POST['whatsapp'] ?? '');
  $phone_fixed = trim($_POST['phone_fixed'] ?? '');
  $contact_name = trim($_POST['contact_name'] ?? '');
  $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? ''); // Campo único
  $email = trim($_POST['email'] ?? '');
  $cep = trim($_POST['cep'] ?? '');
  $street = trim($_POST['address_street'] ?? '');
  $number = trim($_POST['address_number'] ?? '');
  $neighborhood = trim($_POST['address_neighborhood'] ?? '');
  $city = trim($_POST['address_city'] ?? '');
  $state = trim($_POST['address_state'] ?? '');
  $complement = trim($_POST['address_complement'] ?? '');
  $notes = trim($_POST['notes'] ?? '');
  
  // Separa CPF ou CNPJ
  $cpf_cnpj_limpo = preg_replace('/[^\d]/', '', $cpf_cnpj);
  $cpf = strlen($cpf_cnpj_limpo) === 11 ? $cpf_cnpj : '';
  $cnpj = strlen($cpf_cnpj_limpo) === 14 ? $cpf_cnpj : '';
  
  // Campos do portal
  $portal_enabled = isset($_POST['portal_enabled']) ? 1 : 0;
  $portal_password = $_POST['portal_password'] ?? '';

  if(!$name || !$whatsapp){
    flash_set('danger','Nome e WhatsApp são obrigatórios.');
    redirect($base.'/app.php?page=clients');
  }
  
  $pdo->beginTransaction();
  
  try {
    // compat:
    $doc = $cnpj ?: $cpf;
    $address_legacy = trim($street.' '.$number.' - '.$neighborhood.' - '.$city.'/'.$state);

    $st = $pdo->prepare("INSERT INTO clients (name,contact_name,whatsapp,phone_fixed,email,cpf,cnpj,cep,address_street,address_number,address_neighborhood,address_city,address_state,address_complement,notes,phone,doc,address,portal_enabled,created_at,active)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),1)");
    $st->execute([$name,$contact_name,$whatsapp,$phone_fixed,$email,$cpf,$cnpj,$cep,$street,$number,$neighborhood,$city,$state,$complement,$notes,$whatsapp,$doc,$address_legacy,$portal_enabled]);
    $client_id = (int)$pdo->lastInsertId();
    
    // Cria acesso ao portal se solicitado
    if($portal_enabled && $email && $portal_password){
      require_once __DIR__ . '/../config/client_auth.php';
      
      $password_hash = password_hash($portal_password, PASSWORD_DEFAULT);
      $st = $pdo->prepare("INSERT INTO client_auth (client_id, email, password_hash, active, email_verified) VALUES (?, ?, ?, 1, 1)");
      $st->execute([$client_id, $email, $password_hash]);
    }
    
    $pdo->commit();
    flash_set('success','Cliente cadastrado com sucesso!');
    redirect($base.'/app.php?page=clients');
    
  } catch(Exception $e){
    $pdo->rollBack();
    flash_set('danger','Erro ao cadastrar cliente: '.$e->getMessage());
    redirect($base.'/app.php?page=clients');
  }
}

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT id,name,whatsapp,contact_name,address_city,address_state,created_at FROM clients WHERE active=1";
if($q){
  $sql .= " AND (name LIKE ? OR whatsapp LIKE ? OR contact_name LIKE ? OR cpf LIKE ? OR cnpj LIKE ?)";
  $like = '%'.$q.'%';
  $params = [$like,$like,$like,$like,$like];
}
$sql .= " ORDER BY name LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-3">
      <div class="d-flex align-items-center justify-content-between gap-2">
        <h5 style="font-weight:900" class="m-0">Clientes</h5>
        <div class="d-flex gap-2">
          <form class="d-flex gap-2" method="get" action="<?=h($base)?>/app.php">
            <input type="hidden" name="page" value="clients">
            <input class="form-control form-control-sm" name="q" value="<?=h($q)?>" placeholder="Buscar nome/whatsapp/cpf/cnpj...">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Buscar</button>
          </form>
          <a class="btn btn-sm btn-primary" href="<?= h($base) ?>/app.php?page=clients_new">+ Novo Cliente</a>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-2">
        <div class="text-muted small">
          Importação de clientes (PDF → CSV): use a ferramenta de importação para inserir os clientes legados.
        </div>
        <a class="btn btn-sm btn-outline-primary" href="<?= h($base) ?>/app.php?page=clients_import">Importar clientes</a>
      </div>


      <div class="table-responsive mt-2">
        <table class="table table-sm align-middle">
          <thead><tr><th>Nome</th><th>WhatsApp</th><th>Responsável</th><th>Cidade</th><th class="text-muted">Criado</th><th class="text-end">Ações</th></tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= h($r['name']) ?></td>
              <td><?= h($r['whatsapp']) ?></td>
              <td><?= h($r['contact_name']) ?></td>
              <td><?= h(trim(($r['address_city']??'').'/'.($r['address_state']??''), '/')) ?></td>
              <td class="text-muted small"><?= h(substr($r['created_at'],0,10)) ?></td>
              <td class="text-end">
                <?php
                // Verifica se cliente tem acesso ao portal
                $auth_check = $pdo->prepare("SELECT id, email FROM client_auth WHERE client_id = ? AND active = 1");
                $auth_check->execute([$r['id']]);
                $client_auth = $auth_check->fetch();
                
                if($client_auth):
                ?>
                  <button class="btn btn-sm btn-outline-info" onclick="gerarLinkAcesso(<?= (int)$r['id'] ?>, '<?= h($client_auth['email']) ?>')" title="Gerar link de acesso">
                    🔗 Link
                  </button>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= h($base) ?>/app.php?page=clients_edit&id=<?= (int)$r['id'] ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card p-3">
      <h6 style="font-weight:900">Cadastro Rápido</h6>
      <p class="text-muted small mb-3">Para cadastro completo com acesso ao portal, use o botão "+ Novo Cliente" acima.</p>

      <form method="post" class="row g-2" data-cep="1">
        <div class="col-12">
          <label class="form-label">Nome *</label>
          <input class="form-control" name="name" required>
        </div>

        <div class="col-12">
          <label class="form-label">WhatsApp *</label>
          <input class="form-control" name="whatsapp" placeholder="(31) 9xxxx-xxxx" required>
        </div>

        <div class="col-12">
          <label class="form-label">Contato do responsável</label>
          <input class="form-control" name="contact_name" placeholder="Nome do responsável">
        </div>

        <div class="col-12">
          <label class="form-label">Telefone fixo</label>
          <input class="form-control" name="phone_fixed" placeholder="(31) xxxx-xxxx">
        </div>

        <div class="col-12">
          <label class="form-label">E-mail</label>
          <input class="form-control" name="email" type="email">
        </div>

        <div class="col-12">
          <label class="form-label">CPF ou CNPJ</label>
          <input class="form-control" name="cpf_cnpj" data-cpf-cnpj placeholder="Digite CPF ou CNPJ">
        </div>

        <div class="col-12"><hr class="my-2"></div>
        
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="portal_enabled_quick" name="portal_enabled" onchange="togglePortalQuick()">
            <label class="form-check-label" for="portal_enabled_quick">
              <strong>Habilitar acesso ao Portal</strong>
            </label>
          </div>
        </div>
        
        <div id="portal_fields_quick" style="display: none; width: 100%;">
          <div class="col-12">
            <label class="form-label">Senha do Portal</label>
            <input type="password" class="form-control" name="portal_password" placeholder="Mínimo 6 caracteres" minlength="6">
            <small class="text-muted">Cliente usará o email acima para login</small>
          </div>
        </div>

        <div class="col-12"><hr class="my-2"></div>

        <div class="col-5">
          <label class="form-label">CEP</label>
          <input class="form-control" name="cep" placeholder="00000-000">
        </div>
        <div class="col-7">
          <label class="form-label">Rua</label>
          <input class="form-control" name="address_street" placeholder="Auto pelo CEP">
        </div>

        <div class="col-4">
          <label class="form-label">Número</label>
          <input class="form-control" name="address_number">
        </div>
        <div class="col-8">
          <label class="form-label">Bairro</label>
          <input class="form-control" name="address_neighborhood" placeholder="Auto pelo CEP">
        </div>

        <div class="col-8">
          <label class="form-label">Cidade</label>
          <input class="form-control" name="address_city" placeholder="Auto pelo CEP">
        </div>
        <div class="col-4">
          <label class="form-label">UF</label>
          <input class="form-control" name="address_state" placeholder="Auto pelo CEP">
        </div>

        <div class="col-12">
          <label class="form-label">Complemento</label>
          <input class="form-control" name="address_complement">
        </div>

        <div class="col-12">
          <label class="form-label">Observações</label>
          <textarea class="form-control" name="notes" rows="3"></textarea>
        </div>

        <div class="col-12">
          <button class="btn btn-primary w-100" type="submit">💾 Cadastrar Cliente</button>
        </div>
      </form>
      
      <script src="<?= h($base) ?>/assets/js/cpf_cnpj.js"></script>
      <script>
      function togglePortalQuick() {
        const checkbox = document.getElementById('portal_enabled_quick');
        const fields = document.getElementById('portal_fields_quick');
        fields.style.display = checkbox.checked ? 'block' : 'none';
      }
      </script>
    </div>
  </div>
</div>

<!-- Modal para Link de Acesso -->
<div class="modal fade" id="linkAcessoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">🔗 Link de Acesso ao Portal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Envie este link para o cliente fazer o primeiro acesso:</p>
        <div class="input-group mb-3">
          <input type="text" class="form-control" id="linkAcessoInput" readonly>
          <button class="btn btn-primary" type="button" onclick="copiarLink()">📋 Copiar</button>
        </div>
        <div class="text-muted small">
          * Após subir este ZIP, rode o SQL <b>database/updates/upgrade_v3.sql</b> no phpMyAdmin (uma única vez).
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal para Link de Acesso -->
<div class="modal fade" id="linkAcessoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">🔗 Link de Acesso ao Portal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Envie este link para o cliente fazer o primeiro acesso:</p>
        <div class="input-group mb-3">
          <input type="text" class="form-control" id="linkAcessoInput" readonly>
          <button class="btn btn-primary" type="button" onclick="copiarLink()">📋 Copiar</button>
        </div>
        <div class="alert alert-info">
          <strong>💡 Instruções para o cliente:</strong><br>
          1. Clique no link<br>
          2. O email já estará preenchido<br>
          3. Digite apenas a senha cadastrada<br>
          4. Faça login no portal
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function gerarLinkAcesso(clientId, email) {
  const baseUrl = '<?= h($base) ?>';
  const link = window.location.origin + baseUrl + '/client_login.php?email=' + encodeURIComponent(email);
  
  document.getElementById('linkAcessoInput').value = link;
  
  const modal = new bootstrap.Modal(document.getElementById('linkAcessoModal'));
  modal.show();
}

function copiarLink() {
  const input = document.getElementById('linkAcessoInput');
  input.select();
  document.execCommand('copy');
  
  alert('✅ Link copiado para a área de transferência!');
}
</script>
