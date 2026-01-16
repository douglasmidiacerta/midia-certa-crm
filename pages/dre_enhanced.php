<?php
require_login();
require_role(['admin']);
require_once __DIR__ . '/../config/dre_service.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$dreService = new DreService($pdo);
$report = $dreService->getFullReport($from, $to);

$totalCosts = $report['product_costs'] + $report['operating_expenses'] + 
              $report['payroll_costs'] + $report['taxes'] + $report['marketing_costs'];
$grossProfit = $report['total_sales'] - $totalCosts;
?>

<div class="card p-3 mb-3">
  <h5 style="font-weight:900">DRE Completo</h5>
  <div class="text-muted small">Período: <?= h($from) ?> a <?= h($to) ?></div>
  <form class="row g-2 mt-2" method="get">
    <input type="hidden" name="page" value="dre_enhanced">
    <div class="col-md-3">
      <label class="form-label small">De</label>
      <input class="form-control" type="date" name="from" value="<?= h($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">Até</label>
      <input class="form-control" type="date" name="to" value="<?= h($to) ?>">
    </div>
    <div class="col-md-3 align-self-end">
      <button class="btn btn-primary">Filtrar</button>
    </div>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Total Vendas</div>
      <div style="font-weight:900;font-size:1.4rem;color:#059669"><?= h(money($report['total_sales'])) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Custos Produtos</div>
      <div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($report['product_costs'])) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Despesas Operacionais</div>
      <div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($report['operating_expenses'])) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Folha Pagamento</div>
      <div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($report['payroll_costs'])) ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Impostos</div>
      <div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($report['taxes'])) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Marketing</div>
      <div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($report['marketing_costs'])) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Total Custos</div>
      <div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($totalCosts)) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card p-3">
      <div class="text-muted small">Lucro Bruto</div>
      <div style="font-weight:900;font-size:1.4rem;<?= $grossProfit >= 0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($grossProfit)) ?></div>
    </div>
  </div>
</div>

<?php if (!empty($report['delivery_losses'])): ?>
<div class="card p-3 mb-3">
  <h6 style="font-weight:900">Prejuízos por OS</h6>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>OS</th>
          <th>Cliente</th>
          <th>Receita</th>
          <th>Custo</th>
          <th>Prejuízo</th>
          <th>Motivo</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['delivery_losses'] as $loss): ?>
        <tr>
          <td><?= h($loss['code']) ?></td>
          <td><?= h($loss['client_name']) ?></td>
          <td><?= h(money($loss['revenue'])) ?></td>
          <td><?= h(money($loss['cost'])) ?></td>
          <td style="font-weight:900;color:#dc2626"><?= h(money($loss['loss'])) ?></td>
          <td><?= h($loss['loss_reason'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card p-3 mb-3">
  <h6 style="font-weight:900">Fluxo de Caixa</h6>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>Conta</th>
          <th class="text-end">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['cash_accounts'] as $acc): ?>
        <tr>
          <td><?= h($acc['name']) ?></td>
          <td class="text-end" style="font-weight:900"><?= h(money($acc['balance'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th>Total em Caixa</th>
          <th class="text-end" style="font-weight:900"><?= h(money($report['total_cash_balance'])) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card p-3">
      <div class="text-muted small">Contas a Receber</div>
      <div style="font-weight:900;font-size:1.4rem;color:#059669"><?= h(money($report['accounts_receivable_total'])) ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <div class="text-muted small">Contas a Pagar</div>
      <div style="font-weight:900;font-size:1.4rem;color:#dc2626"><?= h(money($report['accounts_payable_total'])) ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <div class="text-muted small">Resultado Liquidação</div>
      <div style="font-weight:900;font-size:1.4rem;<?= $report['liquidation_result'] >= 0 ? 'color:#059669' : 'color:#dc2626' ?>"><?= h(money($report['liquidation_result'])) ?></div>
      <div class="text-muted small">A Receber + Caixa - A Pagar</div>
    </div>
  </div>
</div>
