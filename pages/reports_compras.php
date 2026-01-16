<?php
require_role(['admin','financeiro']);
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$rows = $pdo->prepare("SELECT p.id,p.created_at,p.supplier_id,s.name supplier_name,p.total_amount
                       FROM purchases p
                       LEFT JOIN suppliers s ON s.id=p.supplier_id
                       WHERE DATE(p.created_at) BETWEEN ? AND ?
                       ORDER BY p.id DESC");
$rows->execute([$from,$to]);
$rows = $rows->fetchAll();
?>
<div class="card p-3">
  <h5 style="font-weight:900">Relatório de Compras</h5>
  <form class="row g-2 mt-2">
    <input type="hidden" name="page" value="reports_compras">
    <div class="col-md-3"><label class="form-label">De</label><input class="form-control" type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="col-md-3"><label class="form-label">Até</label><input class="form-control" type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="col-md-3 align-self-end"><button class="btn btn-outline-secondary w-100">Filtrar</button></div>
  </form>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead><tr><th>ID</th><th>Fornecedor</th><th>Total</th><th>Data</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>#<?= h($r['id']) ?></td>
            <td><?= h($r['supplier_name'] ?? '-') ?></td>
            <td>R$ <?= number_format((float)($r['total_amount'] ?? 0),2,',','.') ?></td>
            <td class="text-muted small"><?= h(substr($r['created_at'],0,16)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
