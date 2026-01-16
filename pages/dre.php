<?php
// DRE PROFISSIONAL - Apenas para CEO/MASTER
require_once __DIR__ . '/../config/dre_service.php';

// Verificação de acesso (comentada temporariamente para teste)
/*
$allowed_user_ids = [1, 2, 3];
$current_user_id = $_SESSION['user_id'] ?? 0;
if (!in_array($current_user_id, $allowed_user_ids)) {
  $_SESSION['flash'] = 'Acesso negado. Esta área é exclusiva para CEO/Master.';
  $_SESSION['flash_type'] = 'danger';
  header('Location: app.php?page=dashboard');
  exit;
}
*/

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Inicializa serviço de DRE
$dreService = new DreService($pdo);
$dreReport = $dreService->getFullReport($from, $to);

// Receita e custo por OS (período baseado em created_at da OS)
$st = $pdo->prepare("SELECT
  o.id,
  o.code,
  o.status,
  o.created_at,
  o.client_id,
  c.name AS client_name,
  o.seller_user_id,
  u.name AS seller_name,
  COALESCE(SUM(l.qty*l.unit_price),0) AS items_revenue,
  COALESCE(SUM(l.qty*l.unit_cost),0)  AS items_cost,
  o.delivery_fee,
  o.delivery_cost
FROM os o
JOIN clients c ON c.id=o.client_id
JOIN users u ON u.id=o.seller_user_id
LEFT JOIN os_lines l ON l.os_id=o.id
WHERE DATE(o.created_at) BETWEEN ? AND ?
  AND o.status <> 'cancelada'
GROUP BY o.id
ORDER BY o.id DESC");
$st->execute([$from,$to]);
$rows = $st->fetchAll();

$total_rev = 0.0;
$total_cost = 0.0;
$total_profit = 0.0;
$total_delivery_loss = 0.0;

// Análise por vendedor
$seller_stats = [];
// Análise por cliente
$client_stats = [];

foreach($rows as &$r){
  $rev = (float)$r['items_revenue'] + (float)$r['delivery_fee'];
  $cost = (float)$r['items_cost'] + (float)$r['delivery_cost'];
  $profit = $rev - $cost;
  $delivery_loss = max(0, (float)$r['delivery_cost'] - (float)$r['delivery_fee']);
  $r['rev'] = $rev;
  $r['cost'] = $cost;
  $r['profit'] = $profit;
  $r['delivery_loss'] = $delivery_loss;
  $total_rev += $rev;
  $total_cost += $cost;
  $total_profit += $profit;
  $total_delivery_loss += $delivery_loss;
  
  // Agregar por vendedor
  $seller_id = $r['seller_user_id'];
  if (!isset($seller_stats[$seller_id])) {
    $seller_stats[$seller_id] = [
      'name' => $r['seller_name'],
      'revenue' => 0,
      'cost' => 0,
      'profit' => 0,
      'os_count' => 0
    ];
  }
  $seller_stats[$seller_id]['revenue'] += $rev;
  $seller_stats[$seller_id]['cost'] += $cost;
  $seller_stats[$seller_id]['profit'] += $profit;
  $seller_stats[$seller_id]['os_count']++;
  
  // Agregar por cliente
  $client_id = $r['client_id'];
  if (!isset($client_stats[$client_id])) {
    $client_stats[$client_id] = [
      'name' => $r['client_name'],
      'revenue' => 0,
      'cost' => 0,
      'profit' => 0,
      'os_count' => 0
    ];
  }
  $client_stats[$client_id]['revenue'] += $rev;
  $client_stats[$client_id]['cost'] += $cost;
  $client_stats[$client_id]['profit'] += $profit;
  $client_stats[$client_id]['os_count']++;
}
unset($r);

// Ordenar vendedores por receita
uasort($seller_stats, function($a, $b) {
  return $b['revenue'] <=> $a['revenue'];
});

// Ordenar clientes por receita
uasort($client_stats, function($a, $b) {
  return $b['revenue'] <=> $a['revenue'];
});

// Despesas pagas no período (contas a pagar) - com detalhamento
$st2 = $pdo->prepare("SELECT 
  ap.id,
  ap.description,
  ap.amount,
  ap.paid_at,
  ap.category,
  s.name AS supplier_name,
  u.name AS paid_by_name
FROM ap_titles ap
LEFT JOIN suppliers s ON s.id = ap.supplier_id
LEFT JOIN users u ON u.id = ap.paid_by_user_id
WHERE ap.status='pago' AND DATE(ap.paid_at) BETWEEN ? AND ?
ORDER BY ap.paid_at DESC");
$st2->execute([$from,$to]);
$paid_ap_details = $st2->fetchAll();

$paid_ap = 0.0;
foreach($paid_ap_details as $ap) {
  $paid_ap += (float)$ap['amount'];
}

$dre_result = $total_profit - $paid_ap;
?>

<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h5 style="font-weight:900">DRE Resumido</h5>
      <div class="text-muted small">Período por data de criação da OS. Despesas = contas a pagar com status <b>pago</b> no período.</div>
    </div>
  </div>
  <form class="row g-2 mt-2" method="get">
    <input type="hidden" name="page" value="dre">
    <div class="col-md-3">
      <label class="form-label small text-muted">De</label>
      <input class="form-control" type="date" name="from" value="<?= h($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted">Até</label>
      <input class="form-control" type="date" name="to" value="<?= h($to) ?>">
    </div>
    <div class="col-md-3 align-self-end">
      <button class="btn btn-outline-primary">Filtrar</button>
    </div>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Total Vendas</div><div style="font-weight:900;font-size:1.4rem;color:#059669"><?= h(money($dreReport['total_sales'])) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Custos Produtos</div><div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($dreReport['product_costs'])) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Despesas Operacionais</div><div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($dreReport['operating_expenses'])) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Folha Pagamento</div><div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($dreReport['payroll_costs'])) ?></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Impostos</div><div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($dreReport['taxes'])) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Marketing</div><div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($dreReport['marketing_costs'])) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Saldo Total Caixa</div><div style="font-weight:900;font-size:1.4rem;color:#059669"><?= h(money($dreReport['total_cash_balance'])) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Resultado Liquidação</div><div style="font-weight:900;font-size:1.4rem;<?= $dreReport['liquidation_result']>=0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($dreReport['liquidation_result'])) ?></div><div class="text-muted small" style="font-size:0.7rem">A Receber + Caixa - A Pagar</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card p-3"><div class="text-muted small">Contas a Receber</div><div style="font-weight:900;font-size:1.4rem;color:#059669"><?= h(money($dreReport['accounts_receivable_total'])) ?></div></div></div>
  <div class="col-md-4"><div class="card p-3"><div class="text-muted small">Contas a Pagar</div><div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($dreReport['accounts_payable_total'])) ?></div></div></div>
  <div class="col-md-4"><div class="card p-3"><div class="text-muted small">Margem OS (legado)</div><div style="font-weight:900;font-size:1.4rem"><?= h(money($total_profit)) ?></div></div></div>
