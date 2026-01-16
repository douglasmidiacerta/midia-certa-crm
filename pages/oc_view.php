<?php
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT p.*, s.name supplier_name, o.code os_code
  FROM purchases p
  JOIN suppliers s ON s.id=p.supplier_id
  LEFT JOIN os o ON o.id=p.os_id
  WHERE p.id=?");
$st->execute([$id]);
$oc = $st->fetch();
if(!$oc){ echo "<h4>O.C não encontrada</h4>"; return; }

$ap = $pdo->prepare("SELECT * FROM ap_titles WHERE purchase_id=? ORDER BY id"); $ap->execute([$id]); $ap=$ap->fetchAll();
$open = 0;
foreach($ap as $t){ if($t['status']==='aberto') $open += (float)$t['amount']; }
?>
<div class="card p-3">
  <div class="d-flex justify-content-between">
    <div>
      <div style="font-weight:900;font-size:1.2rem"><?= h($oc['code']) ?> <span class="badge text-bg-light"><?= h($oc['oc_type']) ?></span></div>
      <div style="font-weight:700"><?= h($oc['supplier_name']) ?></div>
      <div class="muted"><?= $oc['os_code'] ? 'OS: '.h($oc['os_code']).' • ' : '' ?>Venc.: <?= $oc['due_date']?h(date_br($oc['due_date'])):'-' ?></div>
    </div>
    <div class="text-end">
      <div class="muted">Em aberto</div>
      <div style="font-weight:900"><?= h(money($open)) ?></div>
      <a class="btn btn-sm btn-outline-primary mt-2" href="<?= h($base) ?>/app.php?page=fin_pagar&oc=<?= h($id) ?>">Ir para pagar</a>
    </div>
  </div>

  <hr>
  <div style="font-weight:800" class="mb-2">Títulos a pagar</div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Valor</th><th>Venc.</th><th>Status</th><th>Comprovante</th></tr></thead>
      <tbody>
        <?php foreach($ap as $t): ?>
          <tr>
            <td><?= h(money($t['amount'])) ?></td>
            <td><?= h(date_br($t['due_date'])) ?></td>
            <td><span class="badge text-bg-light"><?= h($t['status']) ?></span></td>
            <td><?= $t['proof_file'] ? '<a target="_blank" href="'.h($base).'/uploads/'.h($t['proof_file']).'">ver</a>' : '-' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="mt-2">
    <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=oc">Voltar</a>
  </div>
</div>
