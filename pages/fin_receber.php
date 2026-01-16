<?php
require_role(['admin','financeiro']);
$clients = $pdo->query("SELECT id,name FROM clients WHERE active=1 ORDER BY name")->fetchAll();

// Excluir conta a receber (apenas master)
if($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='delete_ar')){
  if(($_SESSION['user_role'] ?? '') !== 'master'){
    flash_set('danger','Apenas o master pode excluir contas a receber.');
    redirect($base.'/app.php?page=fin_receber');
  }
  $ar_id = (int)($_POST['ar_id'] ?? 0);
  if($ar_id > 0){
    $pdo->beginTransaction();
    $ar = $pdo->prepare("SELECT * FROM ar_titles WHERE id=?")->execute([$ar_id]);
    $ar = $pdo->query("SELECT * FROM ar_titles WHERE id=$ar_id")->fetch();
    
    if($ar){
      $pdo->prepare("DELETE FROM ar_titles WHERE id=?")->execute([$ar_id]);
      audit($pdo,'delete','ar_titles',$ar_id,['amount'=>$ar['amount'],'client_id'=>$ar['client_id']]);
      $pdo->commit();
      flash_set('success','Conta a receber excluída com sucesso.');
    } else {
      flash_set('danger','Conta a receber não encontrada.');
    }
  }
  redirect($base.'/app.php?page=fin_receber');
}

// Criar conta a receber manual (extra)
if($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['action'] ?? '')==='create_manual')){
  $client_id = (int)($_POST['client_id'] ?? 0);
  $amount = (float)str_replace(',','.',($_POST['amount'] ?? '0'));
  $desc = trim($_POST['description'] ?? '');
  $due = $_POST['due_date'] ?? null;
  $method = $_POST['method'] ?? 'pix';
  if(!$client_id || $amount<=0 || $desc===''){
    flash_set('danger','Preencha cliente, valor e descrição.');
    redirect($base.'/app.php?page=fin_receber');
  }
  $pdo->beginTransaction();
  $st = $pdo->prepare("INSERT INTO ar_titles (os_id, client_id, kind, amount, method, due_date, status, created_at) VALUES (NULL, ?, 'extra', ?, ?, ?, 'aberto', ?)");
  $st->execute([$client_id, $amount, $method, $due ?: null, now()]);
  $new_id = (int)$pdo->lastInsertId();
  // tenta salvar descrição se coluna existir
  try{ $pdo->prepare("UPDATE ar_titles SET description=? WHERE id=?")->execute([$desc,$new_id]); } catch(Exception $e){}
  $pdo->commit();
  audit($pdo,'create_manual_ar','ar_titles',$new_id,['client_id'=>$client_id,'amount'=>$amount,'desc'=>$desc]);
  flash_set('success','Conta a receber criada.');
  redirect($base.'/app.php?page=fin_receber');
}

$os_filter = $_GET['os'] ?? '';
$q = trim($_GET['q'] ?? '');
$where = "t.status='aberto'";
$params = [];

if($os_filter){
  $where .= " AND t.os_id=?";
  $params[] = (int)$os_filter;
}
if($q){
  $where .= " AND (c.name LIKE ? OR COALESCE(o.code,'') LIKE ?)";
  $params[]="%$q%"; $params[]="%$q%";
}

$st = $pdo->prepare("SELECT t.*, o.code os_code, c.name client_name
  FROM ar_titles t
  LEFT JOIN os o ON o.id=t.os_id
  LEFT JOIN clients c ON c.id=COALESCE(o.client_id, t.client_id)
  WHERE $where
  ORDER BY t.due_date ASC");
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 style="font-weight:900" class="m-0">A Receber</h5>
    <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#novoAR">+ Conta a receber manual</button>
  </div>
  <div class="collapse mb-3" id="novoAR">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="create_manual">
      <div class="col-md-4">
        <label class="form-label small">Cliente</label>
        <select class="form-select form-select-sm" name="client_id" required>
          <option value="">Selecione...</option>
          <?php foreach($clients as $c): ?>
            <option value="<?= h($c['id']) ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Valor</label>
        <input class="form-control form-control-sm" name="amount" required placeholder="0,00">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Venc.</label>
        <input class="form-control form-control-sm" type="date" name="due_date">
      </div>
      <div class="col-md-2">
        <label class="form-label small">Forma</label>
        <select class="form-select form-select-sm" name="method">
          <option value="pix">Pix</option>
          <option value="dinheiro">Dinheiro</option>
          <option value="cartao">Cartão</option>
          <option value="boleto">Boleto</option>
        </select>
      </div>
      <div class="col-md-12">
        <label class="form-label small">Descrição</label>
        <input class="form-control form-control-sm" name="description" required placeholder="Ex: serviço extra / taxa / etc">
      </div>
      <div class="col-md-12">
        <button class="btn btn-sm btn-primary">Salvar</button>
      </div>
    </form>
  </div>

  <form class="row g-2 align-items-center">
    <input type="hidden" name="page" value="fin_receber">
    <div class="col-md-6"><input class="form-control" name="q" placeholder="Buscar cliente / OS" value="<?= h($q) ?>"></div>
    <div class="col-md-3"><input class="form-control" name="os" placeholder="ID OS (opcional)" value="<?= h($os_filter) ?>"></div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>
</div>

<div class="card p-3">
  <div class="d-flex justify-content-between mb-2">
    <div style="font-weight:800">A Receber</div>
    <div class="muted">Baixa exige comprovante</div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Cliente</th><th>OS</th><th>Tipo</th><th>Venc.</th><th>Valor</th><th></th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= h($r['client_name']) ?></td>
            <td><?= h($r['os_code']) ?></td>
            <td><span class="badge text-bg-light"><?= h($r['kind']) ?></span></td>
            <td><?= h(date_br($r['due_date'])) ?></td>
            <td style="font-weight:800"><?= h(money($r['amount'])) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-primary" href="<?= h($base) ?>/app.php?page=fin_baixa&type=ar&id=<?= h($r['id']) ?>">Receber</a>
              <?php if(($_SESSION['user_role'] ?? '') === 'master'): ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('⚠️ Deseja realmente excluir esta conta a receber?\n\nValor: <?= h(money($r['amount'])) ?>\nCliente: <?= h($r['client_name']) ?>\nOS: <?= h($r['os_code']) ?>')">
                  <input type="hidden" name="action" value="delete_ar">
                  <input type="hidden" name="ar_id" value="<?= h($r['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-danger" title="Excluir (apenas master)">
                    🗑️
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
