<?php
require_role(['admin','financeiro']);

$rows = $pdo->query("
  SELECT 
    p.id,
    p.created_at,
    s.name AS supplier_name,
    COALESCE(SUM(pl.qty * pl.unit_price), 0) AS total_amount
  FROM purchases p
  LEFT JOIN suppliers s ON s.id = p.supplier_id
  LEFT JOIN purchase_lines pl ON pl.purchase_id = p.id
  GROUP BY p.id
  ORDER BY p.id DESC 
  LIMIT 200
")->fetchAll();
?>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 style="font-weight:900">O.C (Ordens de Compra)</h5>
    <a class="btn btn-primary" href="<?= h($base) ?>/app.php?page=purchases">+ Nova compra</a>
  </div>

  <div class="text-muted small mt-1">
    Este menu concentra as compras (O.C). Na próxima etapa, vamos separar "Solicitações" x "Aprovação" do Admin.
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead><tr><th>#</th><th>Fornecedor</th><th>Total</th><th>Data</th><th></th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><b><?= h($r['id']) ?></b></td>
          <td><?= h($r['supplier_name'] ?? '-') ?></td>
          <td>R$ <?= number_format((float)($r['total_amount'] ?? 0),2,',','.') ?></td>
          <td class="text-muted small"><?= h(substr($r['created_at'],0,16)) ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="<?= h($base) ?>/app.php?page=purchases&view=<?= h($r['id']) ?>">Abrir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
