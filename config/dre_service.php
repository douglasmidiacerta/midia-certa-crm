<?php
// DRE Service - Centraliza cálculos de métricas

class DreService {
  private $pdo;
  
  public function __construct($pdo) {
    $this->pdo = $pdo;
  }
  
  // Total de vendas (receita bruta de OS no período)
  public function getTotalSales($from, $to) {
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(l.qty * l.unit_price), 0) + COALESCE(SUM(o.delivery_fee), 0) AS total
      FROM os o
      LEFT JOIN os_lines l ON l.os_id = o.id
      WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.status <> 'cancelada'
    ");
    $st->execute([$from, $to]);
    return (float) $st->fetchColumn();
  }
  
  // Custos de produtos (comprados de fornecedores)
  public function getProductCosts($from, $to) {
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(ap.amount), 0) AS total
      FROM ap_titles ap
      WHERE ap.status = 'pago'
        AND DATE(ap.paid_at) BETWEEN ? AND ?
        AND ap.category = 'product_costs'
    ");
    $st->execute([$from, $to]);
    return (float) $st->fetchColumn();
  }
  
  // Despesas operacionais (aluguel, material de escritório, etc)
  public function getOperatingExpenses($from, $to) {
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(ap.amount), 0) AS total
      FROM ap_titles ap
      WHERE ap.status = 'pago'
        AND DATE(ap.paid_at) BETWEEN ? AND ?
        AND ap.category = 'operating_expenses'
    ");
    $st->execute([$from, $to]);
    return (float) $st->fetchColumn();
  }
  
  // Custos de folha de pagamento (salários + benefícios)
  public function getPayrollCosts($from, $to) {
    $fromDate = new DateTime($from);
    $toDate = new DateTime($to);
    $months = 0;
    
    $interval = $fromDate->diff($toDate);
    $days = $interval->days + 1;
    $months = $days / 30.0;
    
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(salary + vr_monthly + va_monthly + vt_monthly + other_benefits), 0) AS monthly_total
      FROM users
      WHERE status = 'ativo'
    ");
    $st->execute();
    $monthlyTotal = (float) $st->fetchColumn();
    
    return $monthlyTotal * $months;
  }
  
  // Impostos e tributos
  public function getTaxes($from, $to) {
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(ap.amount), 0) AS total
      FROM ap_titles ap
      WHERE ap.status = 'pago'
        AND DATE(ap.paid_at) BETWEEN ? AND ?
        AND ap.category = 'taxes'
    ");
    $st->execute([$from, $to]);
    return (float) $st->fetchColumn();
  }
  
  // Custos de marketing
  public function getMarketingCosts($from, $to) {
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(ap.amount), 0) AS total
      FROM ap_titles ap
      WHERE ap.status = 'pago'
        AND DATE(ap.paid_at) BETWEEN ? AND ?
        AND ap.category = 'marketing'
    ");
    $st->execute([$from, $to]);
    return (float) $st->fetchColumn();
  }
  
  // Prejuízos por OS (quando custo > receita)
  public function getDeliveryLossesByOs($from, $to) {
    $st = $this->pdo->prepare("
      SELECT 
        o.id,
        o.code,
        o.loss_reason,
        c.name AS client_name,
        COALESCE(SUM(l.qty * l.unit_price), 0) + o.delivery_fee AS revenue,
        COALESCE(SUM(l.qty * l.unit_cost), 0) + o.delivery_cost AS cost,
        (COALESCE(SUM(l.qty * l.unit_cost), 0) + o.delivery_cost) - 
        (COALESCE(SUM(l.qty * l.unit_price), 0) + o.delivery_fee) AS loss
      FROM os o
      JOIN clients c ON c.id = o.client_id
      LEFT JOIN os_lines l ON l.os_id = o.id
      WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.status <> 'cancelada'
      GROUP BY o.id
      HAVING loss > 0
      ORDER BY loss DESC
    ");
    $st->execute([$from, $to]);
    return $st->fetchAll();
  }
  
  // Saldo por caixa
  public function getCashBalanceByAccount() {
    $accounts = [];
    
    // Tenta accounts (novo) ou cash_accounts (legado)
    $tables = ['accounts', 'cash_accounts'];
    $accountTable = null;
    
    foreach ($tables as $table) {
      $check = $this->pdo->query("SHOW TABLES LIKE '$table'");
      if ($check->rowCount() > 0) {
        $accountTable = $table;
        break;
      }
    }
    
    if (!$accountTable) return [];
    
    $st = $this->pdo->prepare("SELECT id, name FROM $accountTable WHERE active = 1");
    $st->execute();
    $accountsList = $st->fetchAll();
    
    foreach ($accountsList as $acc) {
      $balance = $this->calculateAccountBalance($acc['id'], $accountTable);
      $accounts[] = [
        'id' => $acc['id'],
        'name' => $acc['name'],
        'balance' => $balance
      ];
    }
    
    return $accounts;
  }
  
  private function calculateAccountBalance($accountId, $table) {
    // Calcula saldo: entradas - saídas
    $movTable = ($table === 'accounts') ? 'cash_movements' : 'cash_moves';
    
    $checkMov = $this->pdo->query("SHOW TABLES LIKE '$movTable'");
    if ($checkMov->rowCount() === 0) return 0.0;
    
    if ($table === 'accounts') {
      $st = $this->pdo->prepare("
        SELECT 
          COALESCE(SUM(CASE WHEN movement_type = 'entrada' THEN amount ELSE 0 END), 0) -
          COALESCE(SUM(CASE WHEN movement_type = 'saida' THEN amount ELSE 0 END), 0) AS balance
        FROM cash_movements
        WHERE account_id = ?
      ");
    } else {
      $st = $this->pdo->prepare("
        SELECT 
          COALESCE(SUM(CASE WHEN direction = 'entrada' THEN amount ELSE 0 END), 0) -
          COALESCE(SUM(CASE WHEN direction = 'saida' THEN amount ELSE 0 END), 0) AS balance
        FROM cash_moves
        WHERE cash_account_id = ?
      ");
    }
    
    $st->execute([$accountId]);
    return (float) $st->fetchColumn();
  }
  
  // Saldo total em caixa
  public function getTotalCashBalance() {
    $accounts = $this->getCashBalanceByAccount();
    $total = 0.0;
    foreach ($accounts as $acc) {
      $total += $acc['balance'];
    }
    return $total;
  }
  
  // Contas a receber (total em aberto)
  public function getAccountsReceivableTotal() {
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(amount), 0) AS total
      FROM ar_titles
      WHERE status = 'aberto'
    ");
    $st->execute();
    return (float) $st->fetchColumn();
  }
  
  // Contas a pagar (total em aberto)
  public function getAccountsPayableTotal() {
    $st = $this->pdo->prepare("
      SELECT COALESCE(SUM(amount), 0) AS total
      FROM ap_titles
      WHERE status = 'aberto'
    ");
    $st->execute();
    return (float) $st->fetchColumn();
  }
  
  // Resultado de liquidação
  public function getLiquidationResult() {
    $ar = $this->getAccountsReceivableTotal();
    $cash = $this->getTotalCashBalance();
    $ap = $this->getAccountsPayableTotal();
    
    return $ar + $cash - $ap;
  }
  
  // Detalhamento por fornecedor (quanto comprou de cada)
  public function getCostsBySupplier($from, $to) {
    $st = $this->pdo->prepare("
      SELECT 
        s.id,
        s.name AS supplier_name,
        COUNT(DISTINCT ap.id) AS payment_count,
        COALESCE(SUM(ap.amount), 0) AS total_paid,
        ap.category
      FROM ap_titles ap
      JOIN suppliers s ON s.id = ap.supplier_id
      WHERE ap.status = 'pago'
        AND DATE(ap.paid_at) BETWEEN ? AND ?
        AND ap.supplier_id IS NOT NULL
      GROUP BY s.id, ap.category
      ORDER BY total_paid DESC
    ");
    $st->execute([$from, $to]);
    return $st->fetchAll();
  }
  
  // Resumo de compras por fornecedor (agrupado)
  public function getSupplierSummary($from, $to) {
    $st = $this->pdo->prepare("
      SELECT 
        s.id,
        s.name AS supplier_name,
        COUNT(DISTINCT ap.id) AS payment_count,
        COALESCE(SUM(ap.amount), 0) AS total_paid
      FROM ap_titles ap
      JOIN suppliers s ON s.id = ap.supplier_id
      WHERE ap.status = 'pago'
        AND DATE(ap.paid_at) BETWEEN ? AND ?
        AND ap.supplier_id IS NOT NULL
      GROUP BY s.id
      ORDER BY total_paid DESC
    ");
    $st->execute([$from, $to]);
    return $st->fetchAll();
  }
  
  // Detalhamento por colaborador (custo individual)
  public function getPayrollByEmployee($from, $to) {
    $fromDate = new DateTime($from);
    $toDate = new DateTime($to);
    $days = $fromDate->diff($toDate)->days + 1;
    $months = $days / 30.0;
    
    $st = $this->pdo->prepare("
      SELECT 
        id,
        name,
        job_title,
        salary,
        vr_monthly,
        va_monthly,
        vt_monthly,
        other_benefits,
        (salary + vr_monthly + va_monthly + vt_monthly + other_benefits) AS monthly_total
      FROM users
      WHERE status = 'ativo'
      ORDER BY monthly_total DESC
    ");
    $st->execute();
    $employees = $st->fetchAll();
    
    // Calcula custo proporcional ao período
    foreach ($employees as &$emp) {
      $emp['period_cost'] = $emp['monthly_total'] * $months;
    }
    
    return $employees;
  }
  
  // Comparativo mensal (últimos N meses)
  public function getMonthlyComparison($months = 6) {
    $data = [];
    for ($i = $months - 1; $i >= 0; $i--) {
      $date = new DateTime();
      $date->modify("-$i months");
      $from = $date->format('Y-m-01');
      $to = $date->format('Y-m-t');
      
      $sales = $this->getTotalSales($from, $to);
      $costs = $this->getProductCosts($from, $to) + 
               $this->getOperatingExpenses($from, $to) + 
               $this->getPayrollCosts($from, $to) + 
               $this->getTaxes($from, $to) + 
               $this->getMarketingCosts($from, $to);
      
      $data[] = [
        'month' => $date->format('Y-m'),
        'month_name' => strftime('%B/%Y', $date->getTimestamp()),
        'sales' => $sales,
        'costs' => $costs,
        'profit' => $sales - $costs,
        'margin' => $sales > 0 ? (($sales - $costs) / $sales) * 100 : 0
      ];
    }
    return $data;
  }
  
  // Projeção e planejamento
  public function getProjection($from, $to, $growthRate = 10) {
    // Calcula dados básicos sem chamar getFullReport para evitar recursão
    $currentSales = $this->getTotalSales($from, $to);
    $currentCosts = $this->getProductCosts($from, $to) + 
                    $this->getOperatingExpenses($from, $to) + 
                    $this->getPayrollCosts($from, $to) + 
                    $this->getTaxes($from, $to) + 
                    $this->getMarketingCosts($from, $to);
    $currentProfit = $currentSales - $currentCosts;
    
    // Projeção próximo mês
    $nextMonth = [
      'period' => 'Próximo Mês',
      'sales_target' => $currentSales * (1 + $growthRate / 100),
      'costs_estimated' => $currentCosts * (1 + $growthRate / 100),
      'profit_target' => ($currentSales * (1 + $growthRate / 100)) - ($currentCosts * (1 + $growthRate / 100)),
      'growth_rate' => $growthRate
    ];
    
    // Projeção semestre (6 meses)
    $semester = [
      'period' => 'Próximo Semestre (6 meses)',
      'sales_target' => ($currentSales * 6) * (1 + $growthRate / 100),
      'costs_estimated' => ($currentCosts * 6) * (1 + $growthRate / 100),
      'profit_target' => (($currentSales * 6) * (1 + $growthRate / 100)) - (($currentCosts * 6) * (1 + $growthRate / 100)),
      'growth_rate' => $growthRate
    ];
    
    // Projeção ano (12 meses)
    $year = [
      'period' => 'Próximo Ano (12 meses)',
      'sales_target' => ($currentSales * 12) * (1 + $growthRate / 100),
      'costs_estimated' => ($currentCosts * 12) * (1 + $growthRate / 100),
      'profit_target' => (($currentSales * 12) * (1 + $growthRate / 100)) - (($currentCosts * 12) * (1 + $growthRate / 100)),
      'growth_rate' => $growthRate
    ];
    
    return [
      'current' => [
        'sales' => $currentSales,
        'costs' => $currentCosts,
        'profit' => $currentProfit
      ],
      'next_month' => $nextMonth,
      'semester' => $semester,
      'year' => $year
    ];
  }
  
  // Detalhes de despesas operacionais
  public function getOperatingExpensesDetail($from, $to) {
    $st = $this->pdo->prepare("
      SELECT 
        description,
        amount,
        paid_at,
        COALESCE(s.name, 'Diversos') as supplier_name
      FROM ap_titles
      LEFT JOIN suppliers s ON s.id = ap_titles.supplier_id
      WHERE status = 'pago'
        AND category = 'operating_expenses'
        AND DATE(paid_at) BETWEEN ? AND ?
      ORDER BY paid_at DESC
    ");
    $st->execute([$from, $to]);
    return $st->fetchAll();
  }
  
  // Detalhes de impostos
  public function getTaxesDetail($from, $to) {
    $st = $this->pdo->prepare("
      SELECT 
        description,
        amount,
        paid_at,
        COALESCE(s.name, 'Governo') as supplier_name
      FROM ap_titles
      LEFT JOIN suppliers s ON s.id = ap_titles.supplier_id
      WHERE status = 'pago'
        AND category = 'taxes'
        AND DATE(paid_at) BETWEEN ? AND ?
      ORDER BY paid_at DESC
    ");
    $st->execute([$from, $to]);
    return $st->fetchAll();
  }
  
  // Detalhes de marketing
  public function getMarketingDetail($from, $to) {
    $st = $this->pdo->prepare("
      SELECT 
        description,
        amount,
        paid_at,
        COALESCE(s.name, 'Marketing') as supplier_name
      FROM ap_titles
      LEFT JOIN suppliers s ON s.id = ap_titles.supplier_id
      WHERE status = 'pago'
        AND category = 'marketing'
        AND DATE(paid_at) BETWEEN ? AND ?
      ORDER BY paid_at DESC
    ");
    $st->execute([$from, $to]);
    return $st->fetchAll();
  }
  
  // Resumo completo do DRE
  public function getFullReport($from, $to) {
    return [
      'total_sales' => $this->getTotalSales($from, $to),
      'product_costs' => $this->getProductCosts($from, $to),
      'operating_expenses' => $this->getOperatingExpenses($from, $to),
      'payroll_costs' => $this->getPayrollCosts($from, $to),
      'taxes' => $this->getTaxes($from, $to),
      'marketing_costs' => $this->getMarketingCosts($from, $to),
      'delivery_losses' => $this->getDeliveryLossesByOs($from, $to),
      'cash_accounts' => $this->getCashBalanceByAccount(),
      'total_cash_balance' => $this->getTotalCashBalance(),
      'accounts_receivable_total' => $this->getAccountsReceivableTotal(),
      'accounts_payable_total' => $this->getAccountsPayableTotal(),
      'liquidation_result' => $this->getLiquidationResult(),
      'costs_by_supplier' => $this->getCostsBySupplier($from, $to),
      'supplier_summary' => $this->getSupplierSummary($from, $to),
      'payroll_by_employee' => $this->getPayrollByEmployee($from, $to),
      'operating_expenses_detail' => $this->getOperatingExpensesDetail($from, $to),
      'taxes_detail' => $this->getTaxesDetail($from, $to),
      'marketing_detail' => $this->getMarketingDetail($from, $to),
      'monthly_comparison' => $this->getMonthlyComparison(6),
      'projection' => $this->getProjection($from, $to, 10)
    ];
  }
}
