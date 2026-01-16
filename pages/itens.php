<?php
$action = $_POST['action'] ?? '';
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='create'){
  $vals = [];
  $name = trim($_POST['name'] ?? '');
  $vals[] = $name;
  $type = trim($_POST['type'] ?? '');
  $vals[] = $type;
  $price = (float)str_replace(',','.',($_POST['price'] ?? 0));
  $vals[] = $price;
  $st = $pdo->prepare("INSERT INTO items (name, type, price, active, created_at) VALUES (?, ?, ?, 1, ?)");
  $vals[] = now();
  $st->execute($vals);
  audit($pdo,'create','itens',$pdo->lastInsertId(),null);
  flash_set('success','Cadastrado com sucesso.');
  redirect($base.'/app.php?page=itens');
}

$rows = $pdo->query("SELECT * FROM items WHERE active=1 ORDER BY id DESC LIMIT 300")->fetchAll();
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card p-3">
      <h5 style="font-weight:900">Novo cadastro</h5>
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="create">
        <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="name"></div><div class="col-md-4"><label class="form-label">Tipo</label>
<select class="form-select" name="type"><option value="produto">Produto (revenda)</option><option value="servico">Serviço</option></select></div><div class="col-md-4"><label class="form-label">Preço padrão</label><input class="form-control" name="price" value="0"></div>
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
          <thead><tr><th>Nome</th><th>Tipo</th><th>Preço padrão</th><th>ID</th></tr></thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= h($r['name']) ?></td><td><?= h($r['type']) ?></td><td><?= h($r['price']) ?></td>
                <td class="text-muted"><?= h($r['id']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
