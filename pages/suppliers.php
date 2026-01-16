<?php
require_login();
require_role(['admin','vendas','financeiro']);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $name = trim($_POST['name'] ?? '');
  $whatsapp = trim($_POST['whatsapp'] ?? '');
  $phone_fixed = trim($_POST['phone_fixed'] ?? '');
  $contact_name = trim($_POST['contact_name'] ?? '');
  $cnpj = trim($_POST['cnpj'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $cep = trim($_POST['cep'] ?? '');
  $street = trim($_POST['address_street'] ?? '');
  $number = trim($_POST['address_number'] ?? '');
  $neighborhood = trim($_POST['address_neighborhood'] ?? '');
  $city = trim($_POST['address_city'] ?? '');
  $state = trim($_POST['address_state'] ?? '');
  $complement = trim($_POST['address_complement'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  if(!$name || !$whatsapp){
    flash_set('danger','Nome e WhatsApp são obrigatórios.');
    redirect($base.'/app.php?page=suppliers');
  }

  $st = $pdo->prepare("INSERT INTO suppliers (name,contact_name,whatsapp,phone_fixed,email,cnpj,cep,address_street,address_number,address_neighborhood,address_city,address_state,address_complement,notes,phone,created_at,active)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),1)");
  $st->execute([$name,$contact_name,$whatsapp,$phone_fixed,$email,$cnpj,$cep,$street,$number,$neighborhood,$city,$state,$complement,$notes,$whatsapp]);

  flash_set('success','Fornecedor cadastrado.');
  redirect($base.'/app.php?page=suppliers');
}

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT id,name,whatsapp,contact_name,address_city,address_state,created_at FROM suppliers WHERE active=1";
if($q){
  $sql .= " AND (name LIKE ? OR whatsapp LIKE ? OR contact_name LIKE ? OR cnpj LIKE ?)";
  $like = '%'.$q.'%';
  $params = [$like,$like,$like,$like];
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
        <h5 style="font-weight:900" class="m-0">Fornecedores</h5>
        <form class="d-flex gap-2" method="get" action="<?=h($base)?>/app.php">
          <input type="hidden" name="page" value="suppliers">
          <input class="form-control form-control-sm" name="q" value="<?=h($q)?>" placeholder="Buscar nome/whatsapp/cnpj...">
          <button class="btn btn-sm btn-outline-secondary" type="submit">Buscar</button>
        </form>
      </div>

      <div class="table-responsive mt-2">
        <table class="table table-sm align-middle">
          <thead><tr><th>Nome</th><th>WhatsApp</th><th>Contato</th><th>Cidade</th><th class="text-muted">Criado</th><th class="text-end">Ações</th></tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= h($r['name']) ?></td>
              <td><?= h($r['whatsapp']) ?></td>
              <td><?= h($r['contact_name']) ?></td>
              <td><?= h(trim(($r['address_city']??'').'/'.($r['address_state']??''), '/')) ?></td>
              <td class="text-muted small"><?= h(substr($r['created_at'],0,10)) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?=h($base)?>/app.php?page=suppliers_edit&id=<?= (int)$r['id'] ?>">Editar</a>
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
      <h6 style="font-weight:900">Novo fornecedor</h6>

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
          <input class="form-control" name="contact_name">
        </div>

        <div class="col-12">
          <label class="form-label">Telefone fixo</label>
          <input class="form-control" name="phone_fixed">
        </div>

        <div class="col-12">
          <label class="form-label">E-mail *</label>
          <input class="form-control" name="email" type="email" required>
        </div>

        <div class="col-12">
          <label class="form-label">CNPJ *</label>
          <input class="form-control" name="cnpj" data-cpf-cnpj required>
        </div>

        <div class="col-12"><hr class="my-2"></div>

        <div class="col-5">
          <label class="form-label">CEP *</label>
          <input class="form-control" name="cep" placeholder="00000-000" required>
        </div>
        <div class="col-7">
          <label class="form-label">Rua *</label>
          <input class="form-control" name="address_street" placeholder="Auto pelo CEP" required>
        </div>

        <div class="col-4">
          <label class="form-label">Número *</label>
          <input class="form-control" name="address_number" required>
        </div>
        <div class="col-8">
          <label class="form-label">Bairro *</label>
          <input class="form-control" name="address_neighborhood" placeholder="Auto pelo CEP" required>
        </div>

        <div class="col-8">
          <label class="form-label">Cidade *</label>
          <input class="form-control" name="address_city" placeholder="Auto pelo CEP" required>
        </div>
        <div class="col-4">
          <label class="form-label">UF *</label>
          <input class="form-control" name="address_state" placeholder="Auto pelo CEP" required>
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
          <button class="btn btn-primary" type="submit">Cadastrar</button>
        </div>
      </form>
      
      <script src="<?= h($base) ?>/assets/js/cpf_cnpj.js"></script>
    </div>
  </div>
</div>
