<?php
// DASHBOARD PROFISSIONAL COM GRÁFICOS
require_login();
$user = user();
$isFinance = can_finance() || can_admin();
$isSales = can_sales() || can_admin();

// Buscar pedidos pendentes (vindos do site)
$pedidos_pendentes = $pdo->query("
  SELECT os.*, c.name as client_name, c.phone as client_phone, c.email as client_email
  FROM os 
  JOIN clients c ON c.id = os.client_id
  WHERE os.status = 'pedido_pendente' AND os.origem = 'site'
  ORDER BY os.created_at DESC
")->fetchAll();

// Período padrão: mês atual
$from = date('Y-m-01');
$to = date('Y-m-d');

// 1. Total de OS por status
$osStatus = $pdo->query("
  SELECT status, COUNT(*) as total 
  FROM os 
  WHERE created_at >= '$from'
  GROUP BY status
")->fetchAll();

$statusCounts = [];
foreach ($osStatus as $s) {
    $statusCounts[$s['status']] = $s['total'];
}

// 2. Faturamento do mês
$faturamento = $pdo->query("
  SELECT 
    COALESCE(SUM(ol.qty * ol.unit_price), 0) as total
  FROM os o
  JOIN os_lines ol ON ol.os_id = o.id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN '$from' AND '$to'
")->fetch()['total'];

// 3. Lucro do mês (só para financeiro/admin)
$lucro = 0;
$margem = 0;
if ($isFinance) {
    $lucroData = $pdo->query("
      SELECT 
        COALESCE(SUM(ol.qty * ol.unit_price), 0) as receita,
        COALESCE(SUM(ol.qty * ol.unit_cost), 0) as custo
      FROM os o
      JOIN os_lines ol ON ol.os_id = o.id
      WHERE o.status != 'cancelada'
        AND DATE(o.created_at) BETWEEN '$from' AND '$to'
    ")->fetch();
    $lucro = $lucroData['receita'] - $lucroData['custo'];
    $margem = $lucroData['receita'] > 0 ? ($lucro / $lucroData['receita']) * 100 : 0;
}

// 4. Evolução dos últimos 6 meses
$evolutionData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    
    $data = $pdo->query("
      SELECT 
        COALESCE(SUM(ol.qty * ol.unit_price), 0) as vendas,
        COALESCE(SUM(ol.qty * ol.unit_cost), 0) as custos,
        COUNT(DISTINCT o.id) as num_vendas
      FROM os o
      JOIN os_lines ol ON ol.os_id = o.id
      WHERE o.status != 'cancelada'
        AND DATE(o.created_at) BETWEEN '$monthStart' AND '$monthEnd'
    ")->fetch();
    
    $evolutionData[] = [
        'month' => date('M/y', strtotime($monthStart)),
        'vendas' => $data['vendas'],
        'custos' => $data['custos'],
        'lucro' => $data['vendas'] - $data['custos'],
        'num_vendas' => $data['num_vendas']
    ];
}

// 5. Top 5 clientes do mês
$topClientes = $pdo->query("
  SELECT 
    c.name as cliente,
    COUNT(DISTINCT o.id) as num_pedidos,
    COALESCE(SUM(ol.qty * ol.unit_price), 0) as total
  FROM os o
  JOIN clients c ON c.id = o.client_id
  JOIN os_lines ol ON ol.os_id = o.id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN '$from' AND '$to'
  GROUP BY c.id
  ORDER BY total DESC
  LIMIT 5
")->fetchAll();

// 6. Top 5 produtos do mês
$topProdutos = $pdo->query("
  SELECT 
    i.name as produto,
    SUM(ol.qty) as quantidade,
    COALESCE(SUM(ol.qty * ol.unit_price), 0) as total
  FROM os_lines ol
  JOIN items i ON i.id = ol.item_id
  JOIN os o ON o.id = ol.os_id
  WHERE o.status != 'cancelada'
    AND DATE(o.created_at) BETWEEN '$from' AND '$to'
  GROUP BY i.id
  ORDER BY total DESC
  LIMIT 5
")->fetchAll();

// 7. Financeiro: Contas a receber/pagar
$contasReceber = 0;
$contasPagar = 0;
$saldoCaixa = 0;

if ($isFinance) {
    $contasReceber = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM ar_titles WHERE status = 'aberto'")->fetch()['total'];
    $contasPagar = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM ap_titles WHERE status = 'aberto'")->fetch()['total'];
    
    // Calcular saldo em caixa = saldo inicial + entradas - saídas
    $saldoCaixa = $pdo->query("
      SELECT 
        COALESCE(SUM(a.initial_balance), 0) +
        COALESCE((
          SELECT SUM(amount)
          FROM cash_movements
          WHERE movement_type = 'entrada'
        ), 0) -
        COALESCE((
          SELECT SUM(amount)
          FROM cash_movements
          WHERE movement_type = 'saida'
        ), 0) as total
      FROM accounts a
      WHERE a.active = 1
    ")->fetch()['total'];
}

// 7.1 Alertas automáticos
$alertas = [
    'aprovacao_pendente' => 0,
    'receber_vencido' => 0,
    'pagar_vencido' => 0,
    'conferencia_atrasada' => 0,
];

$alertas['aprovacao_pendente'] = (int)$pdo->query("
  SELECT COUNT(*) FROM os
  WHERE status IN ('arte','aguardando_aprovacao')
")->fetchColumn();

$alertas['receber_vencido'] = (int)$pdo->query("
  SELECT COUNT(*) FROM ar_titles
  WHERE status='aberto' AND due_date IS NOT NULL AND due_date < CURDATE()
")->fetchColumn();

$alertas['pagar_vencido'] = (int)$pdo->query("
  SELECT COUNT(*) FROM ap_titles
  WHERE status='aberto' AND due_date IS NOT NULL AND due_date < CURDATE()
")->fetchColumn();

$alertas['conferencia_atrasada'] = (int)$pdo->query("
  SELECT COUNT(*) FROM os
  WHERE status='conferencia' AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
")->fetchColumn();

// 8. Performance do vendedor (se for vendedor)
$minhasVendas = 0;
$meuFaturamento = 0;
if ($isSales && !can_admin()) {
    $myStats = $pdo->prepare("
      SELECT 
        COUNT(DISTINCT o.id) as vendas,
        COALESCE(SUM(ol.qty * ol.unit_price), 0) as faturamento
      FROM os o
      JOIN os_lines ol ON ol.os_id = o.id
      WHERE o.seller_user_id = ?
        AND o.status != 'cancelada'
        AND DATE(o.created_at) BETWEEN '$from' AND '$to'
    ");
    $myStats->execute([$user['id']]);
    $myData = $myStats->fetch();
    $minhasVendas = $myData['vendas'];
    $meuFaturamento = $myData['faturamento'];
}
?>

<style>
.dashboard-card {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none;
}
.dashboard-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}
.metric-card {
    text-align: center;
    padding: 1.5rem;
}
.metric-value {
    font-size: 2.5rem;
    font-weight: 900;
    line-height: 1;
    margin: 0.5rem 0;
}
.metric-label {
    font-size: 0.875rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.chart-container {
    position: relative;
    height: 300px;
}
</style>

<!-- Cabeçalho -->
<div class="mb-4">
    <h3 style="font-weight: 900;">Olá, <?= h($user['name']) ?>! 👋</h3>
    <p class="text-muted">Bem-vindo ao painel de controle. Aqui está o resumo do mês atual.</p>
</div>

<!-- Alerta de Pedidos Pendentes -->
<?php if(!empty($pedidos_pendentes)): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4" style="border-left: 5px solid #f59e0b; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  <div class="d-flex align-items-start">
    <div style="font-size: 2.5rem; margin-right: 20px;">⚠️</div>
    <div class="flex-grow-1">
      <h4 class="alert-heading fw-bold mb-2">🚨 <?= count($pedidos_pendentes) ?> Pedido(s) Aguardando Aprovação!</h4>
      <p class="mb-3">Novos pedidos recebidos pelo site precisam de sua análise e aprovação comercial.</p>
      
      <div class="table-responsive">
        <table class="table table-sm table-hover bg-white mb-3">
          <thead>
            <tr>
              <th>O.S.</th>
              <th>Cliente</th>
              <th>Contato</th>
              <th>Data</th>
              <th>Pagamento</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($pedidos_pendentes as $ped): ?>
            <tr>
              <td><strong><?= h($ped['code']) ?></strong></td>
              <td><?= h($ped['client_name']) ?></td>
              <td>
                <?php if($ped['client_phone']): ?>
                  <a href="https://wa.me/55<?= preg_replace('/\D/', '', $ped['client_phone']) ?>" target="_blank" class="btn btn-sm btn-success">
                    📱 WhatsApp
                  </a>
                <?php endif; ?>
              </td>
              <td><?= date('d/m/Y H:i', strtotime($ped['created_at'])) ?></td>
              <td><?= h($ped['pagamento_preferencial'] ?: 'A definir') ?></td>
              <td class="text-end">
                <a href="<?= $base ?>/app.php?page=os_view&id=<?= (int)$ped['id'] ?>" class="btn btn-sm btn-primary">
                  👁️ Analisar e Aprovar
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      
      <p class="mb-0 small text-muted">
        💡 <strong>Próximos passos:</strong> Entre em contato com o cliente, confirme forma de pagamento e prazo, depois aprove o pedido para iniciar a produção.
      </p>
    </div>
  </div>
</div>

<?php if($isFinance): ?>
<div class="card p-3 mb-4" style="border-left:4px solid #ef4444;">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 style="font-weight:900;margin-bottom:0;">⚠️ Alertas Automáticos</h5>
            <div class="text-muted small">Atenção a pendências operacionais e financeiras</div>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-3 mb-2">
            <div class="p-2 bg-light rounded">
                <div class="text-muted small">Aprovação pendente</div>
                <div style="font-weight:900; font-size:1.2rem;"><?= (int)$alertas['aprovacao_pendente'] ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="p-2 bg-light rounded">
                <div class="text-muted small">A receber vencido</div>
                <div style="font-weight:900; font-size:1.2rem;"><?= (int)$alertas['receber_vencido'] ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="p-2 bg-light rounded">
                <div class="text-muted small">A pagar vencido</div>
                <div style="font-weight:900; font-size:1.2rem;"><?= (int)$alertas['pagar_vencido'] ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="p-2 bg-light rounded">
                <div class="text-muted small">Conferência atrasada</div>
                <div style="font-weight:900; font-size:1.2rem;"><?= (int)$alertas['conferencia_atrasada'] ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Métricas Principais -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="dashboard-card metric-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="metric-label" style="color: rgba(255,255,255,0.9);">Faturamento do Mês</div>
            <div class="metric-value"><?= h(money($isSales && !can_admin() && !can_finance() ? $meuFaturamento : $faturamento)) ?></div>
            <small style="opacity: 0.9;"><?= can_admin() || can_finance() ? 'Total da empresa' : 'Suas vendas' ?></small>
        </div>
    </div>
    
    <?php if ($isSales && !can_admin() && !can_finance()): ?>
    <div class="col-md-3">
        <div class="dashboard-card metric-card" style="background: #059669; color: white;">
            <div class="metric-label" style="color: rgba(255,255,255,0.9);">Minhas Vendas</div>
            <div class="metric-value"><?= h($minhasVendas) ?></div>
            <small style="opacity: 0.9;">pedidos no mês</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card metric-card" style="background: #3b82f6; color: white;">
            <div class="metric-label" style="color: rgba(255,255,255,0.9);">OS em Andamento</div>
            <div class="metric-value"><?= h($statusCounts['atendimento'] ?? 0) ?></div>
            <small style="opacity: 0.9;">em atendimento</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card metric-card" style="background: #8b5cf6; color: white;">
            <div class="metric-label" style="color: rgba(255,255,255,0.9);">Total OS (Mês)</div>
            <div class="metric-value"><?= h(array_sum($statusCounts)) ?></div>
            <small style="opacity: 0.9;">todas as OS</small>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($isFinance): ?>
    <div class="col-md-3">
        <div class="dashboard-card metric-card" style="background: #059669; color: white;">
            <div class="metric-label" style="color: rgba(255,255,255,0.9);">Lucro do Mês</div>
            <div class="metric-value"><?= h(money($lucro)) ?></div>
            <small style="opacity: 0.9;">margem: <?= number_format($margem, 1) ?>%</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card metric-card" style="background: #3b82f6; color: white;">
            <div class="metric-label" style="color: rgba(255,255,255,0.9);">Saldo em Caixa</div>
            <div class="metric-value"><?= h(money($saldoCaixa)) ?></div>
            <small style="opacity: 0.9;">disponível</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card metric-card" style="background: #f59e0b; color: white;">
            <div class="metric-label" style="color: rgba(255,255,255,0.9);">A Receber</div>
            <div class="metric-value"><?= h(money($contasReceber)) ?></div>
            <small style="opacity: 0.9;">títulos abertos</small>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Status das OS -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="dashboard-card p-4">
            <h5 style="font-weight: 900; margin-bottom: 1rem;">📊 Evolução dos Últimos 6 Meses</h5>
            <div class="chart-container">
                <canvas id="chartEvolution"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card p-4">
            <h5 style="font-weight: 900; margin-bottom: 1rem;">🔄 Status das OS (Mês Atual)</h5>
            <div class="chart-container">
                <canvas id="chartStatus"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Estatísticas Adicionais -->
<?php if ($isFinance): ?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="dashboard-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="metric-label">Taxa Conversão</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: #3b82f6;">
                        <?php 
                        $totalOS = array_sum($statusCounts);
                        $finalizadas = $statusCounts['finalizada'] ?? 0;
                        $conversao = $totalOS > 0 ? ($finalizadas / $totalOS) * 100 : 0;
                        echo number_format($conversao, 1);
                        ?>%
                    </div>
                    <small class="text-muted">OS finalizadas</small>
                </div>
                <div style="font-size: 2rem;">📈</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="metric-label">Ticket Médio</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: #059669;">
                        <?php 
                        $totalVendas = array_sum(array_column($evolutionData, 'num_vendas'));
                        $ticketMedio = $totalVendas > 0 ? $faturamento / count(array_filter($statusCounts)) : 0;
                        echo h(money($ticketMedio));
                        ?>
                    </div>
                    <small class="text-muted">por OS</small>
                </div>
                <div style="font-size: 2rem;">💰</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="metric-label">Em Produção</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: #f59e0b;">
                        <?= h(($statusCounts['producao'] ?? 0) + ($statusCounts['disponivel'] ?? 0)) ?>
                    </div>
                    <small class="text-muted">OS na linha</small>
                </div>
                <div style="font-size: 2rem;">🏭</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="dashboard-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="metric-label">Pendentes</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: #dc2626;">
                        <?= h(($statusCounts['atendimento'] ?? 0) + ($statusCounts['arte'] ?? 0) + ($statusCounts['conferencia'] ?? 0)) ?>
                    </div>
                    <small class="text-muted">aguardando ação</small>
                </div>
                <div style="font-size: 2rem;">⏳</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isSales && !can_admin() && !can_finance()): ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="dashboard-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="metric-label">Meta Pessoal</div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: #3b82f6;">
                        <?php 
                        $metaMensal = 50000; // Pode ser configurável
                        $atingimento = $metaMensal > 0 ? ($meuFaturamento / $metaMensal) * 100 : 0;
                        echo number_format($atingimento, 1);
                        ?>%
                    </div>
                    <small class="text-muted">de R$ <?= number_format($metaMensal, 0, ',', '.') ?></small>
                </div>
                <div style="font-size: 2rem;">🎯</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="metric-label">Ticket Médio</div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: #059669;">
                        <?= h(money($minhasVendas > 0 ? $meuFaturamento / $minhasVendas : 0)) ?>
                    </div>
                    <small class="text-muted">por venda</small>
                </div>
                <div style="font-size: 2rem;">📊</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="metric-label">Ranking</div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: #f59e0b;">
                        <?php
                        // Busca posição do vendedor no ranking
                        $ranking = $pdo->prepare("
                            SELECT COUNT(*) + 1 as posicao
                            FROM (
                                SELECT u.id, SUM(ol.qty * ol.unit_price) as total
                                FROM os o
                                JOIN os_lines ol ON ol.os_id = o.id
                                JOIN users u ON u.id = o.seller_user_id
                                WHERE o.status != 'cancelada'
                                  AND DATE(o.created_at) BETWEEN '$from' AND '$to'
                                GROUP BY u.id
                                HAVING total > ?
                            ) as vendedores
                        ");
                        $ranking->execute([$meuFaturamento]);
                        $posicao = $ranking->fetch()['posicao'] ?? 1;
                        echo $posicao . 'º';
                        ?>
                    </div>
                    <small class="text-muted">no mês</small>
                </div>
                <div style="font-size: 2rem;">🏆</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Top Clientes e Produtos -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="dashboard-card p-4">
            <h5 style="font-weight: 900; margin-bottom: 1rem;">👥 Top 5 Clientes do Mês</h5>
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th class="text-center">Pedidos</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topClientes as $cli): ?>
                    <tr>
                        <td style="font-weight: 700;"><?= h($cli['cliente']) ?></td>
                        <td class="text-center"><span class="badge bg-primary"><?= h($cli['num_pedidos']) ?></span></td>
                        <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($cli['total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dashboard-card p-4">
            <h5 style="font-weight: 900; margin-bottom: 1rem;">📦 Top 5 Produtos do Mês</h5>
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProdutos as $prod): ?>
                    <tr>
                        <td style="font-weight: 700;"><?= h($prod['produto']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= number_format($prod['quantidade'], 0, ',', '.') ?></span></td>
                        <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($prod['total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Evolução
const ctxEvolution = document.getElementById('chartEvolution');
const evolutionData = <?= json_encode($evolutionData) ?>;

new Chart(ctxEvolution, {
    type: 'line',
    data: {
        labels: evolutionData.map(d => d.month),
        datasets: [
            {
                label: 'Vendas',
                data: evolutionData.map(d => d.vendas),
                borderColor: '#059669',
                backgroundColor: 'rgba(5, 150, 105, 0.1)',
                tension: 0.4,
                fill: true
            }<?php if ($isFinance): ?>,
            {
                label: 'Lucro',
                data: evolutionData.map(d => d.lucro),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }
            <?php endif; ?>
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => 'R$ ' + value.toLocaleString('pt-BR')
                }
            }
        }
    }
});

// Gráfico de Status
const ctxStatus = document.getElementById('chartStatus');
const statusData = <?= json_encode($statusCounts) ?>;

new Chart(ctxStatus, {
    type: 'doughnut',
    data: {
        labels: Object.keys(statusData).map(s => s.toUpperCase()),
        datasets: [{
            data: Object.values(statusData),
            backgroundColor: ['#3b82f6', '#f59e0b', '#059669', '#8b5cf6', '#ef4444', '#6b7280']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