</div>

<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <div style="font-weight:900">Resultado (Margem - Despesas)</div>
    <div style="font-weight:900;font-size:1.6rem;<?= $dre_result>=0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($dre_result)) ?></div>
  </div>
  <div class="text-muted small">Prejuízo de entrega (quando custo &gt; cobrança): <b><?= h(money($total_delivery_loss)) ?></b></div>
</div>

<!-- Análise por Vendedor -->
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between mb-3">
    <div style="font-weight:900">Análise por Vendedor</div>
    <div class="text-muted small"><?= count($seller_stats) ?> vendedor(es) no período</div>
  </div>
  <?php if(empty($seller_stats)): ?>
    <div class="text-muted text-center py-3">Nenhum vendedor no período</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Vendedor</th>
            <th class="text-center">OS</th>
            <th class="text-end">Receita</th>
            <th class="text-end">Custo</th>
            <th class="text-end">Lucro</th>
            <th class="text-end">Margem %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($seller_stats as $seller): 
            $margin_pct = $seller['revenue'] > 0 ? ($seller['profit'] / $seller['revenue']) * 100 : 0;
          ?>
            <tr>
              <td style="font-weight:700"><?= h($seller['name']) ?></td>
              <td class="text-center"><span class="badge bg-secondary"><?= $seller['os_count'] ?></span></td>
              <td class="text-end" style="font-weight:800"><?= h(money($seller['revenue'])) ?></td>
              <td class="text-end" style="font-weight:800"><?= h(money($seller['cost'])) ?></td>
              <td class="text-end" style="font-weight:900;<?= $seller['profit']>=0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($seller['profit'])) ?></td>
              <td class="text-end" style="font-weight:700;<?= $margin_pct>=30 ? 'color:#059669' : ($margin_pct>=15 ? 'color:#f59e0b' : 'color:#dc2626') ?>"><?= number_format($margin_pct, 1) ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Análise por Cliente (Top 10) -->
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between mb-3">
    <div style="font-weight:900">Top 10 Clientes</div>
    <div class="text-muted small"><?= count($client_stats) ?> cliente(s) no período</div>
  </div>
  <?php if(empty($client_stats)): ?>
    <div class="text-muted text-center py-3">Nenhum cliente no período</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Cliente</th>
            <th class="text-center">OS</th>
            <th class="text-end">Receita</th>
            <th class="text-end">Custo</th>
            <th class="text-end">Lucro</th>
            <th class="text-end">Margem %</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $count = 0;
          foreach($client_stats as $client): 
            if($count >= 10) break;
            $margin_pct = $client['revenue'] > 0 ? ($client['profit'] / $client['revenue']) * 100 : 0;
            $count++;
          ?>
            <tr>
              <td style="font-weight:700"><?= h($client['name']) ?></td>
              <td class="text-center"><span class="badge bg-secondary"><?= $client['os_count'] ?></span></td>
              <td class="text-end" style="font-weight:800"><?= h(money($client['revenue'])) ?></td>
              <td class="text-end" style="font-weight:800"><?= h(money($client['cost'])) ?></td>
              <td class="text-end" style="font-weight:900;<?= $client['profit']>=0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($client['profit'])) ?></td>
              <td class="text-end" style="font-weight:700;<?= $margin_pct>=30 ? 'color:#059669' : ($margin_pct>=15 ? 'color:#f59e0b' : 'color:#dc2626') ?>"><?= number_format($margin_pct, 1) ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Despesas por Categoria -->
