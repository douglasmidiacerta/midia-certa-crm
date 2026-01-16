<?php
require_login();
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$doc_kind = $_GET['doc_kind'] ?? ''; // '' = todos, 'sale' = vendas, 'budget' = orçamentos
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$params = [];

$sql = "SELECT o.id,o.code,o.status,o.delivery_method,o.due_date,o.created_at,COALESCE(o.doc_kind,'sale') doc_kind,c.name client_name,u.name seller_name
        FROM os o
        JOIN clients c ON c.id=o.client_id
        JOIN users u ON u.id=o.seller_user_id
        WHERE 1=1 
        AND o.status != 'excluida' ";

// Vendas vê somente as próprias
if(can_sales() && !can_finance() && !can_admin()){
  $sql .= " AND o.seller_user_id = ? ";
  $params[] = user_id();
}

// Filtro de busca por código ou cliente
if($q){
  $sql .= " AND (o.code LIKE ? OR c.name LIKE ?) ";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

// Filtro por tipo de documento (venda ou orçamento)
if($doc_kind === 'sale'){
  $sql .= " AND COALESCE(o.doc_kind,'sale') = 'sale' ";
} elseif($doc_kind === 'budget'){
  $sql .= " AND COALESCE(o.doc_kind,'sale') = 'budget' ";
}

// Filtro por status
if($status){
  // Se filtrar por 'excluida', remove o filtro que exclui excluídas
  if($status === 'excluida'){
    $sql = str_replace("AND o.status != 'excluida'", "", $sql);
  }
  $sql .= " AND o.status = ? ";
  $params[] = $status;
}

// Filtro por data
if($date_from){
  $sql .= " AND DATE(o.created_at) >= ? ";
  $params[] = $date_from;
}
if($date_to){
  $sql .= " AND DATE(o.created_at) <= ? ";
  $params[] = $date_to;
}

$sql .= " ORDER BY o.id DESC LIMIT 500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Estatísticas rápidas (excluindo as excluídas)
$stats_sql = "SELECT 
  COUNT(*) as total,
  SUM(CASE WHEN COALESCE(o.doc_kind,'sale') = 'budget' THEN 1 ELSE 0 END) as orcamentos,
  SUM(CASE WHEN COALESCE(o.doc_kind,'sale') = 'sale' AND o.status = 'finalizada' THEN 1 ELSE 0 END) as finalizadas,
  SUM(CASE WHEN COALESCE(o.doc_kind,'sale') = 'sale' AND o.status IN ('producao','conferencia') THEN 1 ELSE 0 END) as em_producao,
  SUM(CASE WHEN COALESCE(o.doc_kind,'sale') = 'sale' AND o.status IN ('atendimento','refugado') THEN 1 ELSE 0 END) as abertas,
  SUM(CASE WHEN o.status = 'excluida' THEN 1 ELSE 0 END) as excluidas
  FROM os o
  JOIN clients c ON c.id=o.client_id
  WHERE o.status != 'excluida' ";

$stats_params = [];
if(can_sales() && !can_finance() && !can_admin()){
  $stats_sql .= " AND o.seller_user_id = ? ";
  $stats_params[] = user_id();
}

$stats_st = $pdo->prepare($stats_sql);
$stats_st->execute($stats_params);
$stats = $stats_st->fetch();
?>
<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 style="font-weight:900">Todas as O.S - Lista Unificada</h5>
    <?php if(can_sales()): ?>
      <a class="btn btn-primary" href="<?= h($base) ?>/app.php?page=os_new">+ Nova Venda/O.S</a>
    <?php endif; ?>
  </div>

  <!-- Estatísticas Rápidas -->
  <div class="row g-2 mt-3 mb-3">
    <div class="col">
      <div class="card text-center" style="border-left: 4px solid #6c757d;">
        <div class="card-body py-2">
          <h3 class="mb-0"><?= h($stats['total'] ?? 0) ?></h3>
          <small class="text-muted">Total</small>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card text-center" style="border-left: 4px solid #ffc107;">
        <div class="card-body py-2">
          <h3 class="mb-0"><?= h($stats['orcamentos'] ?? 0) ?></h3>
          <small class="text-muted">Orçamentos</small>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card text-center" style="border-left: 4px solid #17a2b8;">
        <div class="card-body py-2">
          <h3 class="mb-0"><?= h($stats['abertas'] ?? 0) ?></h3>
          <small class="text-muted">Abertas</small>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card text-center" style="border-left: 4px solid #fd7e14;">
        <div class="card-body py-2">
          <h3 class="mb-0"><?= h($stats['em_producao'] ?? 0) ?></h3>
          <small class="text-muted">Em Produção</small>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card text-center" style="border-left: 4px solid #28a745;">
        <div class="card-body py-2">
          <h3 class="mb-0"><?= h($stats['finalizadas'] ?? 0) ?></h3>
          <small class="text-muted">Finalizadas</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtros Avançados -->
  <form class="row g-2 mt-2">
    <input type="hidden" name="page" value="os">
    
    <div class="col-md-3">
      <label class="form-label small">Buscar</label>
      <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Código ou cliente">
    </div>
    
    <div class="col-md-2">
      <label class="form-label small">Tipo</label>
      <select class="form-select" name="doc_kind">
        <option value="">Todos</option>
        <option value="sale" <?= $doc_kind==='sale'?'selected':'' ?>>Vendas</option>
        <option value="budget" <?= $doc_kind==='budget'?'selected':'' ?>>Orçamentos</option>
        <option value="reaberta" <?= $doc_kind==='reaberta'?'selected':'' ?>>Reabertas</option>
      </select>
    </div>
    
    <div class="col-md-2">
      <label class="form-label small">Status</label>
      <select class="form-select" name="status">
        <option value="">Todos (ativos)</option>
        <?php foreach(['atendimento','aguardando_aprovacao','conferencia','producao','disponivel','finalizada','refugado','cancelada','excluida'] as $s): ?>
          <option value="<?= h($s) ?>" <?= $status===$s?'selected':'' ?>><?= strtoupper(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="col-md-2">
      <label class="form-label small">Data de</label>
      <input type="date" class="form-control" name="date_from" value="<?= h($date_from) ?>">
    </div>
    
    <div class="col-md-2">
      <label class="form-label small">Data até</label>
      <input type="date" class="form-control" name="date_to" value="<?= h($date_to) ?>">
    </div>
    
    <div class="col-md-1 d-flex align-items-end">
      <button class="btn btn-primary w-100">Filtrar</button>
    </div>
  </form>
  
  <div class="mt-2 mb-2">
    <small class="text-muted">Mostrando <?= count($rows) ?> resultados (limite: 500)</small>
    <?php if($q || $status || $doc_kind || $date_from || $date_to): ?>
      <a href="<?= h($base) ?>/app.php?page=os" class="btn btn-sm btn-outline-secondary ms-2">Limpar filtros</a>
    <?php endif; ?>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-sm table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Código</th>
          <th>Tipo</th>
          <th>Cliente</th>
          <th>Vendedor</th>
          <th>Status</th>
          <th>Entrega</th>
          <th>Data</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if(empty($rows)): ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            Nenhum pedido encontrado com os filtros selecionados.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><b>#<?= h($r['code']) ?></b></td>
            <td>
              <?php if($r['doc_kind']==='budget'): ?>
                <span class="badge bg-warning text-dark">ORÇAMENTO</span>
              <?php else: ?>
                <span class="badge bg-success">VENDA</span>
              <?php endif; ?>
            </td>
            <td><?= h($r['client_name']) ?></td>
            <td><?= h($r['seller_name']) ?></td>
            <td>
              <?php 
                $statusColors = [
                  'atendimento' => 'info',
                  'aguardando_aprovacao' => 'warning',
                  'conferencia' => 'primary',
                  'producao' => 'warning',
                  'disponivel' => 'success',
                  'finalizada' => 'secondary',
                  'refugado' => 'danger',
                  'cancelada' => 'dark'
                ];
                $statusLabels = [
                  'aguardando_aprovacao' => 'AGUARDANDO APROVAÇÃO'
                ];
                $color = $statusColors[$r['status']] ?? 'secondary';
                $label = $statusLabels[$r['status']] ?? strtoupper($r['status']);
              ?>
              <span class="badge bg-<?= $color ?>"><?= h($label) ?></span>
            </td>
            <td><?= h($r['delivery_method']) ?> <?= $r['due_date']?('• '.h($r['due_date'])):'' ?></td>
            <td class="text-muted small"><?= h(substr($r['created_at'],0,16)) ?></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($r['id']) ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

