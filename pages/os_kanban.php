<?php
$status_map = [
  'atendimento' => 'Aberto',
  'conferencia' => 'Análise de arquivo',
  'producao' => 'Produção',
  'disponivel' => 'Disponível',
  'refugado' => 'Refugado',
  'finalizada' => 'Finalizada'
];

$filter = $_GET['filter'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = "1=1";
$params = [];
if($q){
  $where .= " AND (o.code LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
  $params[] = "%$q%"; $params[]="%$q%"; $params[]="%$q%";
}
if($filter==='atrasadas'){
  $where .= " AND o.due_date IS NOT NULL AND o.due_date < CURDATE() AND o.status NOT IN ('finalizada','cancelada')";
}

$st = $pdo->prepare("SELECT o.*, c.name client_name, c.phone client_phone,
  (SELECT COALESCE(SUM(amount),0) FROM ar_titles WHERE os_id=o.id AND status='aberto') ar_open
  FROM os o JOIN clients c ON c.id=o.client_id
  WHERE $where
  ORDER BY o.created_at DESC");
$st->execute($params);
$rows = $st->fetchAll();

$by = [];
foreach($rows as $r){ $by[$r['status']][] = $r; }
?>
<div class="card p-3 mb-3">
  <form class="row g-2 align-items-center">
    <input type="hidden" name="page" value="os_kanban">
    <div class="col-md-5">
      <input class="form-control" name="q" placeholder="Buscar OS / Cliente / Telefone" value="<?= h($q) ?>">
    </div>
    <div class="col-md-3">
      <select class="form-select" name="filter">
        <option value="">Filtros</option>
        <option value="atrasadas" <?= $filter==='atrasadas'?'selected':'' ?>>Atrasadas</option>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-outline-primary">Filtrar</button>
      <a class="btn btn-primary" href="<?= h($base) ?>/app.php?page=os_new">+ Nova Venda</a>
    </div>
  </form>
</div>

<div class="kanban">
  <?php foreach($status_map as $key=>$label): ?>
    <div class="kan-col">
      <div class="kan-head">
        <div style="font-weight:900"><?= h($label) ?></div>
        <span class="badge text-bg-light"><?= count($by[$key] ?? []) ?></span>
      </div>
      <div class="kan-body">
        <?php foreach(($by[$key] ?? []) as $o): ?>
          <div class="os-card">
            <div class="os-card">
              <div class="d-flex justify-content-between">
                <div style="font-weight:900"><?= h($o['code']) ?></div>
                <div class="muted"><?= $o['due_date'] ? date_br($o['due_date']) : '' ?></div>
              </div>
              <div style="font-weight:700"><?= h($o['client_name']) ?></div>
              <div class="muted"><?= h($o['os_type']==='produto'?'Produto (revenda)':'Somente serviço') ?> • Prioridade: <?= h($o['priority']) ?></div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="muted">Pendência: <b><?= h(money($o['ar_open'])) ?></b></div>
                <a class="btn btn-sm btn-outline-primary" href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($o['id']) ?>">Abrir</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
