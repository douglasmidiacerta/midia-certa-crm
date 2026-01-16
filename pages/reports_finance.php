<?php
require_role(['admin','financeiro']);
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$ar = $pdo->prepare("SELECT kind, status, SUM(amount) total FROM ar_titles WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY kind,status ORDER BY kind,status");
$ar->execute([$from,$to]);
$ar = $ar->fetchAll();

$ap = $pdo->prepare("SELECT status, SUM(amount) total FROM ap_titles WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status ORDER BY status");
$ap->execute([$from,$to]);
$ap = $ap->fetchAll();
?>
<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 style="font-weight:900">Relatório - Financeiro</h5>
  </div>

  <form class="row g-2 mt-2">
    <input type="hidden" name="page" value="reports_finance">
    <div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="col-md-3"><button class="btn btn-outline-secondary w-100">Aplicar</button></div>
  </form>

  <div class="row mt-3 g-3">
    <div class="col-md-6">
      <h6 style="font-weight:900">A Receber (títulos)</h6>
      <table class="table table-sm">
        <thead><tr><th>Tipo</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($ar as $r): ?>
            <tr><td><?= h($r['kind']) ?></td><td><?= h($r['status']) ?></td><td>R$ <?= number_format((float)$r['total'],2,',','.') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="col-md-6">
      <h6 style="font-weight:900">A Pagar (títulos)</h6>
      <table class="table table-sm">
        <thead><tr><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($ap as $r): ?>
            <tr><td><?= h($r['status']) ?></td><td>R$ <?= number_format((float)$r['total'],2,',','.') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
