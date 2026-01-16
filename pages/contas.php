<?php
$action = $_POST['action'] ?? '';
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='create'){
  $vals = [];
  $name = trim($_POST['name'] ?? '');
  $vals[] = $name;
  $institution = trim($_POST['institution'] ?? '');
  $vals[] = $institution;
  $type = trim($_POST['type'] ?? '');
  $vals[] = $type;
  $st = $pdo->prepare("INSERT INTO cash_accounts (name, institution, type, active, created_at) VALUES (?, ?, ?, 1, ?)");
  $vals[] = now();
  $st->execute($vals);
  audit($pdo,'create','contas',$pdo->lastInsertId(),null);
  flash_set('success','Cadastrado com sucesso.');
  redirect($base.'/app.php?page=contas');
}

$rows = $pdo->query("SELECT * FROM cash_accounts WHERE active=1 ORDER BY id DESC LIMIT 300")->fetchAll();
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3">
      <h5 style="font-weight:900">Novo cadastro</h5>
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="create">
        <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="name"></div><div class="col-md-6"><label class="form-label">Instituição</label><input class="form-control" name="institution"></div><div class="col-md-6"><label class="form-label">Tipo</label><input class="form-control" name="type"></div>
        <div class="col-12">
          <button class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card p-3">
      <div style="font-weight:800" class="mb-2">Lista</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Nome</th><th>Instituição</th><th>Tipo</th><th>ID</th></tr></thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= h($r['name']) ?></td><td><?= h($r['institution']) ?></td><td><?= h($r['type']) ?></td>
                <td class="text-muted"><?= h($r['id']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
