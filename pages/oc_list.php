<?php
$q = trim($_GET['q'] ?? '');
$where = "1=1";
$params = [];
if($q){
  $where .= " AND (p.code LIKE ? OR s.name LIKE ?)";
  $params = ["%$q%","%$q%"];
}
$st = $pdo->prepare("SELECT p.*, s.name supplier_name, o.code os_code
  FROM purchases p 
  JOIN suppliers s ON s.id=p.supplier_id
  LEFT JOIN os o ON o.id=p.os_id
  WHERE $where
  ORDER BY p.created_at DESC");
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="card p-3 mb-3">
  <form class="row g-2">
    <input type="hidden" name="page" value="oc_list">
    <div class="col-md-8">
      <input class="form-control" name="q" placeholder="Buscar O.C / Fornecedor" value="<?= h($q) ?>">
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-outline-primary">Filtrar</button>
      <a class="btn btn-primary" href="<?= h($base) ?>/app.php?page=purchases">+ Nova Compra</a>
    </div>
  </form>
</div>

<div class="row g-3">
  <?php foreach($rows as $r): 
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM ap_titles WHERE purchase_id=? AND status='aberto'");
    $st->execute([$r['id']]);
    $open = (float)$st->fetch()['s'];
  ?>
    <div class="col-md-6 col-lg-4">
      <div class="card p-3">
        <div class="d-flex justify-content-between">
          <div style="font-weight:900"><?= h($r['code']) ?></div>
          <span class="badge text-bg-light"><?= h($r['oc_type']) ?></span>
        </div>
        <div style="font-weight:700"><?= h($r['supplier_name']) ?></div>
        <div class="muted"><?= $r['os_code'] ? 'OS: '.h($r['os_code']).' • ' : '' ?>Venc.: <?= $r['due_date']?h(date_br($r['due_date'])):'-' ?></div>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <div class="muted">Em aberto: <b><?= h(money($open)) ?></b></div>
          <a class="btn btn-sm btn-outline-primary" href="<?= h($base) ?>/app.php?page=purchases&view=<?= h($r['id']) ?>">Abrir</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
