<?php
// Exportação do DRE - CSV, Excel, PDF
require_login();
require_role(['admin']);
require_once __DIR__ . '/../config/dre_service.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';

$dreService = new DreService($pdo);
$report = $dreService->getFullReport($from, $to);

$totalCosts = $report['product_costs'] + $report['operating_expenses'] + 
              $report['payroll_costs'] + $report['taxes'] + $report['marketing_costs'];
$grossProfit = $report['total_sales'] - $totalCosts;
$profitMargin = $report['total_sales'] > 0 ? ($grossProfit / $report['total_sales']) * 100 : 0;

// Exportação CSV
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DRE_' . $from . '_' . $to . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // Cabeçalho
    fputcsv($output, ['DRE PROFISSIONAL - Mídia Certa']);
    fputcsv($output, ['Período', date('d/m/Y', strtotime($from)) . ' até ' . date('d/m/Y', strtotime($to))]);
    fputcsv($output, []);
    
    // Resumo Executivo
    fputcsv($output, ['RESUMO EXECUTIVO']);
    fputcsv($output, ['Faturamento Total', number_format($report['total_sales'], 2, ',', '.')]);
    fputcsv($output, ['Custos Totais', number_format($totalCosts, 2, ',', '.')]);
    fputcsv($output, ['Lucro Líquido', number_format($grossProfit, 2, ',', '.')]);
    fputcsv($output, ['Margem de Lucro (%)', number_format($profitMargin, 1, ',', '.') . '%']);
    fputcsv($output, []);
    
    // Custos Detalhados
    fputcsv($output, ['CUSTOS E DESPESAS']);
    fputcsv($output, ['Custos de Produtos', number_format($report['product_costs'], 2, ',', '.')]);
    fputcsv($output, ['Despesas Operacionais', number_format($report['operating_expenses'], 2, ',', '.')]);
    fputcsv($output, ['Folha de Pagamento', number_format($report['payroll_costs'], 2, ',', '.')]);
    fputcsv($output, ['Impostos e Tributos', number_format($report['taxes'], 2, ',', '.')]);
    fputcsv($output, ['Marketing', number_format($report['marketing_costs'], 2, ',', '.')]);
    fputcsv($output, []);
    
    // Fornecedores
    fputcsv($output, ['COMPRAS POR FORNECEDOR']);
    fputcsv($output, ['Fornecedor', 'Nº Pagamentos', 'Total Pago']);
    foreach ($report['supplier_summary'] as $supplier) {
        fputcsv($output, [
            $supplier['supplier_name'],
            $supplier['payment_count'],
            number_format($supplier['total_paid'], 2, ',', '.')
        ]);
    }
    fputcsv($output, []);
    
    // Colaboradores
    fputcsv($output, ['CUSTO POR COLABORADOR']);
    fputcsv($output, ['Colaborador', 'Cargo', 'Salário', 'VR', 'VA', 'VT', 'Outros', 'Total/Mês', 'Custo Período']);
    foreach ($report['payroll_by_employee'] as $emp) {
        fputcsv($output, [
            $emp['name'],
            $emp['job_title'] ?? '-',
            number_format($emp['salary'], 2, ',', '.'),
            number_format($emp['vr_monthly'], 2, ',', '.'),
            number_format($emp['va_monthly'], 2, ',', '.'),
            number_format($emp['vt_monthly'], 2, ',', '.'),
            number_format($emp['other_benefits'], 2, ',', '.'),
            number_format($emp['monthly_total'], 2, ',', '.'),
            number_format($emp['period_cost'], 2, ',', '.')
        ]);
    }
    fputcsv($output, []);
    
    // Caixa e Liquidação
    fputcsv($output, ['POSIÇÃO DE CAIXA E LIQUIDAÇÃO']);
    fputcsv($output, ['Total em Caixa', number_format($report['total_cash_balance'], 2, ',', '.')]);
    fputcsv($output, ['Contas a Receber', number_format($report['accounts_receivable_total'], 2, ',', '.')]);
    fputcsv($output, ['Contas a Pagar', number_format($report['accounts_payable_total'], 2, ',', '.')]);
    fputcsv($output, ['Resultado Liquidação', number_format($report['liquidation_result'], 2, ',', '.')]);
    
    fclose($output);
    exit;
}

