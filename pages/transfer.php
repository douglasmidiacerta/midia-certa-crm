<?php
$accounts = $pdo->query("SELECT id,name FROM accounts WHERE active=1 ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $from = (int)($_POST['from'] ?? 0);
  $to = (int)($_POST['to'] ?? 0);
  $amount = (float)str_replace(',','.',($_POST['amount'] ?? 0));
  $notes = trim($_POST['notes'] ?? '');
  if(!$from || !$to || $from===$to){ flash_set('danger','Selecione contas diferentes.'); redirect($base.'/app.php?page=transfer'); }
  if($amount<=0){ flash_set('danger','Valor inválido.'); redirect($base.'/app.php?page=transfer'); }

  // criar duas movimentações
  $st = $pdo->prepare("INSERT INTO cash_movements (account_id, movement_type, amount, description, category, reference_type, reference_id, created_by_user_id, created_at)
                       VALUES (?,?,?,?, 'transfer', 'transfer', 0, ?, ?)");
  $desc = $notes ?: 'Transferência entre contas';
  $st->execute([$from, 'saida', $amount, $desc, user_id(), now()]);
  $st->execute([$to, 'entrada', $amount, $desc, user_id(), now()]);
  audit($pdo,'transfer','cash',0,['from'=>$from,'to'=>$to,'amount'=>$amount]);

  flash_set('success','Transferência registrada.');
  redirect($base.'/app.php?page=cash');
}
?>
<div class="card p-3">
  <h5 style="font-weight:900">Transferência entre contas</h5>
  <form method="post" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Origem</label>
      <select class="form-select" name="from" required>
        <option value="">Selecione...</option>
        <?php foreach($accounts as $a): ?><option value="<?= h($a['id']) ?>"><?= h($a['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Destino</label>
      <select class="form-select" name="to" required>
        <option value="">Selecione...</option>
        <?php foreach($accounts as $a): ?><option value="<?= h($a['id']) ?>"><?= h($a['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Valor</label>
      <input class="form-control" name="amount" value="0">
    </div>
    <div class="col-12">
      <label class="form-label">Observação</label>
      <textarea class="form-control" name="notes" rows="2"></textarea>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">Confirmar</button>
      <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=cash">Voltar</a>
    </div>
  </form>
</div>
