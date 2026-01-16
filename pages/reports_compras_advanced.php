<?php
// RELATÓRIOS AVANÇADOS DE COMPRAS
require_login();
require_role(['admin', 'financeiro']);

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// 1. Resumo Geral de Compras
$resumoGeral = $pdo->prepare("
  SELECT 
    COUNT(DISTINCT ap.id) AS total_pagamentos,
    SUM(ap.amount) AS valor_total_pago,
    AVG(ap.amount) AS valor_medio
  FROM ap_titles ap
  WHERE ap.status = 'pago'
    AND DATE(ap.paid_at) BETWEEN ? AND ?
");
$resumoGeral->execute([$from, $to]);
$resumo = $resumoGeral->fetch();

// 2. Compras por Fornecedor
$comprasPorFornecedor = $pdo->prepare("
  SELECT 
    s.id,
    s.name AS fornecedor,
    COUNT(ap.id) AS numero_pagamentos,
    SUM(ap.amount) AS total_pago,
    AVG(ap.amount) AS ticket_medio,
    MAX(ap.paid_at) AS ultima_compra
  FROM ap_titles ap
  JOIN suppliers s ON s.id = ap.supplier_id
  WHERE ap.status = 'pago'
    AND DATE(ap.paid_at) BETWEEN ? AND ?
  GROUP BY s.id
  ORDER BY total_pago DESC
");
$comprasPorFornecedor->execute([$from, $to]);
$fornecedores = $comprasPorFornecedor->fetchAll();

// 3. Compras por Categoria
$comprasPorCategoria = $pdo->prepare("
  SELECT 
    COALESCE(ap.category, 'Sem categoria') AS categoria,
    COUNT(ap.id) AS numero_pagamentos,
    SUM(ap.amount) AS total_pago,
    CASE ap.category
      WHEN 'product_costs' THEN 'Custos de Produtos'
      WHEN 'operating_expenses' THEN 'Despesas Operacionais'
      WHEN 'payroll' THEN 'Folha de Pagamento'
      WHEN 'taxes' THEN 'Impostos'
      WHEN 'marketing' THEN 'Marketing'
      ELSE 'Outros'
    END AS categoria_nome
  FROM ap_titles ap
  WHERE ap.status = 'pago'
    AND DATE(ap.paid_at) BETWEEN ? AND ?
  GROUP BY ap.category
  ORDER BY total_pago DESC
");
$comprasPorCategoria->execute([$from, $to]);
$categorias = $comprasPorCategoria->fetchAll();

// 4. Maiores Despesas
$maioresDespesas = $pdo->prepare("
  SELECT 
    ap.id,
    ap.description,
    ap.amount,
    ap.paid_at,
    COALESCE(s.name, 'Diversos') AS fornecedor,
    CASE ap.category
      WHEN 'product_costs' THEN 'Custos de Produtos'
      WHEN 'operating_expenses' THEN 'Despesas Operacionais'
      WHEN 'payroll' THEN 'Folha de Pagamento'
      WHEN 'taxes' THEN 'Impostos'
      WHEN 'marketing' THEN 'Marketing'
      ELSE 'Outros'
    END AS categoria_nome
  FROM ap_titles ap
  LEFT JOIN suppliers s ON s.id = ap.supplier_id
  WHERE ap.status = 'pago'
    AND DATE(ap.paid_at) BETWEEN ? AND ?
  ORDER BY ap.amount DESC
  LIMIT 30
");
$maioresDespesas->execute([$from, $to]);
$topDespesas = $maioresDespesas->fetchAll();

// 5. Ordens de Compra (O.C)
$ordensCompra = $pdo->prepare("
  SELECT 
    p.id,
    p.code,
    p.created_at,
    p.status,
    COALESCE(s.name, 'Sem fornecedor') AS fornecedor,
    COALESCE(SUM(pl.qty * pl.unit_price), 0) AS total_oc
  FROM purchases p
  LEFT JOIN suppliers s ON s.id = p.supplier_id
  LEFT JOIN purchase_lines pl ON pl.purchase_id = p.id
  WHERE DATE(p.created_at) BETWEEN ? AND ?
  GROUP BY p.id
  ORDER BY p.created_at DESC
  LIMIT 50
");
$ordensCompra->execute([$from, $to]);
$ocs = $ordensCompra->fetchAll();
?>

<style>
.report-card {
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}
.report-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.metric-big {
  font-size: 2rem;
  font-weight: 900;
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
  font-size: 1.2rem;
  margin-bottom: 1rem;
  color: #1f2937;
}
</style>

<!-- Header -->
<div class="card report-card p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="section-title mb-1">🛒 Relatórios Avançados de Compras</h4>
      <p class="text-muted small mb-0">Análise completa de despesas e fornecedores</p>
    </div>
  </div>
  
  <form class="row g-3" method="get">
    <input type="hidden" name="page" value="reports_compras_advanced">
    <div class="col-md-4">
      <label class="form-label metric-label">Data Inicial</label>
      <input class="form-control" type="date" name="from" value="<?= h($from) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label metric-label">Data Final</label>
      <input class="form-control" type="date" name="to" value="<?= h($to) ?>" required>
    </div>
    <div class="col-md-4 align-self-end">
      <button class="btn btn-primary w-100" type="submit">
        <i class="bi bi-funnel"></i> Filtrar
      </button>
    </div>
  </form>
  
  <div class="alert alert-info mt-3 mb-0 small">
    <strong>📅 Período:</strong> <?= date('d/m/Y', strtotime($from)) ?> até <?= date('d/m/Y', strtotime($to)) ?>
  </div>
</div>

<!-- Resumo Geral -->
<div class="card report-card p-4 mb-4" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
  <h5 class="mb-4" style="font-weight: 900; color: white;">💰 RESUMO GERAL DE COMPRAS</h5>
  <div class="row g-4">
    <div class="col-md-4">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Total de Pagamentos</div>
      <div class="metric-big"><?= h($resumo['total_pagamentos']) ?></div>
      <small style="opacity: 0.8;">contas pagas no período</small>
    </div>
    <div class="col-md-4">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Valor Total Pago</div>
      <div class="metric-big"><?= h(money($resumo['valor_total_pago'])) ?></div>
      <small style="opacity: 0.8;">despesas totais</small>
    </div>
    <div class="col-md-4">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Valor Médio</div>
      <div class="metric-big"><?= h(money($resumo['valor_medio'])) ?></div>
      <small style="opacity: 0.8;">por pagamento</small>
    </div>
  </div>
</div>

<!-- Compras por Fornecedor -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">🏭 Compras por Fornecedor</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Fornecedor</th>
          <th class="text-center">Nº Pagamentos</th>
          <th class="text-end">Total Pago</th>
          <th class="text-end">Ticket Médio</th>
          <th>Última Compra</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($fornecedores as $forn): ?>
        <tr>
          <td style="font-weight: 700;"><?= h($forn['fornecedor']) ?></td>
          <td class="text-center"><span class="badge bg-primary"><?= h($forn['numero_pagamentos']) ?></span></td>
          <td class="text-end" style="font-weight: 700; color: #dc2626;"><?= h(money($forn['total_pago'])) ?></td>
          <td class="text-end"><?= h(money($forn['ticket_medio'])) ?></td>
          <td class="text-muted small"><?= date('d/m/Y', strtotime($forn['ultima_compra'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="alert alert-info mt-3 mb-0 small">
    <strong>💡 Dica:</strong> Negocie melhores condições com os fornecedores onde você mais gasta. Volume de compra = poder de negociação!
  </div>
</div>

<!-- Compras por Categoria -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">📊 Despesas por Categoria</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Categoria</th>
          <th class="text-center">Nº Pagamentos</th>
          <th class="text-end">Total Pago</th>
          <th class="text-end">% do Total</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $totalGeral = $resumo['valor_total_pago'];
        foreach ($categorias as $cat): 
          $percentual = $totalGeral > 0 ? ($cat['total_pago'] / $totalGeral) * 100 : 0;
        ?>
        <tr>
          <td style="font-weight: 700;"><?= h($cat['categoria_nome']) ?></td>
          <td class="text-center"><span class="badge bg-secondary"><?= h($cat['numero_pagamentos']) ?></span></td>
          <td class="text-end" style="font-weight: 700; color: #dc2626;"><?= h(money($cat['total_pago'])) ?></td>
          <td class="text-end">
            <span class="badge bg-info"><?= number_format($percentual, 1) ?>%</span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Top 30 Maiores Despesas -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">💸 Top 30 - Maiores Despesas</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle table-sm">
      <thead style="background: #f9fafb;">
        <tr>
          <th>#</th>
          <th>Descrição</th>
          <th>Fornecedor</th>
          <th>Categoria</th>
          <th class="text-end">Valor</th>
          <th>Data</th>
        </tr>
      </thead>
      <tbody>
        <?php $pos = 1; foreach ($topDespesas as $desp): ?>
        <tr>
          <td><strong><?= $pos++ ?></strong></td>
          <td><?= h($desp['description']) ?></td>
          <td class="small"><?= h($desp['fornecedor']) ?></td>
          <td><span class="badge bg-secondary small"><?= h($desp['categoria_nome']) ?></span></td>
          <td class="text-end" style="font-weight: 700; color: #dc2626;"><?= h(money($desp['amount'])) ?></td>
          <td class="text-muted small"><?= date('d/m/Y', strtotime($desp['paid_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Ordens de Compra (O.C) -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">📋 Ordens de Compra (O.C) - Últimas 50</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Código</th>
          <th>Fornecedor</th>
          <th>Status</th>
          <th class="text-end">Valor Total</th>
          <th>Data</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ocs as $oc): ?>
        <tr>
          <td style="font-weight: 700;"><?= h($oc['code']) ?></td>
          <td><?= h($oc['fornecedor']) ?></td>
          <td>
            <span class="badge <?= $oc['status'] === 'paga' ? 'bg-success' : ($oc['status'] === 'aprovada' ? 'bg-info' : 'bg-warning') ?>">
              <?= strtoupper(h($oc['status'])) ?>
            </span>
          </td>
          <td class="text-end" style="font-weight: 700;"><?= h(money($oc['total_oc'])) ?></td>
          <td class="text-muted small"><?= date('d/m/Y', strtotime($oc['created_at'])) ?></td>
          <td><a href="?page=purchases&view=<?= h($oc['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
