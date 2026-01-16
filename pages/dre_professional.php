<?php
// DRE PROFISSIONAL - Análise Completa de Resultados
require_login();
require_role(['admin']);
require_once __DIR__ . '/../config/dre_service.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$supplier_id = (int)($_GET['supplier_id'] ?? 0);
$employee_id = (int)($_GET['employee_id'] ?? 0);

// Busca fornecedores e funcionários para os filtros
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE active=1 ORDER BY name")->fetchAll();
$employees = $pdo->query("SELECT id, name FROM users WHERE active=1 ORDER BY name")->fetchAll();

$dreService = new DreService($pdo);
$report = $dreService->getFullReport($from, $to);

// Cálculos principais
$totalCosts = $report['product_costs'] + $report['operating_expenses'] + 
              $report['payroll_costs'] + $report['taxes'] + $report['marketing_costs'];
$grossProfit = $report['total_sales'] - $totalCosts;
$profitMargin = $report['total_sales'] > 0 ? ($grossProfit / $report['total_sales']) * 100 : 0;
?>

<style>
.dre-card {
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}
.dre-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.metric-value {
  font-weight: 900;
  font-size: 1.8rem;
  line-height: 1.2;
}
.metric-label {
  font-size: 0.85rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}
.section-title {
  font-weight: 900;
  font-size: 1.3rem;
  margin-bottom: 1rem;
  color: #1f2937;
}
.positive { color: #059669; }
.negative { color: #dc2626; }
.warning { color: #f59e0b; }
</style>

<!-- Header com Filtro de Período -->
<div class="card dre-card p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="section-title mb-1">📊 DRE Completo</h4>
      <p class="text-muted small mb-0">Demonstração do Resultado do Exercício - Análise Completa</p>
    </div>
    <div class="btn-group">
      <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-download"></i> Exportar
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="?page=dre_export&format=csv&from=<?= h($from) ?>&to=<?= h($to) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> CSV</a></li>
        <li><a class="dropdown-item" href="?page=dre_export&format=xls&from=<?= h($from) ?>&to=<?= h($to) ?>"><i class="bi bi-file-earmark-excel"></i> Excel (XLS)</a></li>
        <li><a class="dropdown-item" href="#" onclick="window.print(); return false;"><i class="bi bi-printer"></i> Imprimir/PDF</a></li>
      </ul>
    </div>
  </div>
  
  <form class="row g-3" method="get">
    <input type="hidden" name="page" value="dre_professional">
    <div class="col-md-3">
      <label class="form-label metric-label">Data Inicial</label>
      <input class="form-control form-control-lg" type="date" name="from" value="<?= h($from) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label metric-label">Data Final</label>
      <input class="form-control form-control-lg" type="date" name="to" value="<?= h($to) ?>" required>
    </div>
    <div class="col-md-2">
      <label class="form-label metric-label">Fornecedor</label>
      <select class="form-select form-select-lg" name="supplier_id">
        <option value="">Todos</option>
        <?php foreach($suppliers as $s): ?>
          <option value="<?= h($s['id']) ?>" <?= $supplier_id==$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label metric-label">Funcionário</label>
      <select class="form-select form-select-lg" name="employee_id">
        <option value="">Todos</option>
        <?php foreach($employees as $e): ?>
          <option value="<?= h($e['id']) ?>" <?= $employee_id==$e['id']?'selected':'' ?>><?= h($e['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 align-self-end">
      <button class="btn btn-primary btn-lg w-100" type="submit">
        <i class="bi bi-funnel"></i> Filtrar
      </button>
    </div>
  </form>
  
  <?php if($supplier_id || $employee_id): ?>
    <div class="alert alert-warning mt-3 mb-0 small">
      <strong>🔍 Filtros ativos:</strong>
      <?php if($supplier_id): ?>
        <?php $supplier_name = $pdo->query("SELECT name FROM suppliers WHERE id=$supplier_id")->fetchColumn(); ?>
        Fornecedor: <strong><?= h($supplier_name) ?></strong>
      <?php endif; ?>
      <?php if($employee_id): ?>
        <?php $employee_name = $pdo->query("SELECT name FROM users WHERE id=$employee_id")->fetchColumn(); ?>
        Funcionário: <strong><?= h($employee_name) ?></strong>
      <?php endif; ?>
      <a href="?page=dre_professional&from=<?= h($from) ?>&to=<?= h($to) ?>" class="btn btn-sm btn-outline-secondary ms-2">Limpar filtros</a>
    </div>
  <?php endif; ?>
  
  <div class="alert alert-info mt-3 mb-0 small">
    <strong>📅 Período analisado:</strong> <?= date('d/m/Y', strtotime($from)) ?> até <?= date('d/m/Y', strtotime($to)) ?>
    (<?= (new DateTime($from))->diff(new DateTime($to))->days + 1 ?> dias)
  </div>
</div>

<!-- RESUMO EXECUTIVO -->
<div class="card dre-card p-4 mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
  <h5 class="mb-4" style="font-weight: 900; color: white;">💰 RESUMO EXECUTIVO</h5>
  <div class="row g-4">
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Faturamento Total</div>
      <div class="metric-value"><?= h(money($report['total_sales'])) ?></div>
    </div>
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Custos Totais</div>
      <div class="metric-value"><?= h(money($totalCosts)) ?></div>
    </div>
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Lucro Líquido</div>
      <div class="metric-value <?= $grossProfit >= 0 ? '' : 'text-danger' ?>"><?= h(money($grossProfit)) ?></div>
    </div>
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Margem de Lucro</div>
      <div class="metric-value <?= $profitMargin >= 20 ? '' : ($profitMargin >= 10 ? 'warning' : 'text-danger') ?>">
        <?= number_format($profitMargin, 1) ?>%
      </div>
    </div>
  </div>
</div>

<!-- RECEITAS -->
<div class="card dre-card p-4 mb-4">
  <h5 class="section-title">📈 RECEITAS</h5>
  <div class="row g-3">
    <div class="col-md-12">
      <div class="d-flex justify-content-between align-items-center p-3" style="background: #f0fdf4; border-radius: 6px;">
        <div>
          <div class="metric-label">Total de Vendas (Faturamento Bruto)</div>
          <div class="text-muted small">Receita bruta de todas as OS no período</div>
        </div>
        <div class="metric-value positive"><?= h(money($report['total_sales'])) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- CUSTOS E DESPESAS -->
<div class="card dre-card p-4 mb-4">
  <h5 class="section-title">💸 CUSTOS E DESPESAS</h5>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card p-3 h-100" style="border-left: 4px solid #dc2626;">
        <div class="metric-label">Custos de Produtos</div>
        <div class="metric-value negative"><?= h(money($report['product_costs'])) ?></div>
        <small class="text-muted">Compras de fornecedores</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 h-100" style="border-left: 4px solid #ea580c;">
        <div class="metric-label">Despesas Operacionais</div>
        <div class="metric-value negative"><?= h(money($report['operating_expenses'])) ?></div>
        <small class="text-muted">Aluguel, material de escritório, etc</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 h-100" style="border-left: 4px solid #ca8a04;">
        <div class="metric-label">Folha de Pagamento</div>
        <div class="metric-value negative"><?= h(money($report['payroll_costs'])) ?></div>
        <small class="text-muted">Salários + benefícios</small>
      </div>
    </div>
  </div>
  
  <div class="row g-3 mt-1">
    <div class="col-md-4">
      <div class="card p-3 h-100" style="border-left: 4px solid #7c3aed;">
        <div class="metric-label">Impostos e Tributos</div>
        <div class="metric-value negative"><?= h(money($report['taxes'])) ?></div>
        <small class="text-muted">Obrigações fiscais</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 h-100" style="border-left: 4px solid #0891b2;">
        <div class="metric-label">Marketing</div>
        <div class="metric-value negative"><?= h(money($report['marketing_costs'])) ?></div>
        <small class="text-muted">Investimento em divulgação</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 h-100" style="border-left: 4px solid #000;">
        <div class="metric-label">Total de Custos</div>
        <div class="metric-value negative"><strong><?= h(money($totalCosts)) ?></strong></div>
        <small class="text-muted">Soma de todos os custos</small>
      </div>
    </div>
  </div>
</div>

<!-- RESULTADO OPERACIONAL -->
<div class="card dre-card p-4 mb-4" style="border: 3px solid <?= $grossProfit >= 0 ? '#059669' : '#dc2626' ?>;">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h5 class="section-title mb-1">🎯 RESULTADO OPERACIONAL</h5>
      <p class="text-muted small mb-0">Lucro = Receitas - Custos</p>
    </div>
    <div class="text-end">
      <div class="metric-value <?= $grossProfit >= 0 ? 'positive' : 'negative' ?>">
        <?= h(money($grossProfit)) ?>
      </div>
      <div class="<?= $profitMargin >= 20 ? 'positive' : ($profitMargin >= 10 ? 'warning' : 'negative') ?>" style="font-weight: 700; font-size: 1.2rem;">
        <?= number_format($profitMargin, 1) ?>% de margem
      </div>
    </div>
  </div>
</div>

<!-- DETALHAMENTO POR FORNECEDOR -->
<?php 
// Filtra por fornecedor se especificado
$supplier_summary = $report['supplier_summary'];
if($supplier_id > 0){
  $supplier_summary = array_filter($supplier_summary, function($s) use ($supplier_id) {
    return $s['supplier_id'] == $supplier_id;
  });
}
?>
<?php if (!empty($supplier_summary)): ?>
<div class="card dre-card p-4 mb-4">
  <h5 class="section-title">🏭 COMPRAS POR FORNECEDOR</h5>
  <p class="text-muted small mb-3">Quanto você comprou de cada fornecedor no período<?= $supplier_id ? ' (filtrado)' : '' ?></p>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Fornecedor</th>
          <th class="text-center">Nº Pagamentos</th>
          <th class="text-end">Total Pago</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $totalSuppliers = 0;
        foreach ($supplier_summary as $supplier): 
          $totalSuppliers += $supplier['total_paid'];
        ?>
        <tr>
          <td style="font-weight: 700;"><?= h($supplier['supplier_name']) ?></td>
          <td class="text-center">
            <span class="badge bg-secondary"><?= h($supplier['payment_count']) ?></span>
          </td>
          <td class="text-end" style="font-weight: 800; color: #dc2626;">
            <?= h(money($supplier['total_paid'])) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot style="background: #f9fafb; font-weight: 900;">
        <tr>
          <th>TOTAL FORNECEDORES</th>
          <th></th>
          <th class="text-end" style="color: #dc2626;"><?= h(money($totalSuppliers)) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- DETALHAMENTO POR COLABORADOR -->
<?php 
// Filtra por funcionário se especificado
$payroll_by_employee = $report['payroll_by_employee'];
if($employee_id > 0){
  $payroll_by_employee = array_filter($payroll_by_employee, function($e) use ($employee_id) {
    return $e['user_id'] == $employee_id;
  });
}
?>
<?php if (!empty($payroll_by_employee)): ?>
<div class="card dre-card p-4 mb-4">
  <h5 class="section-title">👥 CUSTO POR COLABORADOR</h5>
  <p class="text-muted small mb-3">Detalhamento individual de salários e benefícios (proporcional ao período)<?= $employee_id ? ' (filtrado)' : '' ?></p>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Colaborador</th>
          <th>Cargo</th>
          <th class="text-end">Salário</th>
          <th class="text-end">VR</th>
          <th class="text-end">VA</th>
          <th class="text-end">VT</th>
          <th class="text-end">Outros</th>
          <th class="text-end">Total/Mês</th>
          <th class="text-end">Custo Período</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $totalPeriodCost = 0;
        foreach ($payroll_by_employee as $emp): 
          $totalPeriodCost += $emp['period_cost'];
        ?>
        <tr>
          <td style="font-weight: 700;"><?= h($emp['name']) ?></td>
          <td class="text-muted"><?= h($emp['job_title'] ?? '-') ?></td>
          <td class="text-end"><?= h(money($emp['salary'])) ?></td>
          <td class="text-end"><?= h(money($emp['vr_monthly'])) ?></td>
          <td class="text-end"><?= h(money($emp['va_monthly'])) ?></td>
          <td class="text-end"><?= h(money($emp['vt_monthly'])) ?></td>
          <td class="text-end"><?= h(money($emp['other_benefits'])) ?></td>
          <td class="text-end" style="font-weight: 700;"><?= h(money($emp['monthly_total'])) ?></td>
          <td class="text-end" style="font-weight: 900; color: #dc2626;"><?= h(money($emp['period_cost'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot style="background: #f9fafb; font-weight: 900;">
        <tr>
          <th colspan="8">TOTAL FOLHA DE PAGAMENTO (PERÍODO)</th>
          <th class="text-end" style="color: #dc2626;"><?= h(money($totalPeriodCost)) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- PREJUÍZOS POR OS -->
<?php if (!empty($report['delivery_losses'])): ?>
<div class="card dre-card p-4 mb-4" style="border-left: 4px solid #dc2626;">
  <h5 class="section-title">⚠️ PREJUÍZOS POR ORDEM DE SERVIÇO</h5>
  <p class="text-muted small mb-3">OS onde o custo foi maior que a receita</p>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #fef2f2;">
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
        $totalLosses = 0;
        foreach ($report['delivery_losses'] as $loss): 
          $totalLosses += $loss['loss'];
        ?>
        <tr>
          <td style="font-weight: 700;"><?= h($loss['code']) ?></td>
          <td><?= h($loss['client_name']) ?></td>
          <td class="text-end"><?= h(money($loss['revenue'])) ?></td>
          <td class="text-end"><?= h(money($loss['cost'])) ?></td>
          <td class="text-end" style="font-weight: 900; color: #dc2626;"><?= h(money($loss['loss'])) ?></td>
          <td class="text-muted"><?= h($loss['loss_reason'] ?? 'Não informado') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot style="background: #fef2f2; font-weight: 900;">
        <tr>
          <th colspan="4">TOTAL PREJUÍZOS</th>
          <th class="text-end" style="color: #dc2626;"><?= h(money($totalLosses)) ?></th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="alert alert-warning mt-3 mb-0 small">
    <strong>💡 Dica:</strong> Analise os motivos dos prejuízos para evitar repetição. Ajuste preços ou revise custos operacionais.
  </div>
</div>
<?php endif; ?>

<!-- FLUXO DE CAIXA E LIQUIDAÇÃO -->
<div class="row g-3 mb-4">
  <div class="col-md-12">
    <div class="card dre-card p-4">
      <h5 class="section-title">💵 POSIÇÃO DE CAIXA E LIQUIDAÇÃO</h5>
      <p class="text-muted small mb-3">Panorama completo da situação financeira atual</p>
      
      <div class="row g-3">
        <div class="col-md-3">
          <div class="card p-3" style="background: #f0fdf4; border-left: 4px solid #059669;">
            <div class="metric-label">Total em Caixa</div>
            <div class="metric-value positive"><?= h(money($report['total_cash_balance'])) ?></div>
            <small class="text-muted">Soma de todas as contas</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card p-3" style="background: #ecfdf5; border-left: 4px solid #10b981;">
            <div class="metric-label">Contas a Receber</div>
            <div class="metric-value positive"><?= h(money($report['accounts_receivable_total'])) ?></div>
            <small class="text-muted">Títulos em aberto</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card p-3" style="background: #fef2f2; border-left: 4px solid #dc2626;">
            <div class="metric-label">Contas a Pagar</div>
            <div class="metric-value negative"><?= h(money($report['accounts_payable_total'])) ?></div>
            <small class="text-muted">Obrigações pendentes</small>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card p-3" style="background: <?= $report['liquidation_result'] >= 0 ? '#f0fdf4' : '#fef2f2' ?>; border-left: 4px solid <?= $report['liquidation_result'] >= 0 ? '#059669' : '#dc2626' ?>;">
            <div class="metric-label">Resultado Liquidação</div>
            <div class="metric-value <?= $report['liquidation_result'] >= 0 ? 'positive' : 'negative' ?>">
              <?= h(money($report['liquidation_result'])) ?>
            </div>
            <small class="text-muted">A Receber + Caixa - A Pagar</small>
          </div>
        </div>
      </div>
      
      <div class="alert alert-info mt-3 mb-0 small">
        <strong>📊 Interpretação:</strong> O resultado de liquidação mostra quanto você teria se recebesse tudo que tem a receber e pagasse todas as contas pendentes. 
        <?php if ($report['liquidation_result'] >= 0): ?>
          <span class="positive" style="font-weight: 700;">Resultado positivo indica boa saúde financeira! ✓</span>
        <?php else: ?>
          <span class="negative" style="font-weight: 700;">Atenção: resultado negativo indica necessidade de ação imediata! ⚠</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- SALDOS POR CONTA DE CAIXA -->
<?php if (!empty($report['cash_accounts'])): ?>
<div class="card dre-card p-4 mb-4">
  <h5 class="section-title">🏦 SALDO POR CONTA DE CAIXA</h5>
  <p class="text-muted small mb-3">Distribuição do dinheiro disponível</p>
  <div class="row g-3">
    <?php foreach ($report['cash_accounts'] as $acc): ?>
    <div class="col-md-3">
      <div class="card p-3 text-center" style="border-top: 3px solid <?= $acc['balance'] >= 0 ? '#059669' : '#dc2626' ?>;">
        <div class="text-muted small mb-1"><?= h($acc['name']) ?></div>
        <div class="<?= $acc['balance'] >= 0 ? 'positive' : 'negative' ?>" style="font-weight: 900; font-size: 1.5rem;">
          <?= h(money($acc['balance'])) ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-3 p-3" style="background: #f9fafb; border-radius: 6px;">
    <div class="d-flex justify-content-between align-items-center">
      <strong>TOTAL DISPONÍVEL EM CAIXA:</strong>
      <strong class="<?= $report['total_cash_balance'] >= 0 ? 'positive' : 'negative' ?>" style="font-size: 1.4rem;">
        <?= h(money($report['total_cash_balance'])) ?>
      </strong>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- DETALHAMENTO DE CENTROS DE CUSTO -->
<div class="row g-3 mb-4">
  <!-- Despesas Operacionais Detalhadas -->
  <?php if (!empty($report['operating_expenses_detail'])): ?>
  <div class="col-md-4">
    <div class="card dre-card p-3 h-100">
      <h6 style="font-weight: 900; color: #ea580c; margin-bottom: 1rem;">
        🏢 Despesas Operacionais
        <span class="badge bg-danger float-end"><?= count($report['operating_expenses_detail']) ?></span>
      </h6>
      <div style="max-height: 300px; overflow-y: auto;">
        <table class="table table-sm table-hover">
          <tbody>
            <?php foreach (array_slice($report['operating_expenses_detail'], 0, 10) as $item): ?>
            <tr>
              <td class="small"><?= h($item['description']) ?><br><small class="text-muted"><?= date('d/m/Y', strtotime($item['paid_at'])) ?></small></td>
              <td class="text-end small" style="white-space: nowrap;"><?= h(money($item['amount'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($report['operating_expenses_detail']) > 10): ?>
          <small class="text-muted">+ <?= count($report['operating_expenses_detail']) - 10 ?> itens...</small>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Impostos Detalhados -->
  <?php if (!empty($report['taxes_detail'])): ?>
  <div class="col-md-4">
    <div class="card dre-card p-3 h-100">
      <h6 style="font-weight: 900; color: #7c3aed; margin-bottom: 1rem;">
        🏛️ Impostos e Tributos
        <span class="badge bg-secondary float-end"><?= count($report['taxes_detail']) ?></span>
      </h6>
      <div style="max-height: 300px; overflow-y: auto;">
        <table class="table table-sm table-hover">
          <tbody>
            <?php foreach (array_slice($report['taxes_detail'], 0, 10) as $item): ?>
            <tr>
              <td class="small"><?= h($item['description']) ?><br><small class="text-muted"><?= date('d/m/Y', strtotime($item['paid_at'])) ?></small></td>
              <td class="text-end small" style="white-space: nowrap;"><?= h(money($item['amount'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($report['taxes_detail']) > 10): ?>
          <small class="text-muted">+ <?= count($report['taxes_detail']) - 10 ?> itens...</small>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Marketing Detalhado -->
  <?php if (!empty($report['marketing_detail'])): ?>
  <div class="col-md-4">
    <div class="card dre-card p-3 h-100">
      <h6 style="font-weight: 900; color: #0891b2; margin-bottom: 1rem;">
        📢 Marketing
        <span class="badge bg-info float-end"><?= count($report['marketing_detail']) ?></span>
      </h6>
      <div style="max-height: 300px; overflow-y: auto;">
        <table class="table table-sm table-hover">
          <tbody>
            <?php foreach (array_slice($report['marketing_detail'], 0, 10) as $item): ?>
            <tr>
              <td class="small"><?= h($item['description']) ?><br><small class="text-muted"><?= date('d/m/Y', strtotime($item['paid_at'])) ?></small></td>
              <td class="text-end small" style="white-space: nowrap;"><?= h(money($item['amount'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($report['marketing_detail']) > 10): ?>
          <small class="text-muted">+ <?= count($report['marketing_detail']) - 10 ?> itens...</small>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- GRÁFICOS COMPARATIVOS -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card dre-card p-4">
      <h5 class="section-title">📊 Evolução - Últimos 6 Meses</h5>
      <div style="position: relative; height: 300px;">
        <canvas id="chartMonthlyComparison"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card dre-card p-4">
      <h5 class="section-title">💹 Vendas vs Custos vs Lucro</h5>
      <div style="position: relative; height: 300px;">
        <canvas id="chartBreakdown"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- PLANEJAMENTO E PROJEÇÕES -->
<div class="card dre-card p-4 mb-4" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
  <h5 class="section-title" style="color: #1f2937;">🎯 PLANEJAMENTO E PROJEÇÕES</h5>
  <p class="text-muted mb-3">Metas baseadas em crescimento de <strong><?= $report['projection']['next_month']['growth_rate'] ?>%</strong> (ideal para microempresas)</p>
  
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card p-3" style="border-left: 4px solid #10b981;">
        <div class="metric-label" style="color: #1f2937;">Próximo Mês</div>
        <div style="font-weight: 900; font-size: 1.5rem; color: #059669;">
          <?= h(money($report['projection']['next_month']['sales_target'])) ?>
        </div>
        <small class="text-muted">Meta de vendas</small>
        <hr class="my-2">
        <div class="d-flex justify-content-between small">
          <span>Custos estimados:</span>
          <strong><?= h(money($report['projection']['next_month']['costs_estimated'])) ?></strong>
        </div>
        <div class="d-flex justify-content-between small">
          <span>Lucro projetado:</span>
          <strong class="positive"><?= h(money($report['projection']['next_month']['profit_target'])) ?></strong>
        </div>
      </div>
    </div>
    
    <div class="col-md-4">
      <div class="card p-3" style="border-left: 4px solid #3b82f6;">
        <div class="metric-label" style="color: #1f2937;">Próximo Semestre (6 meses)</div>
        <div style="font-weight: 900; font-size: 1.5rem; color: #2563eb;">
          <?= h(money($report['projection']['semester']['sales_target'])) ?>
        </div>
        <small class="text-muted">Meta de vendas</small>
        <hr class="my-2">
        <div class="d-flex justify-content-between small">
          <span>Custos estimados:</span>
          <strong><?= h(money($report['projection']['semester']['costs_estimated'])) ?></strong>
        </div>
        <div class="d-flex justify-content-between small">
          <span>Lucro projetado:</span>
          <strong class="positive"><?= h(money($report['projection']['semester']['profit_target'])) ?></strong>
        </div>
      </div>
    </div>
    
    <div class="col-md-4">
      <div class="card p-3" style="border-left: 4px solid #8b5cf6;">
        <div class="metric-label" style="color: #1f2937;">Próximo Ano (12 meses)</div>
        <div style="font-weight: 900; font-size: 1.5rem; color: #7c3aed;">
          <?= h(money($report['projection']['year']['sales_target'])) ?>
        </div>
        <small class="text-muted">Meta de vendas</small>
        <hr class="my-2">
        <div class="d-flex justify-content-between small">
          <span>Custos estimados:</span>
          <strong><?= h(money($report['projection']['year']['costs_estimated'])) ?></strong>
        </div>
        <div class="d-flex justify-content-between small">
          <span>Lucro projetado:</span>
          <strong class="positive"><?= h(money($report['projection']['year']['profit_target'])) ?></strong>
        </div>
      </div>
    </div>
  </div>
  
  <div class="alert alert-info mt-3 mb-0 small">
    <strong>💡 Como interpretar:</strong> As projeções consideram um crescimento sustentável de <?= $report['projection']['next_month']['growth_rate'] ?>% ao mês, 
    ideal para microempresas manterem operações saudáveis e crescimento consistente. Ajuste suas estratégias de vendas e controle de custos para atingir essas metas.
  </div>
</div>

<!-- INDICADORES E INSIGHTS -->
<div class="card dre-card p-4 mb-4" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
  <h5 class="mb-3" style="font-weight: 900; color: white;">📈 INDICADORES E INSIGHTS</h5>
  <div class="row g-3">
    <div class="col-md-6">
      <div style="padding: 1rem; background: rgba(255,255,255,0.2); border-radius: 8px;">
        <div style="font-size: 0.9rem; margin-bottom: 0.5rem; opacity: 0.9;">Margem de Lucro</div>
        <div style="font-size: 2rem; font-weight: 900;"><?= number_format($profitMargin, 1) ?>%</div>
        <div style="font-size: 0.8rem; opacity: 0.8;">
          <?php if ($profitMargin >= 30): ?>
            ✅ Excelente! Margem muito saudável
          <?php elseif ($profitMargin >= 20): ?>
            ✓ Boa margem de lucro
          <?php elseif ($profitMargin >= 10): ?>
            ⚠ Margem aceitável, mas pode melhorar
          <?php else: ?>
            🚨 Atenção! Margem crítica
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div style="padding: 1rem; background: rgba(255,255,255,0.2); border-radius: 8px;">
        <div style="font-size: 0.9rem; margin-bottom: 0.5rem; opacity: 0.9;">Eficiência Operacional</div>
        <div style="font-size: 2rem; font-weight: 900;">
          <?php 
          $efficiency = $report['total_sales'] > 0 ? (($totalCosts / $report['total_sales']) * 100) : 0;
          echo number_format($efficiency, 1);
          ?>%
        </div>
        <div style="font-size: 0.8rem; opacity: 0.8;">
          <?php if ($efficiency <= 70): ?>
            ✅ Custos bem controlados!
          <?php elseif ($efficiency <= 85): ?>
            ✓ Custos razoáveis
          <?php else: ?>
            🚨 Custos altos demais!
          <?php endif; ?>
          (Custos/Vendas)
        </div>
      </div>
    </div>
  </div>
</div>

<!-- RESUMO FINAL -->
<div class="card dre-card p-4" style="border: 2px solid #e5e7eb;">
  <h5 class="section-title">📋 RESUMO PARA TOMADA DE DECISÃO</h5>
  <div class="row g-3">
    <div class="col-md-12">
      <div class="alert <?= $grossProfit >= 0 ? 'alert-success' : 'alert-danger' ?> mb-0">
        <h6 style="font-weight: 900;">
          <?php if ($grossProfit >= 0): ?>
            ✅ Resultado POSITIVO no período
          <?php else: ?>
            🚨 Resultado NEGATIVO no período - Ação Necessária!
          <?php endif; ?>
        </h6>
        <hr>
        <ul class="mb-0" style="padding-left: 1.2rem;">
          <li>Faturamento: <strong><?= h(money($report['total_sales'])) ?></strong></li>
          <li>Custos Totais: <strong><?= h(money($totalCosts)) ?></strong></li>
          <li>Lucro Líquido: <strong><?= h(money($grossProfit)) ?></strong> (Margem: <?= number_format($profitMargin, 1) ?>%)</li>
          <li>Situação de Caixa: <strong><?= h(money($report['liquidation_result'])) ?></strong> (se liquidar tudo)</li>
          <?php if (!empty($report['delivery_losses'])): ?>
          <li>Prejuízos identificados: <strong><?= count($report['delivery_losses']) ?> OS</strong> - Valor total: <strong style="color: #dc2626;"><?= h(money(array_sum(array_column($report['delivery_losses'], 'loss')))) ?></strong></li>
          <?php endif; ?>
        </ul>
        
        <hr>
        <h6 style="font-weight: 900;">💡 INSIGHTS E RECOMENDAÇÕES</h6>
        <ul class="mb-0" style="padding-left: 1.2rem;">
          <?php if ($profitMargin < 10): ?>
            <li><strong style="color: #dc2626;">⚠ Margem de lucro muito baixa!</strong> Considere: aumentar preços, reduzir custos operacionais ou negociar melhores condições com fornecedores.</li>
          <?php elseif ($profitMargin < 20): ?>
            <li><strong style="color: #f59e0b;">⚡ Margem de lucro pode melhorar.</strong> Analise os custos mais altos e busque oportunidades de otimização.</li>
          <?php else: ?>
            <li><strong style="color: #059669;">✅ Excelente margem de lucro!</strong> Continue mantendo esse padrão e considere investir em expansão.</li>
          <?php endif; ?>
          
          <?php if ($report['liquidation_result'] < 0): ?>
            <li><strong style="color: #dc2626;">🚨 ATENÇÃO: Resultado de liquidação negativo!</strong> Acelere o recebimento de clientes e negocie prazos com fornecedores urgentemente.</li>
          <?php elseif ($report['liquidation_result'] < $report['total_sales'] * 0.3): ?>
            <li><strong style="color: #f59e0b;">⚠ Capital de giro apertado.</strong> Mantenha foco na cobrança e evite novos compromissos desnecessários.</li>
          <?php else: ?>
            <li><strong style="color: #059669;">✅ Boa saúde financeira!</strong> Capital de giro adequado para operação e crescimento.</li>
          <?php endif; ?>
          
          <?php if (!empty($report['delivery_losses']) && count($report['delivery_losses']) > 3): ?>
            <li><strong style="color: #dc2626;">⚠ Muitas OS com prejuízo detectadas.</strong> Revise sua precificação e processos operacionais imediatamente.</li>
          <?php endif; ?>
          
          <?php if ($efficiency > 85): ?>
            <li><strong style="color: #f59e0b;">📉 Custos operacionais altos (<?= number_format($efficiency, 1) ?>% das vendas).</strong> Identifique despesas desnecessárias e otimize processos.</li>
          <?php endif; ?>
          
          <?php 
          $monthlyComparison = $report['monthly_comparison'];
          if (count($monthlyComparison) >= 2) {
            $lastMonth = end($monthlyComparison);
            $prevMonth = prev($monthlyComparison);
            $growth = $prevMonth['sales'] > 0 ? (($lastMonth['sales'] - $prevMonth['sales']) / $prevMonth['sales']) * 100 : 0;
            if ($growth < 0): ?>
              <li><strong style="color: #dc2626;">📉 Vendas em queda (<?= number_format(abs($growth), 1) ?>% comparado ao mês anterior).</strong> Intensifique ações de marketing e prospecção.</li>
            <?php elseif ($growth > 15): ?>
              <li><strong style="color: #059669;">📈 Crescimento acelerado (<?= number_format($growth, 1) ?>% no último mês)!</strong> Prepare-se para aumentar capacidade operacional.</li>
            <?php endif;
          }
          ?>
          
          <li><strong>🎯 Meta recomendada para próximo mês:</strong> Faturar <?= h(money($report['projection']['next_month']['sales_target'])) ?> mantendo margem de lucro acima de 20%.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Scripts Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Evolução Mensal
const ctxMonthly = document.getElementById('chartMonthlyComparison');
if (ctxMonthly) {
  const monthlyData = <?= json_encode($report['monthly_comparison']) ?>;
  
  new Chart(ctxMonthly, {
    type: 'line',
    data: {
      labels: monthlyData.map(d => {
        const [year, month] = d.month.split('-');
        const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        return months[parseInt(month) - 1] + '/' + year.substr(2);
      }),
      datasets: [
        {
          label: 'Vendas',
          data: monthlyData.map(d => d.sales),
          borderColor: '#059669',
          backgroundColor: 'rgba(5, 150, 105, 0.1)',
          tension: 0.4,
          fill: true
        },
        {
          label: 'Custos',
          data: monthlyData.map(d => d.costs),
          borderColor: '#dc2626',
          backgroundColor: 'rgba(220, 38, 38, 0.1)',
          tension: 0.4,
          fill: true
        },
        {
          label: 'Lucro',
          data: monthlyData.map(d => d.profit),
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          tension: 0.4,
          fill: true
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          position: 'top',
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              label += 'R$ ' + context.parsed.y.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
              return label;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return 'R$ ' + value.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }
          }
        }
      }
    }
  });
}

// Gráfico de Breakdown (Pizza)
const ctxBreakdown = document.getElementById('chartBreakdown');
if (ctxBreakdown) {
  new Chart(ctxBreakdown, {
    type: 'doughnut',
    data: {
      labels: ['Custos de Produtos', 'Despesas Operacionais', 'Folha de Pagamento', 'Impostos', 'Marketing', 'Lucro'],
      datasets: [{
        data: [
          <?= $report['product_costs'] ?>,
          <?= $report['operating_expenses'] ?>,
          <?= $report['payroll_costs'] ?>,
          <?= $report['taxes'] ?>,
          <?= $report['marketing_costs'] ?>,
          <?= max(0, $grossProfit) ?>
        ],
        backgroundColor: [
          '#dc2626',
          '#ea580c',
          '#ca8a04',
          '#7c3aed',
          '#0891b2',
          '#059669'
        ],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.label || '';
              if (label) {
                label += ': ';
              }
              const value = context.parsed;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((value / total) * 100).toFixed(1);
              label += 'R$ ' + value.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' (' + percentage + '%)';
              return label;
            }
          }
        }
      }
    }
  });
}
</script>

<style media="print">
  .btn, .dropdown, .nav, .sidebar { display: none !important; }
  .card { break-inside: avoid; }
  body { font-size: 11pt; }
</style>