// Exportação Excel (HTML com mime type Excel)
if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="DRE_' . $from . '_' . $to . '.xls"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    ?>
    <html xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background-color: #4a5568; color: white; font-weight: bold; }
            .section-title { background-color: #667eea; color: white; font-weight: bold; font-size: 14pt; }
            .money { text-align: right; }
            .positive { color: green; font-weight: bold; }
            .negative { color: red; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>DRE PROFISSIONAL - Mídia Certa</h1>
        <p><strong>Período:</strong> <?= date('d/m/Y', strtotime($from)) ?> até <?= date('d/m/Y', strtotime($to)) ?></p>
        
        <h2>RESUMO EXECUTIVO</h2>
        <table>
            <tr><th>Métrica</th><th class="money">Valor</th></tr>
            <tr><td>Faturamento Total</td><td class="money positive">R$ <?= number_format($report['total_sales'], 2, ',', '.') ?></td></tr>
            <tr><td>Custos Totais</td><td class="money negative">R$ <?= number_format($totalCosts, 2, ',', '.') ?></td></tr>
            <tr><td>Lucro Líquido</td><td class="money <?= $grossProfit >= 0 ? 'positive' : 'negative' ?>">R$ <?= number_format($grossProfit, 2, ',', '.') ?></td></tr>
            <tr><td>Margem de Lucro</td><td class="money"><?= number_format($profitMargin, 1, ',', '.') ?>%</td></tr>
        </table>
        
        <h2>CUSTOS E DESPESAS</h2>
        <table>
            <tr><th>Centro de Custo</th><th class="money">Valor</th></tr>
            <tr><td>Custos de Produtos</td><td class="money">R$ <?= number_format($report['product_costs'], 2, ',', '.') ?></td></tr>
            <tr><td>Despesas Operacionais</td><td class="money">R$ <?= number_format($report['operating_expenses'], 2, ',', '.') ?></td></tr>
            <tr><td>Folha de Pagamento</td><td class="money">R$ <?= number_format($report['payroll_costs'], 2, ',', '.') ?></td></tr>
            <tr><td>Impostos e Tributos</td><td class="money">R$ <?= number_format($report['taxes'], 2, ',', '.') ?></td></tr>
            <tr><td>Marketing</td><td class="money">R$ <?= number_format($report['marketing_costs'], 2, ',', '.') ?></td></tr>
        </table>
        
        <h2>COMPRAS POR FORNECEDOR</h2>
        <table>
            <tr><th>Fornecedor</th><th>Nº Pagamentos</th><th class="money">Total Pago</th></tr>
            <?php foreach ($report['supplier_summary'] as $supplier): ?>
            <tr>
                <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                <td><?= $supplier['payment_count'] ?></td>
                <td class="money">R$ <?= number_format($supplier['total_paid'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2>CUSTO POR COLABORADOR</h2>
        <table>
            <tr><th>Colaborador</th><th>Cargo</th><th class="money">Salário</th><th class="money">VR</th><th class="money">VA</th><th class="money">VT</th><th class="money">Outros</th><th class="money">Total/Mês</th><th class="money">Custo Período</th></tr>
            <?php foreach ($report['payroll_by_employee'] as $emp): ?>
            <tr>
                <td><?= htmlspecialchars($emp['name']) ?></td>
                <td><?= htmlspecialchars($emp['job_title'] ?? '-') ?></td>
                <td class="money">R$ <?= number_format($emp['salary'], 2, ',', '.') ?></td>
                <td class="money">R$ <?= number_format($emp['vr_monthly'], 2, ',', '.') ?></td>
                <td class="money">R$ <?= number_format($emp['va_monthly'], 2, ',', '.') ?></td>
                <td class="money">R$ <?= number_format($emp['vt_monthly'], 2, ',', '.') ?></td>
                <td class="money">R$ <?= number_format($emp['other_benefits'], 2, ',', '.') ?></td>
                <td class="money">R$ <?= number_format($emp['monthly_total'], 2, ',', '.') ?></td>
                <td class="money">R$ <?= number_format($emp['period_cost'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2>POSIÇÃO DE CAIXA E LIQUIDAÇÃO</h2>
        <table>
            <tr><th>Item</th><th class="money">Valor</th></tr>
            <tr><td>Total em Caixa</td><td class="money positive">R$ <?= number_format($report['total_cash_balance'], 2, ',', '.') ?></td></tr>
            <tr><td>Contas a Receber</td><td class="money positive">R$ <?= number_format($report['accounts_receivable_total'], 2, ',', '.') ?></td></tr>
            <tr><td>Contas a Pagar</td><td class="money negative">R$ <?= number_format($report['accounts_payable_total'], 2, ',', '.') ?></td></tr>
            <tr><td>Resultado Liquidação</td><td class="money <?= $report['liquidation_result'] >= 0 ? 'positive' : 'negative' ?>">R$ <?= number_format($report['liquidation_result'], 2, ',', '.') ?></td></tr>
        </table>
    </body>
    </html>
    <?php
    exit;
}
