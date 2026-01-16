<?php require_login(); require_role(['admin','financeiro']); 
$ar_open = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM ar_titles WHERE status='aberto'")->fetch()['s'];
$ap_open = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) s FROM ap_titles WHERE status='aberto'")->fetch()['s'];
?>
<div class="card p-3">
  <h5 style="font-weight:900">Financeiro</h5>
  <div class="row g-2">
    <div class="col-md-6"><div class="border rounded p-2"><div class="text-muted small">A receber (aberto)</div><div style="font-size:28px;font-weight:900">R$ <?= number_format($ar_open,2,',','.') ?></div></div></div>
    <div class="col-md-6"><div class="border rounded p-2"><div class="text-muted small">A pagar (aberto)</div><div style="font-size:28px;font-weight:900">R$ <?= number_format($ap_open,2,',','.') ?></div></div></div>
  </div>
  <div class="text-muted small mt-2">As baixas de recebimento/pagamento acontecem dentro da O.S e das O.C (compras). Relatórios/DRE entram na próxima etapa.</div>
</div>