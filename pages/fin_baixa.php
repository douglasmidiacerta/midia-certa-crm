<?php
$type = $_GET['type'] ?? 'ar';
$id = (int)($_GET['id'] ?? 0);

$accounts = $pdo->query("SELECT id,name FROM accounts WHERE active=1 ORDER BY name")->fetchAll();

if($type==='ar'){
  $st = $pdo->prepare("SELECT t.*, o.code os_code, c.name client_name FROM ar_titles t JOIN os o ON o.id=t.os_id JOIN clients c ON c.id=o.client_id WHERE t.id=?");
  $st->execute([$id]); $t = $st->fetch();
  if(!$t){ echo "<h4>Título não encontrado</h4>"; return; }
  $title = "Receber - {$t['client_name']} ({$t['os_code']})";
  $btn = "Confirmar recebimento";
} else {
  $st = $pdo->prepare("SELECT t.*, p.code oc_code, s.name supplier_name FROM ap_titles t JOIN purchases p ON p.id=t.purchase_id JOIN suppliers s ON s.id=p.supplier_id WHERE t.id=?");
  $st->execute([$id]); $t = $st->fetch();
  if(!$t){ echo "<h4>Título não encontrado</h4>"; return; }
  $title = "Pagar - {$t['supplier_name']} ({$t['oc_code']})";
  $btn = "Confirmar pagamento";
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $account_id = (int)($_POST['account_id'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');
  [$ok,$msg] = require_file('proof',$config);
  if(!$ok){ flash_set('danger',$msg); redirect($base."/app.php?page=fin_baixa&type=$type&id=$id"); }
  if(!$account_id){ flash_set('danger','Selecione a conta.'); redirect($base."/app.php?page=fin_baixa&type=$type&id=$id"); }

  $fname = save_upload('proof',$config);

  if($type==='ar'){
    $st = $pdo->prepare("UPDATE ar_titles SET status='recebido', received_at=?, account_id=?, proof_file=?, notes=? WHERE id=?");
    $st->execute([now(),$account_id,$fname,$notes,$id]);
    // caixa entrada
    $st = $pdo->prepare("INSERT INTO cash_movements (account_id, movement_type, amount, description, category, reference_type, reference_id, created_by_user_id, created_at)
                         VALUES (?, 'entrada', ?, ?, ?, 'ar_title', ?, ?, ?)");
    $st->execute([$account_id, $t['amount'], $title, null, $id, user_id(), now()]);
    audit($pdo,'receive','ar',$id,['file'=>$fname,'account'=>$account_id]);
    flash_set('success','Recebimento registrado.');
    redirect($base.'/app.php?page=fin_receber');
  } else {
    $st = $pdo->prepare("UPDATE ap_titles SET status='pago', paid_at=?, account_id=?, proof_file=?, notes=? WHERE id=?");
    $st->execute([now(),$account_id,$fname,$notes,$id]);
    // caixa saída
    $st = $pdo->prepare("INSERT INTO cash_movements (account_id, movement_type, amount, description, category, reference_type, reference_id, created_by_user_id, created_at)
                         VALUES (?, 'saida', ?, ?, ?, 'ap_title', ?, ?, ?)");
    $st->execute([$account_id, $t['amount'], $title, null, $id, user_id(), now()]);
    audit($pdo,'pay','ap',$id,['file'=>$fname,'account'=>$account_id]);
    flash_set('success','Pagamento registrado.');
    redirect($base.'/app.php?page=fin_pagar');
  }
}

?>
<div class="card p-3">
  <h5 style="font-weight:900"><?= h($title) ?></h5>
  <div class="muted mb-3">Valor: <b><?= h(money($t['amount'])) ?></b> • Vencimento: <?= h(date_br($t['due_date'])) ?> • Tipo: <?= h($type==='ar'?$t['kind']:'') ?></div>

  <form method="post" enctype="multipart/form-data" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Conta (onde entra/sai)</label>
      <select class="form-select" name="account_id" required>
        <option value="">Selecione...</option>
        <?php foreach($accounts as $a): ?>
          <option value="<?= h($a['id']) ?>"><?= h($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Comprovante <span class="text-danger">*</span></label>
      <input class="form-control" type="file" name="proof" required>
      <div class="muted mt-1">Obrigatório para baixar.</div>
    </div>
    <div class="col-12">
      <label class="form-label">Observação (opcional)</label>
      <textarea class="form-control" name="notes" rows="2"></textarea>
    </div>
    <div class="col-12">
      <button class="btn btn-primary"><?= h($btn) ?></button>
      <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=<?= h($type==='ar'?'fin_receber':'fin_pagar') ?>">Voltar</a>
    </div>
  </form>
</div>
