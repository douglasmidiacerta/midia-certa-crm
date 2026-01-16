<?php
// RELATÓRIO FINANCEIRO COMPLETO - Braço Direito do CEO
require_login();
require_role(['admin', 'financeiro']);

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// 1. Resumo de Receitas (Contas a Receber)
$receitasRecebidas = $pdo->prepare("
  SELECT 
    COUNT(id) AS total_titulos,
    SUM(amount) AS total_recebido
  FROM ar_titles
  WHERE status = 'recebido'
    AND DATE(received_at) BETWEEN ? AND ?
");
$receitasRecebidas->execute([$from, $to]);
$receitas = $receitasRecebidas->fetch();

// 2. Resumo de Despesas (Contas a Pagar)
$despesasPagas = $pdo->prepare("
  SELECT 
    COUNT(id) AS total_titulos,
    SUM(amount) AS total_pago
  FROM ap_titles
  WHERE status = 'pago'
    AND DATE(paid_at) BETWEEN ? AND ?
");
$despesasPagas->execute([$from, $to]);
$despesas = $despesasPagas->fetch();

// 3. Fluxo de Caixa por Conta
$fluxoCaixa = $pdo->prepare("
  SELECT 
    a.id,
    a.name AS conta,
    a.type AS tipo,
    a.initial_balance AS saldo_inicial,
    (
      SELECT COALESCE(SUM(amount), 0)
      FROM cash_movements cm
      WHERE cm.account_id = a.id
        AND cm.movement_type = 'entrada'
        AND DATE(cm.created_at) <= ?
    ) AS total_entradas,
    (
      SELECT COALESCE(SUM(amount), 0)
      FROM cash_movements cm
      WHERE cm.account_id = a.id
        AND cm.movement_type = 'saida'
        AND DATE(cm.created_at) <= ?
    ) AS total_saidas,
    COALESCE(a.initial_balance, 0) + (
      SELECT COALESCE(SUM(amount), 0)
      FROM cash_movements cm
      WHERE cm.account_id = a.id
        AND cm.movement_type = 'entrada'
        AND DATE(cm.created_at) <= ?
    ) - (
      SELECT COALESCE(SUM(amount), 0)
      FROM cash_movements cm
      WHERE cm.account_id = a.id
        AND cm.movement_type = 'saida'
        AND DATE(cm.created_at) <= ?
    ) AS saldo_atual
  FROM accounts a
  WHERE a.active = 1
  ORDER BY saldo_atual DESC
");
$fluxoCaixa->execute([$to, $to, $to, $to]);
$contas = $fluxoCaixa->fetchAll();

// 4. Contas a Receber em Aberto
$aReceber = $pdo->prepare("
  SELECT 
    ar.id,
    ar.amount,
    ar.due_date,
    ar.kind,
    ar.method,
    o.code AS os_code,
    c.name AS cliente
  FROM ar_titles ar
  JOIN os o ON o.id = ar.os_id
  JOIN clients c ON c.id = o.client_id
  WHERE ar.status = 'aberto'
  ORDER BY ar.due_date ASC
  LIMIT 50
");
$aReceber->execute();
$titulosReceber = $aReceber->fetchAll();

// 5. Contas a Pagar em Aberto
$aPagar = $pdo->prepare("
  SELECT 
    ap.id,
    ap.description,
    ap.amount,
    ap.due_date,
    COALESCE(s.name, 'Diversos') AS fornecedor,
    CASE ap.category
      WHEN 'product_costs' THEN 'Produtos'
      WHEN 'operating_expenses' THEN 'Operacional'
      WHEN 'taxes' THEN 'Impostos'
      WHEN 'marketing' THEN 'Marketing'
      ELSE 'Outros'
    END AS categoria
  FROM ap_titles ap
  LEFT JOIN suppliers s ON s.id = ap.supplier_id
  WHERE ap.status = 'aberto'
  ORDER BY ap.due_date ASC
  LIMIT 50
");
$aPagar->execute();
$titulosPagar = $aPagar->fetchAll();

// 6. Totais de Contas Abertas
$totaisAbertos = $pdo->query("
  SELECT 
    (SELECT COALESCE(SUM(amount), 0) FROM ar_titles WHERE status = 'aberto') AS total_a_receber,
    (SELECT COALESCE(SUM(amount), 0) FROM ap_titles WHERE status = 'aberto') AS total_a_pagar
")->fetch();

// 7. OS por Status (visão operacional)
$osPorStatus = $pdo->query("
  SELECT 
    status,
    COUNT(*) AS quantidade,
    CASE status
      WHEN 'atendimento' THEN 1
      WHEN 'arte' THEN 2
      WHEN 'conferencia' THEN 3
      WHEN 'producao' THEN 4
      WHEN 'disponivel' THEN 5
      WHEN 'finalizada' THEN 6
      WHEN 'cancelada' THEN 7
    END AS ordem
  FROM os
  WHERE status NOT IN ('finalizada', 'cancelada')
  GROUP BY status
  ORDER BY ordem
")->fetchAll();

// 8. Inadimplência (títulos vencidos)
$inadimplencia = $pdo->query("
  SELECT 
    ar.id,
    ar.amount,
    ar.due_date,
    DATEDIFF(CURDATE(), ar.due_date) AS dias_atraso,
    o.code AS os_code,
    c.name AS cliente,
    c.whatsapp
  FROM ar_titles ar
  JOIN os o ON o.id = ar.os_id
  JOIN clients c ON c.id = o.client_id
  WHERE ar.status = 'aberto'
    AND ar.due_date < CURDATE()
  ORDER BY dias_atraso DESC
  LIMIT 30
")->fetchAll();

// Cálculos
$saldoPeriodo = ($receitas['total_recebido'] ?? 0) - ($despesas['total_pago'] ?? 0);
$saldoTotal = array_sum(array_column($contas, 'saldo_atual'));
$resultadoLiquidacao = $saldoTotal + ($totaisAbertos['total_a_receber'] ?? 0) - ($totaisAbertos['total_a_pagar'] ?? 0);
$totalInadimplencia = array_sum(array_column($inadimplencia, 'amount'));
?>

<style>
.report-card {
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}
.metric-big { font-size: 2rem; font-weight: 900; }
.metric-label { font-size: 0.85rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
.section-title { font-weight: 900; font-size: 1.2rem; margin-bottom: 1rem; color: #1f2937; }
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 700;
}
</style>

<!-- Header -->
<div class="card report-card p-4 mb-4">
  <h4 class="section-title mb-1">💼 Relatório Financeiro Completo</h4>
  <p class="text-muted small mb-3">Visão 360º do financeiro - O braço direito do CEO</p>
  
  <form class="row g-3" method="get">
    <input type="hidden" name="page" value="reports_finance_advanced">
    <div class="col-md-4">
      <label class="form-label metric-label">Data Inicial</label>
      <input class="form-control" type="date" name="from" value="<?= h($from) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label metric-label">Data Final</label>
      <input class="form-control" type="date" name="to" value="<?= h($to) ?>" required>
    </div>
    <div class="col-md-4 align-self-end">
      <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    </div>
  </form>
</div>

<!-- Resumo Executivo -->
<div class="card report-card p-4 mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
  <h5 class="mb-4" style="font-weight: 900; color: white;">📊 RESUMO EXECUTIVO DO PERÍODO</h5>
  <div class="row g-4">
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Receitas Recebidas</div>
      <div class="metric-big"><?= h(money($receitas['total_recebido'])) ?></div>
      <small style="opacity: 0.8;"><?= h($receitas['total_titulos']) ?> títulos</small>
    </div>
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Despesas Pagas</div>
      <div class="metric-big"><?= h(money($despesas['total_pago'])) ?></div>
      <small style="opacity: 0.8;"><?= h($despesas['total_titulos']) ?> pagamentos</small>
    </div>
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Saldo do Período</div>
      <div class="metric-big <?= $saldoPeriodo >= 0 ? '' : 'text-danger' ?>"><?= h(money($saldoPeriodo)) ?></div>
      <small style="opacity: 0.8;">receitas - despesas</small>
    </div>
    <div class="col-md-3">
      <div class="metric-label" style="color: rgba(255,255,255,0.8);">Saldo em Caixa</div>
      <div class="metric-big"><?= h(money($saldoTotal)) ?></div>
      <small style="opacity: 0.8;">total disponível</small>
    </div>
  </div>
</div>

<!-- Alertas Críticos -->
<?php if (!empty($inadimplencia) || $resultadoLiquidacao < 0): ?>
<div class="alert alert-danger" style="border-left: 4px solid #dc2626;">
  <h6 style="font-weight: 900;">🚨 ALERTAS CRÍTICOS</h6>
  <ul class="mb-0">
    <?php if (!empty($inadimplencia)): ?>
      <li><strong>Inadimplência detectada:</strong> <?= count($inadimplencia) ?> títulos vencidos totalizando <strong><?= h(money($totalInadimplencia)) ?></strong></li>
    <?php endif; ?>
    <?php if ($resultadoLiquidacao < 0): ?>
      <li><strong>Resultado de liquidação negativo:</strong> <?= h(money($resultadoLiquidacao)) ?> - Ação imediata necessária!</li>
    <?php endif; ?>
  </ul>
</div>
<?php endif; ?>

<!-- Posição de Caixa -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">💵 Posição de Caixa por Conta</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead style="background: #f9fafb;">
        <tr>
          <th>Conta</th>
          <th>Tipo</th>
          <th class="text-end">Entradas (Acum.)</th>
          <th class="text-end">Saídas (Acum.)</th>
          <th class="text-end">Saldo Atual</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contas as $conta): ?>
        <tr>
          <td style="font-weight: 700;"><?= h($conta['conta']) ?></td>
          <td><span class="badge bg-secondary"><?= strtoupper(h($conta['tipo'])) ?></span></td>
          <td class="text-end" style="color: #059669;"><?= h(money($conta['total_entradas'])) ?></td>
          <td class="text-end" style="color: #dc2626;"><?= h(money($conta['total_saidas'])) ?></td>
          <td class="text-end" style="font-weight: 900; font-size: 1.1rem; color: <?= $conta['saldo_atual'] >= 0 ? '#059669' : '#dc2626' ?>;">
            <?= h(money($conta['saldo_atual'])) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot style="background: #f9fafb;">
        <tr>
          <th colspan="4">TOTAL EM CAIXA</th>
          <th class="text-end" style="font-size: 1.3rem; color: <?= $saldoTotal >= 0 ? '#059669' : '#dc2626' ?>;">
            <?= h(money($saldoTotal)) ?>
          </th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Liquidação e Panorama -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card p-3 h-100" style="border-left: 4px solid #059669;">
      <div class="metric-label">Total a Receber</div>
      <div style="font-size: 1.8rem; font-weight: 900; color: #059669;"><?= h(money($totaisAbertos['total_a_receber'])) ?></div>
      <small class="text-muted">Títulos em aberto</small>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 h-100" style="border-left: 4px solid #dc2626;">
      <div class="metric-label">Total a Pagar</div>
      <div style="font-size: 1.8rem; font-weight: 900; color: #dc2626;"><?= h(money($totaisAbertos['total_a_pagar'])) ?></div>
      <small class="text-muted">Obrigações pendentes</small>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 h-100" style="border-left: 4px solid <?= $resultadoLiquidacao >= 0 ? '#059669' : '#dc2626' ?>;">
      <div class="metric-label">Resultado Liquidação</div>
      <div style="font-size: 1.8rem; font-weight: 900; color: <?= $resultadoLiquidacao >= 0 ? '#059669' : '#dc2626' ?>;">
        <?= h(money($resultadoLiquidacao)) ?>
      </div>
      <small class="text-muted">Caixa + A Receber - A Pagar</small>
    </div>
  </div>
</div>

<!-- Inadimplência -->
<?php if (!empty($inadimplencia)): ?>
<div class="card report-card p-4 mb-4" style="border-left: 4px solid #dc2626;">
  <h5 class="section-title text-danger">🚨 INADIMPLÊNCIA - Títulos Vencidos</h5>
  <div class="alert alert-danger mb-3">
    <strong>Total em atraso:</strong> <?= h(money($totalInadimplencia)) ?> em <?= count($inadimplencia) ?> títulos
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle table-sm">
      <thead style="background: #fef2f2;">
        <tr>
          <th>OS</th>
          <th>Cliente</th>
          <th>WhatsApp</th>
          <th class="text-end">Valor</th>
          <th>Vencimento</th>
          <th class="text-center">Dias Atraso</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($inadimplencia as $inad): ?>
        <tr>
          <td><a href="?page=os_view&id=<?= h($inad['id']) ?>"><strong><?= h($inad['os_code']) ?></strong></a></td>
          <td style="font-weight: 700;"><?= h($inad['cliente']) ?></td>
          <td><small><?= h($inad['whatsapp']) ?></small></td>
          <td class="text-end" style="font-weight: 900; color: #dc2626;"><?= h(money($inad['amount'])) ?></td>
          <td class="small"><?= date('d/m/Y', strtotime($inad['due_date'])) ?></td>
          <td class="text-center">
            <span class="badge <?= $inad['dias_atraso'] > 30 ? 'bg-danger' : ($inad['dias_atraso'] > 15 ? 'bg-warning' : 'bg-secondary') ?>">
              <?= h($inad['dias_atraso']) ?> dias
            </span>
          </td>
          <td>
            <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $inad['whatsapp']) ?>" target="_blank" class="btn btn-sm btn-success">
              <i class="bi bi-whatsapp"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="alert alert-warning mt-3 mb-0 small">
    <strong>💡 Ação Recomendada:</strong> Entre em contato com os clientes em atraso imediatamente. Use o botão WhatsApp para facilitar.
  </div>
</div>
<?php endif; ?>

<!-- Contas a Receber (Próximas 50) -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">📥 Contas a Receber - Próximas 50</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle table-sm">
      <thead style="background: #f0fdf4;">
        <tr>
          <th>OS</th>
          <th>Cliente</th>
          <th>Tipo</th>
          <th>Método</th>
          <th class="text-end">Valor</th>
          <th>Vencimento</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($titulosReceber as $rec): 
          $vencido = strtotime($rec['due_date']) < time();
        ?>
        <tr class="<?= $vencido ? 'table-danger' : '' ?>">
          <td><a href="?page=os_view&id=<?= h($rec['id']) ?>"><strong><?= h($rec['os_code']) ?></strong></a></td>
          <td><?= h($rec['cliente']) ?></td>
          <td><span class="badge bg-info"><?= strtoupper(h($rec['kind'])) ?></span></td>
          <td class="small"><?= strtoupper(h($rec['method'])) ?></td>
          <td class="text-end" style="font-weight: 700; color: #059669;"><?= h(money($rec['amount'])) ?></td>
          <td class="small"><?= $rec['due_date'] ? date('d/m/Y', strtotime($rec['due_date'])) : '-' ?></td>
          <td><?= $vencido ? '<span class="badge bg-danger">VENCIDO</span>' : '<span class="badge bg-success">EM DIA</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Contas a Pagar (Próximas 50) -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">📤 Contas a Pagar - Próximas 50</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle table-sm">
      <thead style="background: #fef2f2;">
        <tr>
          <th>Descrição</th>
          <th>Fornecedor</th>
          <th>Categoria</th>
          <th class="text-end">Valor</th>
          <th>Vencimento</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($titulosPagar as $pag): 
          $vencido = $pag['due_date'] && strtotime($pag['due_date']) < time();
        ?>
        <tr class="<?= $vencido ? 'table-danger' : '' ?>">
          <td><?= h($pag['description']) ?></td>
          <td class="small"><?= h($pag['fornecedor']) ?></td>
          <td><span class="badge bg-secondary small"><?= h($pag['categoria']) ?></span></td>
          <td class="text-end" style="font-weight: 700; color: #dc2626;"><?= h(money($pag['amount'])) ?></td>
          <td class="small"><?= $pag['due_date'] ? date('d/m/Y', strtotime($pag['due_date'])) : '-' ?></td>
          <td><?= $vencido ? '<span class="badge bg-danger">VENCIDO</span>' : '<span class="badge bg-warning">PENDENTE</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Status Operacional (OS) -->
<div class="card report-card p-4 mb-4">
  <h5 class="section-title">🔄 Status Operacional - OS em Andamento</h5>
  <div class="row g-3">
    <?php foreach ($osPorStatus as $status): ?>
    <div class="col-md-2">
      <div class="card p-3 text-center h-100">
        <div class="text-muted small mb-1"><?= strtoupper(h($status['status'])) ?></div>
        <div style="font-size: 2rem; font-weight: 900; color: #667eea;"><?= h($status['quantidade']) ?></div>
        <small class="text-muted">OS</small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="alert alert-info mt-3 mb-0 small">
    <strong>📋 Acompanhamento:</strong> Mantenha as OS fluindo pelos status. Gargalos operacionais impactam diretamente no fluxo de caixa.
  </div>
</div>

<!-- Insights Estratégicos -->
<div class="card report-card p-4" style="border: 2px solid #e5e7eb;">
  <h5 class="section-title">💡 INSIGHTS E RECOMENDAÇÕES</h5>
  <div class="alert <?= $saldoPeriodo >= 0 ? 'alert-success' : 'alert-danger' ?>">
    <h6 style="font-weight: 900;">
      <?php if ($saldoPeriodo >= 0): ?>
        ✅ Período POSITIVO - Receitas > Despesas
      <?php else: ?>
        🚨 Período NEGATIVO - Despesas > Receitas
      <?php endif; ?>
    </h6>
    <hr>
    <ul class="mb-0" style="padding-left: 1.2rem;">
      <li><strong>Receitas recebidas:</strong> <?= h(money($receitas['total_recebido'])) ?></li>
      <li><strong>Despesas pagas:</strong> <?= h(money($despesas['total_pago'])) ?></li>
      <li><strong>Saldo do período:</strong> <?= h(money($saldoPeriodo)) ?></li>
      <li><strong>Disponível em caixa:</strong> <?= h(money($saldoTotal)) ?></li>
      <li><strong>Resultado se liquidar tudo:</strong> <?= h(money($resultadoLiquidacao)) ?></li>
      
      <?php if (!empty($inadimplencia)): ?>
        <li style="color: #dc2626;"><strong>⚠ URGENTE:</strong> <?= count($inadimplencia) ?> títulos vencidos - Total: <?= h(money($totalInadimplencia)) ?></li>
      <?php endif; ?>
      
      <?php if ($resultadoLiquidacao < 0): ?>
        <li style="color: #dc2626;"><strong>🚨 CRÍTICO:</strong> Resultado de liquidação negativo! Acelere recebimentos e negocie prazos com fornecedores.</li>
      <?php elseif ($resultadoLiquidacao < $totaisAbertos['total_a_receber'] * 0.3): ?>
        <li style="color: #f59e0b;"><strong>⚠ ATENÇÃO:</strong> Capital de giro apertado. Mantenha foco na cobrança.</li>
      <?php else: ?>
        <li style="color: #059669;"><strong>✅ SAUDÁVEL:</strong> Boa posição de caixa e liquidez adequada.</li>
      <?php endif; ?>
    </ul>
  </div>
</div>
