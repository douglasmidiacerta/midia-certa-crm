<?php
// ob_start(); // COMENTADO TEMPORARIAMENTE PARA DEBUG
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/auth.php';

require_login();
$user = $user ?? current_user();
$isFinance = can_finance() || can_admin();
$isSales = can_sales() || can_admin();

// **FIX #1: Definir as variáveis de data que faltavam**
$from = date('Y-m-01');  // Primeiro dia do mês
$to = date('Y-m-d');      // Dia de hoje

try {
    // Buscar pedidos pendentes vindos do site
    $pedidosPendentes = $pdo->query("
        SELECT os.*, c.name as clientname, c.phone as clientphone, c.email as clientemail 
        FROM os 
        JOIN clients c ON c.id = os.client_id 
        WHERE os.status IN ('pedido_pendente', 'pedidopendente')
        AND (os.origem = 'site' OR os.origem IS NULL)
        ORDER BY os.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    // Se coluna origem não existe, buscar só por status
    $pedidosPendentes = $pdo->query("
        SELECT os.*, c.name as clientname, c.phone as clientphone, c.email as clientemail 
        FROM os 
        JOIN clients c ON c.id = os.client_id 
        WHERE os.status IN ('pedido_pendente', 'pedidopendente')
        ORDER BY os.created_at DESC
    ")->fetchAll();
}

// **FIX #2: Corrigir queries de status**
// 1. Total de OS por status
$osStatus = $pdo->query("
    SELECT status, COUNT(*) as total 
    FROM os 
    WHERE DATE(created_at) >= '$from'
    GROUP BY status
")->fetchAll();

$statusCounts = [];
foreach ($osStatus as $s) {
    $statusCounts[$s['status']] = $s['total'];
}

// 2. Faturamento do mês
$faturamento = $pdo->query("
    SELECT COALESCE(SUM(ol.qty * ol.unit_price), 0) as total 
    FROM os o 
    LEFT JOIN os_lines ol ON ol.os_id = o.id 
    WHERE o.status NOT IN ('cancelada', 'excluida') 
    AND DATE(o.created_at) BETWEEN '$from' AND '$to'
")->fetch()['total'] ?? 0;

// 3. Lucro do mês (só para financeiro/admin)
$lucro = 0;
$margem = 0;
if ($isFinance) {
    $lucroData = $pdo->query("
        SELECT 
            COALESCE(SUM(ol.qty * ol.unit_price), 0) as receita,
            COALESCE(SUM(ol.qty * ol.unit_cost), 0) as custo
        FROM os o 
        LEFT JOIN os_lines ol ON ol.os_id = o.id 
        WHERE o.status NOT IN ('cancelada', 'excluida') 
        AND DATE(o.created_at) BETWEEN '$from' AND '$to'
    ")->fetch();
    
    $lucro = ($lucroData['receita'] ?? 0) - ($lucroData['custo'] ?? 0);
    $margem = ($lucroData['receita'] ?? 0) > 0 ? ($lucro / $lucroData['receita']) * 100 : 0;
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
            COUNT(DISTINCT o.id) as numvendas
        FROM os o 
        LEFT JOIN os_lines ol ON ol.os_id = o.id 
        WHERE o.status NOT IN ('cancelada', 'excluida')
        AND DATE(o.created_at) BETWEEN '$monthStart' AND '$monthEnd'
    ")->fetch();
    
    $evolutionData[$month] = [
        'month' => date('M/y', strtotime($monthStart)),
        'vendas' => $data['vendas'] ?? 0,
        'custos' => $data['custos'] ?? 0,
        'lucro' => ($data['vendas'] ?? 0) - ($data['custos'] ?? 0),
        'numvendas' => $data['numvendas'] ?? 0
    ];
}

// 5. Top 5 clientes do mês
$topClientes = $pdo->query("
    SELECT 
        c.name as cliente, 
        COUNT(DISTINCT o.id) as numpedidos, 
        COALESCE(SUM(ol.qty * ol.unit_price), 0) as total 
    FROM os o 
    JOIN clients c ON c.id = o.client_id 
    LEFT JOIN os_lines ol ON ol.os_id = o.id 
    WHERE o.status NOT IN ('cancelada', 'excluida')
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
    WHERE o.status NOT IN ('cancelada', 'excluida')
    AND DATE(o.created_at) BETWEEN '$from' AND '$to'
    GROUP BY i.id 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

// 7. Financeiro - Contas a receber/pagar
$contasReceber = 0;
$contasPagar = 0;
$saldoCaixa = 0;

if ($isFinance) {
    $contasReceber = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM ar_titles 
        WHERE status = 'aberto'
    ")->fetch()['total'] ?? 0;
    
    $contasPagar = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM ap_titles 
        WHERE status = 'aberto'
    ")->fetch()['total'] ?? 0;
    
    $saldoCaixa = $pdo->query("
        SELECT COALESCE(
            SUM(CASE WHEN movement_type = 'entrada' THEN amount ELSE 0 END) - 
            SUM(CASE WHEN movement_type = 'saida' THEN amount ELSE 0 END), 
            0
        ) as total 
        FROM cash_movements
    ")->fetch()['total'] ?? 0;
}

// 7.1 Alertas automáticos
$alertas = [
    'aprovacao_pendente' => (int) $pdo->query("SELECT COUNT(*) FROM os WHERE status IN ('arte', 'aguardando_aprovacao')")->fetchColumn(),
    'receber_vencido' => (int) $pdo->query("SELECT COUNT(*) FROM ar_titles WHERE status = 'aberto' AND due_date IS NOT NULL AND due_date < CURDATE()")->fetchColumn(),
    'pagar_vencido' => (int) $pdo->query("SELECT COUNT(*) FROM ap_titles WHERE status = 'aberto' AND due_date IS NOT NULL AND due_date < CURDATE()")->fetchColumn(),
    'conferencia_atrasada' => (int) $pdo->query("SELECT COUNT(*) FROM os WHERE status = 'conferencia' AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)")->fetchColumn(),
];

// 8. Performance do vendedor (se for vendedor)
$minhasVendas = 0;
$meuFaturamento = 0;

if ($isSales && !can_admin()) {
    $myStats = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as vendas,
            COALESCE(SUM(ol.qty * ol.unit_price), 0) as faturamento
        FROM os o 
        LEFT JOIN os_lines ol ON ol.os_id = o.id 
        WHERE o.seller_user_id = ? 
        AND o.status NOT IN ('cancelada', 'excluida')
        AND DATE(o.created_at) BETWEEN ? AND ?
    ");
    $myStats->execute([$user['id'], $from, $to]);
    $myData = $myStats->fetch();
    
    $minhasVendas = $myData['vendas'] ?? 0;
    $meuFaturamento = $myData['faturamento'] ?? 0;
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
    <h3 style="font-weight: 900;">Olá, <?php echo htmlspecialchars($user['name'] ?? 'Usuário'); ?>! 👋</h3>
    <p class="text-muted">Bem-vindo ao painel de controle. Aqui está o resumo do mês atual.</p>
</div>

<!-- Alerta de Pedidos Pendentes -->
<?php if (!empty($pedidosPendentes)) { ?>
    <div class="alert alert-warning alert-dismissible fade show mb-4" style="border-left: 5px solid #f59e0b; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <div class="d-flex align-items-start">
            <div style="font-size: 2.5rem; margin-right: 20px;">⚠️</div>
            <div class="flex-grow-1">
                <h4 class="alert-heading fw-bold mb-2"><?php echo count($pedidosPendentes); ?> Pedidos Aguardando Aprovação</h4>
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
                            <?php foreach ($pedidosPendentes as $ped) { ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ped['code'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ped['clientname'] ?? 'Desconhecido'); ?></td>
                                    <td>
                                        <?php if ($ped['clientphone'] ?? null) { ?>
                                            <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $ped['clientphone']); ?>" target="_blank" class="btn btn-sm btn-success">WhatsApp</a>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ped['created_at'] ?? 'now')); ?></td>
                                    <td><?php echo htmlspecialchars($ped['pagamento_preferencial'] ?? 'A definir'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_PATH; ?>app.php?page=os_view&id=<?php echo (int)$ped['id']; ?>" class="btn btn-sm btn-primary">Analisar</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<!-- Alertas Automáticos (Financeiro) -->
<?php if ($isFinance) { ?>
    <div class="card p-3 mb-4" style="border-left: 4px solid #ef4444;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 style="font-weight: 900; margin-bottom: 0;">⚡ Alertas Automáticos</h5>
            </div>
            <div class="text-muted small">Atenção a pendências operacionais e financeiras</div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-3 mb-2">
                <div class="p-2 bg-light rounded">
                    <div class="text-muted small">Aprovação pendente</div>
                    <div style="font-weight: 900; font-size: 1.2rem;"><?php echo (int)$alertas['aprovacao_pendente']; ?></div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="p-2 bg-light rounded">
                    <div class="text-muted small">A receber vencido</div>
                    <div style="font-weight:
// ob_end_flush(); // COMENTADO TEMPORARIAMENTE
