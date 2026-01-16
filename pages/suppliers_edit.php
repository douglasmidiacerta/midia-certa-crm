<?php
require_once __DIR__.'/../config/utils.php';
require_once __DIR__.'/../config/auth.php';
require_login();

require_role(['admin','financeiro']);

$id = (int)($_GET['id'] ?? 0);
if($id<=0){
  flash('Fornecedor inválido.');
  header('Location: '.$base.'/app.php?page=suppliers');
  exit;
}

$st = $pdo->prepare("SELECT * FROM suppliers WHERE id=? LIMIT 1");
$st->execute([$id]);
$s = $st->fetch(PDO::FETCH_ASSOC);
if(!$s){
  flash('Fornecedor não encontrado.');
  header('Location: '.$base.'/app.php?page=suppliers');
  exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? 'save';
  if($action==='delete'){
    $pdo->prepare("UPDATE suppliers SET active=0 WHERE id=? LIMIT 1")->execute([$id]);
    audit($pdo, 'update', 'suppliers', $id, ['active'=>0]);
    flash('Fornecedor desativado.');
    header('Location: '.$base.'/app.php?page=suppliers');
    exit;
  }

  $name = trim($_POST['name'] ?? '');
  $whatsapp = preg_replace('/\D+/', '', ($_POST['whatsapp'] ?? ''));
  $contact_name = trim($_POST['contact_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $city = trim($_POST['address_city'] ?? '');
  $state = trim($_POST['address_state'] ?? '');

  if($name===''){
    flash('Nome é obrigatório.');
  } else {
    $pdo->prepare("UPDATE suppliers SET name=?, whatsapp=?, contact_name=?, email=?, address_city=?, address_state=? WHERE id=? LIMIT 1")
      ->execute([$name,$whatsapp,$contact_name,$email,$city,$state,$id]);
    audit($pdo,'update','suppliers',$id,['name'=>$name]);
    flash('Fornecedor atualizado.');
    header('Location: '.$base.'/app.php?page=suppliers');
    exit;
  }
}

?>

<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">Editar fornecedor</h3>
    <a class="btn btn-outline-secondary" href="<?=h($base)?>/app.php?page=suppliers">Voltar</a>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nome *</label>
          <input class="form-control" name="name" value="<?=h($s['name'])?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">WhatsApp</label>
          <input class="form-control" name="whatsapp" value="<?=h($s['whatsapp'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Contato</label>
          <input class="form-control" name="contact_name" value="<?=h($s['contact_name'])?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">E-mail</label>
          <input class="form-control" name="email" value="<?=h($s['email'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Cidade</label>
          <input class="form-control" name="address_city" value="<?=h($s['address_city'])?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">UF</label>
          <input class="form-control" name="address_state" value="<?=h($s['address_state'])?>">
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Salvar</button>
          <button class="btn btn-outline-danger" type="submit" name="action" value="delete" onclick="return confirm('Desativar este fornecedor?');">Desativar</button>
        </div>
      </form>
    </div>
  </div>
</div>
