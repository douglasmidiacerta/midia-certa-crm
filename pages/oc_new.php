<?php
$suppliers = $pdo->query("SELECT id,name FROM suppliers WHERE active=1 ORDER BY name")->fetchAll();
$os_list = $pdo->query("SELECT id,code FROM os WHERE os_type='produto' AND status<>'cancelada' ORDER BY created_at DESC LIMIT 200")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $oc_type = $_POST['oc_type'] ?? 'administrativa';
  $supplier_id = (int)($_POST['supplier_id'] ?? 0);
  $os_id = $_POST['os_id'] ? (int)$_POST['os_id'] : null;
  $due_date = $_POST['due_date'] ?: date('Y-m-d');
  $notes = trim($_POST['notes'] ?? '');
  $amount = (float)str_replace(',','.',($_POST['amount'] ?? 0));

  if(!$supplier_id){ flash_set('danger','Selecione um fornecedor.'); redirect($base.'/app.php?page=purchases'); }
  if($oc_type==='producao' && !$os_id){ flash_set('danger','O.C de produção precisa vincular uma O.S.'); redirect($base.'/app.php?page=purchases'); }

  $code = 'OC'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
  $st = $pdo->prepare("INSERT INTO purchases (code, oc_type, supplier_id, os_id, status, due_date, notes, created_at)
                       VALUES (?,?,?,?, 'aberta', ?, ?, ?)");
  $st->execute([$code,$oc_type,$supplier_id,$os_id,$due_date,$notes,now()]);
  $pid = (int)$pdo->lastInsertId();
  audit($pdo,'create','oc',$pid,['code'=>$code]);

  // cria conta a pagar
  $st = $pdo->prepare("INSERT INTO ap_titles (purchase_id, amount, due_date, status, created_at) VALUES (?,?,?, 'aberto', ?)");
  $st->execute([$pid,$amount,$due_date,now()]);

  flash_set('success','O.C criada.');
  redirect($base.'/app.php?page=purchases&view='.$pid);
}
?>
<div class="card p-3">
  <h5 style="font-weight:900">Nova Compra O.C (Ordem de Compra)</h5>
  <form method="post" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Tipo</label>
      <select class="form-select" name="oc_type">
        <option value="producao">Produção (vinculada à O.S)</option>
        <option value="administrativa">Administrativa</option>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label">Fornecedor</label>
      <select class="form-select" name="supplier_id" required>
        <option value="">Selecione...</option>
        <?php foreach($suppliers as $s): ?><option value="<?= h($s['id']) ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Vencimento</label>
      <input class="form-control" type="date" name="due_date" value="<?= h(date('Y-m-d')) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Vincular O.S (somente para produção)</label>
      <select class="form-select" name="os_id">
        <option value="">(opcional)</option>
        <?php foreach($os_list as $o): ?><option value="<?= h($o['id']) ?>"><?= h($o['code']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Valor (gera conta a pagar)</label>
      <input class="form-control" name="amount" value="0">
    </div>

    <div class="col-12">
      <label class="form-label">Observações</label>
      <textarea class="form-control" name="notes" rows="3"></textarea>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">Criar O.C</button>
      <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=oc">Voltar</a>
    </div>
  </form>
</div>
