<?php
// RELATÓRIOS AVANÇADOS DE VENDAS
require_login();
require_role(['admin', 'vendas']);

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// 1. Produtos Mais Vendidos
$produtosMaisVendidos = $pdo->prepare("
  SELECT 
    i.id,
    i.name AS produto,
    SUM(ol.qty) AS quantidade_vendida,
    SUM(ol.qty * ol.unit_price) AS faturamento_total,
    SUM(ol.qty * ol.unit_cost) AS custo_total,
    SUM(ol.qty * (ol.unit_price - ol.unit_cost)) AS lucro_total,
    CASE 
      WHEN SUM(ol.qty * ol.unit_price) > 0 
      THEN ((SUM(ol.qty * (ol.unit_price - ol.unit_cost)) / SUM(ol.qty * ol.unit_price)) * 100)
      ELSE 0 
    END AS margem_lucro,
    COUNT(DISTINCT ol.os_id) AS numero_vendas
  FROM os_lines ol
  JOIN items i ON i.id = ol.item_id
  JOIN os o ON o.id = ol.os_id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN ? AND ?
  GROUP BY i.id
  ORDER BY quantidade_vendida DESC
  LIMIT 20
");
$produtosMaisVendidos->execute([$from, $to]);
$topProdutos = $produtosMaisVendidos->fetchAll();

// 2. Produtos com Melhor Margem
$produtosMelhorMargem = $pdo->prepare("
  SELECT 
    i.id,
    i.name AS produto,
    SUM(ol.qty) AS quantidade_vendida,
    SUM(ol.qty * ol.unit_price) AS faturamento_total,
    SUM(ol.qty * ol.unit_cost) AS custo_total,
    SUM(ol.qty * (ol.unit_price - ol.unit_cost)) AS lucro_total,
    CASE 
      WHEN SUM(ol.qty * ol.unit_price) > 0 
      THEN ((SUM(ol.qty * (ol.unit_price - ol.unit_cost)) / SUM(ol.qty * ol.unit_price)) * 100)
      ELSE 0 
    END AS margem_lucro
  FROM os_lines ol
  JOIN items i ON i.id = ol.item_id
  JOIN os o ON o.id = ol.os_id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN ? AND ?
  GROUP BY i.id
  HAVING SUM(ol.qty) > 0
  ORDER BY margem_lucro DESC
  LIMIT 20
");
$produtosMelhorMargem->execute([$from, $to]);
$topMargem = $produtosMelhorMargem->fetchAll();

// 3. Melhores Clientes (por faturamento)
$melhoresClientes = $pdo->prepare("
  SELECT 
    c.id,
    c.name AS cliente,
    COUNT(DISTINCT o.id) AS numero_pedidos,
    SUM(ol.qty * ol.unit_price) AS faturamento_total,
    SUM(ol.qty * ol.unit_cost) AS custo_total,
    SUM(ol.qty * (ol.unit_price - ol.unit_cost)) AS lucro_total,
    AVG(ol.qty * ol.unit_price) AS ticket_medio
  FROM os o
  JOIN clients c ON c.id = o.client_id
  JOIN os_lines ol ON ol.os_id = o.id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN ? AND ?
  GROUP BY c.id
  ORDER BY faturamento_total DESC
  LIMIT 20
");
$melhoresClientes->execute([$from, $to]);
$topClientes = $melhoresClientes->fetchAll();

// 4. Performance por Vendedor
$performanceVendedores = $pdo->prepare("
  SELECT 
    u.id,
    u.name AS vendedor,
    COUNT(DISTINCT o.id) AS numero_vendas,
    SUM(ol.qty * ol.unit_price) AS faturamento_total,
    SUM(ol.qty * ol.unit_cost) AS custo_total,
    SUM(ol.qty * (ol.unit_price - ol.unit_cost)) AS lucro_gerado,
    AVG(ol.qty * ol.unit_price) AS ticket_medio
  FROM os o
  JOIN users u ON u.id = o.seller_user_id
  JOIN os_lines ol ON ol.os_id = o.id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN ? AND ?
  GROUP BY u.id
  ORDER BY faturamento_total DESC
");
$performanceVendedores->execute([$from, $to]);
$vendedores = $performanceVendedores->fetchAll();

// 5. Resumo Geral
$resumoGeral = $pdo->prepare("
  SELECT 
    COUNT(DISTINCT o.id) AS total_vendas,
    SUM(ol.qty * ol.unit_price) AS faturamento_total,
    SUM(ol.qty * ol.unit_cost) AS custo_total,
    SUM(ol.qty * (ol.unit_price - ol.unit_cost)) AS lucro_total,
    AVG(ol.qty * ol.unit_price) AS ticket_medio
  FROM os o
  JOIN os_lines ol ON ol.os_id = o.id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN ? AND ?
");
$resumoGeral->execute([$from, $to]);
$resumo = $resumoGeral->fetch();

// 6. Categorias Mais Vendidas
$categoriasMaisVendidas = $pdo->prepare("
  SELECT 
    cat.id,
    cat.name AS categoria,
    SUM(ol.qty) AS quantidade_vendida,
    SUM(ol.qty * ol.unit_price) AS faturamento_total,
    COUNT(DISTINCT ol.os_id) AS numero_vendas
  FROM os_lines ol
  JOIN items i ON i.id = ol.item_id
  JOIN categories cat ON cat.id = i.category_id
  JOIN os o ON o.id = ol.os_id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN ? AND ?
  GROUP BY cat.id
  ORDER BY faturamento_total DESC
");
$categoriasMaisVendidas->execute([$from, $to]);
$topCategorias = $categoriasMaisVendidas->fetchAll();

$margemGeral = $resumo['faturamento_total'] > 0 
  ? (($resumo['lucro_total'] / $resumo['faturamento_total']) * 100) 
  : 0;
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
      <h4 class="section-title mb-1">📊 Relatórios Avançados de Vendas</h4>
      <p class="text-muted small mb-0">Análise completa de performance comercial</p>
    </div>
  </div>
  
  <form class="row g-3" method="get">
    <input type="hidden" name="page" value="reports_sales_advanced">
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
<div class="card report-card p-4 mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
  <h5 class="mb-4" style="font-weight: 900; color: white;">💰 RESUMO GERAL</h5>
  <div class="row g-4">
    <div class="<?= can_admin() || can_finance() ? 'col-md-3' : 'col-md-4' ?>">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Total de Vendas</div>
      <div class="metric-big"><?= h($resumo['total_vendas']) ?></div>
      <small style="opacity: 0.8;">pedidos no período</small>
    </div>
    <div class="<?= can_admin() || can_finance() ? 'col-md-3' : 'col-md-4' ?>">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Faturamento Total</div>
      <div class="metric-big"><?= h(money($resumo['faturamento_total'])) ?></div>
      <small style="opacity: 0.8;">receita bruta</small>
    </div>
    <?php if(can_admin() || can_finance()): ?>
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Lucro Total</div>
      <div class="metric-big"><?= h(money($resumo['lucro_total'])) ?></div>
      <small style="opacity: 0.8;">margem: <?= number_format($margemGeral, 1) ?>%</small>
    </div>
    <?php endif; ?>
    <div class="<?= can_admin() || can_finance() ? 'col-md-3' : 'col-md-4' ?>">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Ticket Médio</div>
      <div class="metric-big"><?= h(money($resumo['ticket_medio'])) ?></div>
      <small style="opacity: 0.8;">por pedido</small>
    </div>
  </div>
</div>

<!-- Top 20 Produtos Mais Vendidos -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">🏆 Top 20 - Produtos Mais Vendidos</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>#</th>
          <th>Produto</th>
          <th class="text-center">Qtd Vendida</th>
          <th class="text-center">Nº Vendas</th>
          <th class="text-end">Faturamento</th>
          <?php if(can_admin() || can_finance()): ?>
          <th class="text-end">Lucro</th>
          <th class="text-end">Margem</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php $pos = 1; foreach ($topProdutos as $prod): ?>
        <tr>
          <td><strong><?= $pos++ ?></strong></td>
          <td style="font-weight: 700;"><?= h($prod['produto']) ?></td>
          <td class="text-center"><span class="badge bg-primary"><?= number_format($prod['quantidade_vendida'], 0, ',', '.') ?></span></td>
          <td class="text-center"><?= h($prod['numero_vendas']) ?></td>
          <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($prod['faturamento_total'])) ?></td>
          <?php if(can_admin() || can_finance()): ?>
          <td class="text-end" style="font-weight: 700; color: #2563eb;"><?= h(money($prod['lucro_total'])) ?></td>
          <td class="text-end">
            <span class="badge <?= $prod['margem_lucro'] >= 30 ? 'bg-success' : ($prod['margem_lucro'] >= 20 ? 'bg-warning' : 'bg-danger') ?>">
              <?= number_format($prod['margem_lucro'], 1) ?>%
            </span>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Top 20 Produtos com Melhor Margem -->
<?php if(can_admin() || can_finance()): ?>
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">💎 Top 20 - Produtos com Melhor Margem de Lucro</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>#</th>
          <th>Produto</th>
          <th class="text-end">Margem</th>
          <th class="text-center">Qtd Vendida</th>
          <th class="text-end">Faturamento</th>
          <th class="text-end">Lucro</th>
        </tr>
      </thead>
      <tbody>
        <?php $pos = 1; foreach ($topMargem as $prod): ?>
        <tr>
          <td><strong><?= $pos++ ?></strong></td>
          <td style="font-weight: 700;"><?= h($prod['produto']) ?></td>
          <td class="text-end">
            <span class="badge <?= $prod['margem_lucro'] >= 50 ? 'bg-success' : ($prod['margem_lucro'] >= 30 ? 'bg-info' : 'bg-warning') ?>" style="font-size: 1rem;">
              <?= number_format($prod['margem_lucro'], 1) ?>%
            </span>
          </td>
          <td class="text-center"><?= number_format($prod['quantidade_vendida'], 0, ',', '.') ?></td>
          <td class="text-end" style="font-weight: 700;"><?= h(money($prod['faturamento_total'])) ?></td>
          <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($prod['lucro_total'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="alert alert-info mt-3 mb-0 small">
    <strong>💡 Dica:</strong> Foque em vender mais os produtos com alta margem. Considere criar combos ou promoções especiais com esses itens.
  </div>
</div>
<?php endif; ?>

<!-- Top 20 Melhores Clientes -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">👥 Top 20 - Melhores Clientes</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>#</th>
          <th>Cliente</th>
          <th class="text-center">Nº Pedidos</th>
          <th class="text-end">Faturamento Total</th>
          <?php if(can_admin() || can_finance()): ?>
          <th class="text-end">Lucro Gerado</th>
          <?php endif; ?>
          <th class="text-end">Ticket Médio</th>
        </tr>
      </thead>
      <tbody>
        <?php $pos = 1; foreach ($topClientes as $cli): ?>
        <tr>
          <td><strong><?= $pos++ ?></strong></td>
          <td style="font-weight: 700;"><?= h($cli['cliente']) ?></td>
          <td class="text-center"><span class="badge bg-primary"><?= h($cli['numero_pedidos']) ?></span></td>
          <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($cli['faturamento_total'])) ?></td>
          <?php if(can_admin() || can_finance()): ?>
          <td class="text-end" style="font-weight: 700; color: #2563eb;"><?= h(money($cli['lucro_total'])) ?></td>
          <?php endif; ?>
          <td class="text-end"><?= h(money($cli['ticket_medio'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="alert alert-success mt-3 mb-0 small">
    <strong>✅ Estratégia:</strong> Mantenha relacionamento próximo com esses clientes! Considere programa de fidelidade ou descontos especiais.
  </div>
</div>

<!-- Performance por Vendedor -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">🎯 Performance por Vendedor</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Vendedor</th>
          <th class="text-center">Nº Vendas</th>
          <th class="text-end">Faturamento</th>
          <?php if(can_admin() || can_finance()): ?>
          <th class="text-end">Lucro Gerado</th>
          <?php endif; ?>
          <th class="text-end">Ticket Médio</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vendedores as $vend): ?>
        <tr>
          <td style="font-weight: 700;"><?= h($vend['vendedor']) ?></td>
          <td class="text-center"><span class="badge bg-primary"><?= h($vend['numero_vendas']) ?></span></td>
          <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($vend['faturamento_total'])) ?></td>
          <?php if(can_admin() || can_finance()): ?>
          <td class="text-end" style="font-weight: 700; color: #2563eb;"><?= h(money($vend['lucro_gerado'])) ?></td>
          <?php endif; ?>
          <td class="text-end"><?= h(money($vend['ticket_medio'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Categorias Mais Vendidas -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">📦 Categorias Mais Vendidas</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Categoria</th>
          <th class="text-center">Qtd Vendida</th>
          <th class="text-center">Nº Vendas</th>
          <th class="text-end">Faturamento</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topCategorias as $cat): ?>
        <tr>
          <td style="font-weight: 700;"><?= h($cat['categoria']) ?></td>
          <td class="text-center"><?= number_format($cat['quantidade_vendida'], 0, ',', '.') ?></td>
          <td class="text-center"><span class="badge bg-secondary"><?= h($cat['numero_vendas']) ?></span></td>
          <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($cat['faturamento_total'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>