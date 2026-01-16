<?php
require_role(['admin','vendas','financeiro']);
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$params = [$from,$to];
$sql = "SELECT o.id,o.code,COALESCE(o.doc_kind,'sale') doc_kind,o.status,o.created_at,c.name client_name,u.name seller_name
        FROM os o
        JOIN clients c ON c.id=o.client_id
        JOIN users u ON u.id=o.seller_user_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?";

if(can_sales() && !can_admin() && !can_finance()){
  $sql .= " AND o.seller_user_id = ?";
  $params[] = user_id();
}
$sql .= " ORDER BY o.id DESC LIMIT 500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// CSV
if(isset($_GET['csv'])){
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="relatorio_vendas.csv"');
  $out = fopen('php://output','w');
  fputcsv($out,['codigo','tipo','status','cliente','vendedor','data']);
  foreach($rows as $r){
    fputcsv($out,[$r['code'],$r['doc_kind'],$r['status'],$r['client_name'],$r['seller_name'],$r['created_at']]);
  }
  fclose($out);
  exit;
}
?>
<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 style="font-weight:900">Relatório - Vendas/O.S</h5>
    <a class="btn btn-outline-secondary btn-sm" href="<?= h($base) ?>/app.php?page=reports_sales&from=<?= h($from) ?>&to=<?= h($to) ?>&csv=1">Exportar CSV</a>
  </div>

  <form class="row g-2 mt-2">
    <input type="hidden" name="page" value="reports_sales">
    <div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Filtrar</button></div>
  </form>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead><tr><th>Código</th><th>Tipo</th><th>Status</th><th>Cliente</th><th>Vendedor</th><th>Data</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><a href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($r['id']) ?>"><b>#<?= h($r['code']) ?></b></a></td>
          <td><?= $r['doc_kind']==='budget' ? '<span class="badge bg-warning text-dark">ORÇAMENTO</span>' : '<span class="badge bg-success">VENDA</span>' ?></td>
          <td><span class="badge bg-primary"><?= strtoupper(h($r['status'])) ?></span></td>
          <td><?= h($r['client_name']) ?></td>
          <td><?= h($r['seller_name']) ?></td>
          <td class="text-muted small"><?= h(substr($r['created_at'],0,16)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