<?php
$st_cat = $pdo->prepare("SELECT 
  ap.category,
  COALESCE(SUM(ap.amount), 0) AS total
FROM ap_titles ap
WHERE ap.status = 'pago'
  AND DATE(ap.paid_at) BETWEEN ? AND ?
  AND ap.category IS NOT NULL
GROUP BY ap.category
ORDER BY total DESC");
$st_cat->execute([$from, $to]);
$expenses_by_category = $st_cat->fetchAll();

$category_labels = [
  'product_costs' => 'Custos de Produtos',
  'operating_expenses' => 'Despesas Operacionais',
  'payroll' => 'Folha de Pagamento',
  'taxes' => 'Impostos',
  'marketing' => 'Marketing'
];
?>

<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between mb-3">
    <div style="font-weight:900">Despesas por Categoria</div>
    <div class="text-muted small"><?= count($expenses_by_category) ?> categoria(s)</div>
  </div>
  <?php if(empty($expenses_by_category)): ?>
    <div class="text-muted text-center py-3">Nenhuma despesa categorizada no período</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Categoria</th>
            <th class="text-end">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $total_cat = 0;
          foreach($expenses_by_category as $cat): 
            $total_cat += $cat['total'];
          ?>
            <tr>
              <td style="font-weight:600"><?= h($category_labels[$cat['category']] ?? $cat['category']) ?></td>
              <td class="text-end" style="font-weight:800;color:#dc2626"><?= h(money($cat['total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th>Total Categorizado:</th>
            <th class="text-end" style="font-weight:900;color:#dc2626"><?= h(money($total_cat)) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Prejuízos por OS -->
<?php if(!empty($dreReport['delivery_losses'])): ?>
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between mb-3">
    <div style="font-weight:900">Prejuízos por OS</div>
    <div class="text-muted small"><?= count($dreReport['delivery_losses']) ?> OS com prejuízo</div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>OS</th>
          <th>Cliente</th>
          <th class="text-end">Receita</th>
          <th class="text-end">Custo</th>
          <th class="text-end">Prejuízo</th>
          <th>Motivo</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $total_losses = 0;
        foreach($dreReport['delivery_losses'] as $loss): 
          $total_losses += $loss['loss'];
        ?>
          <tr>
            <td><a href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($loss['id']) ?>"><?= h($loss['code']) ?></a></td>
            <td><?= h($loss['client_name']) ?></td>
            <td class="text-end" style="font-weight:800"><?= h(money($loss['revenue'])) ?></td>
            <td class="text-end" style="font-weight:800"><?= h(money($loss['cost'])) ?></td>
            <td class="text-end" style="font-weight:900;color:#dc2626"><?= h(money($loss['loss'])) ?></td>
            <td><?= h($loss['loss_reason'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="text-end">Total Prejuízos:</th>
          <th class="text-end" style="font-weight:900;color:#dc2626"><?= h(money($total_losses)) ?></th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Saldos por Caixa -->
<?php if(!empty($dreReport['cash_accounts'])): ?>
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between mb-3">
    <div style="font-weight:900">Saldos por Caixa</div>
    <div class="text-muted small"><?= count($dreReport['cash_accounts']) ?> conta(s)</div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Conta</th>
          <th class="text-end">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($dreReport['cash_accounts'] as $acc): ?>
          <tr>
            <td style="font-weight:600"><?= h($acc['name']) ?></td>
            <td class="text-end" style="font-weight:800;<?= $acc['balance']>=0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($acc['balance'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th>Total em Caixa:</th>
          <th class="text-end" style="font-weight:900;<?= $dreReport['total_cash_balance']>=0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($dreReport['total_cash_balance'])) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Detalhamento de Despesas (todas) -->
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between mb-3">
    <div style="font-weight:900">Todas Despesas Pagas no Período</div>
    <div class="text-muted small"><?= count($paid_ap_details) ?> despesa(s)</div>
  </div>
  <?php if(empty($paid_ap_details)): ?>
    <div class="text-muted text-center py-3">Nenhuma despesa paga no período</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Descrição</th>
            <th>Categoria</th>
            <th>Fornecedor</th>
            <th>Pago por</th>
            <th>Data Pagamento</th>
            <th class="text-end">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($paid_ap_details as $ap): ?>
            <tr>
              <td style="font-weight:600"><?= h($ap['description']) ?></td>
              <td><span class="badge bg-light text-dark"><?= h($category_labels[$ap['category'] ?? ''] ?? ($ap['category'] ?? 'sem categoria')) ?></span></td>
              <td><?= h($ap['supplier_name'] ?? '-') ?></td>
              <td><?= h($ap['paid_by_name'] ?? '-') ?></td>
              <td><?= h(date_br($ap['paid_at'])) ?></td>
              <td class="text-end" style="font-weight:800;color:#dc2626"><?= h(money($ap['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="5" class="text-end">Total de Despesas:</th>
            <th class="text-end" style="font-weight:900;color:#dc2626"><?= h(money($paid_ap)) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Gráficos Visuais -->
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card p-3">
      <div style="font-weight:900" class="mb-3">Distribuição de Receita por Vendedor</div>
      <canvas id="sellerRevenueChart" style="max-height:300px"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <div style="font-weight:900" class="mb-3">Receita vs Despesas</div>
      <canvas id="revenueExpensesChart" style="max-height:300px"></canvas>
    </div>
  </div>
</div>

<div class="card p-3">
  <div class="d-flex justify-content-between mb-2">
    <div style="font-weight:900">OS no período</div>
    <div class="text-muted small">Receita = itens + taxa entrega | Custo = custo itens + custo entrega</div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>OS</th>
          <th>Cliente</th>
          <th>Vendedor</th>
          <th>Status</th>
          <th>Receita</th>
          <th>Custo</th>
          <th>Lucro</th>
          <th>Prejuízo entrega</th>
          <th>Data</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><a href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($r['id']) ?>"><?= h($r['code']) ?></a></td>
            <td><?= h($r['client_name']) ?></td>
            <td><?= h($r['seller_name']) ?></td>
            <td><span class="badge text-bg-light"><?= h($r['status']) ?></span></td>
            <td style="font-weight:800"><?= h(money($r['rev'])) ?></td>
            <td style="font-weight:800"><?= h(money($r['cost'])) ?></td>
            <td style="font-weight:900;<?= $r['profit']>=0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($r['profit'])) ?></td>
            <td><?= $r['delivery_loss']>0 ? '<span style="color:#dc2626;font-weight:900">'.h(money($r['delivery_loss'])).'</span>' : '-' ?></td>
            <td><?= h(date_br($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Receita por Vendedor
<?php if(!empty($seller_stats)): ?>
const sellerData = {
  labels: <?= json_encode(array_map(function($s) { return $s['name']; }, array_values($seller_stats))) ?>,
  datasets: [{
    label: 'Receita',
    data: <?= json_encode(array_map(function($s) { return $s['revenue']; }, array_values($seller_stats))) ?>,
    backgroundColor: [
      'rgba(59, 130, 246, 0.7)',
      'rgba(16, 185, 129, 0.7)',
      'rgba(245, 158, 11, 0.7)',
      'rgba(239, 68, 68, 0.7)',
      'rgba(139, 92, 246, 0.7)',
      'rgba(236, 72, 153, 0.7)',
    ],
    borderWidth: 0
  }]
};

new Chart(document.getElementById('sellerRevenueChart'), {
  type: 'doughnut',
  data: sellerData,
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: {
        position: 'bottom',
      }
    }
  }
});
<?php endif; ?>

// Gráfico de Receita vs Despesas
const financeData = {
  labels: ['Receita', 'Custos OS', 'Despesas Pagas'],
  datasets: [{
    label: 'Valores',
    data: [<?= $total_rev ?>, <?= $total_cost ?>, <?= $paid_ap ?>],
    backgroundColor: [
      'rgba(16, 185, 129, 0.7)',
      'rgba(245, 158, 11, 0.7)',
      'rgba(239, 68, 68, 0.7)',
    ],
    borderWidth: 0
  }]
};

new Chart(document.getElementById('revenueExpensesChart'), {
  type: 'bar',
  data: financeData,
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: {
        display: false
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: function(value) {
            return 'R$ ' + value.toLocaleString('pt-BR');
          }
        }
      }
    }
  }
});
</script>
