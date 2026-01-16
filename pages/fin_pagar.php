<?php
require_role(['admin','financeiro']);

// Processamento de ações
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? '';
  
  // Lançamento manual de conta a pagar
  if($action === 'create_manual'){
    $description = trim($_POST['description'] ?? '');
    $amount = (float)str_replace(',', '.', ($_POST['amount'] ?? '0'));
    $due_date = $_POST['due_date'] ?? date('Y-m-d');
    $category = trim($_POST['category'] ?? '');
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    
    if(empty($description) || $amount <= 0){
      flash_set('danger', 'Preencha descrição e valor.');
    } else {
      $st = $pdo->prepare("INSERT INTO ap_titles (description, amount, due_date, status, category, supplier_id, created_at)
                          VALUES (?, ?, ?, 'aberto', ?, ?, NOW())");
      $st->execute([$description, $amount, $due_date, $category, $supplier_id > 0 ? $supplier_id : null]);
      flash_set('success', 'Conta a pagar lançada com sucesso!');
      redirect($base.'/app.php?page=fin_pagar');
    }
  }
  
  // Baixa de conta a pagar
  if($action === 'pay'){
    $id = (int)($_POST['id'] ?? 0);
    $account_id = (int)($_POST['account_id'] ?? 0);
    $paid_amount = (float)str_replace(',', '.', ($_POST['paid_amount'] ?? '0'));
    
    if(!$id || !$account_id || $paid_amount <= 0){
      flash_set('danger', 'Preencha todos os campos obrigatórios.');
    } else {
      $pdo->beginTransaction();
      
      // Atualiza conta a pagar
      $st = $pdo->prepare("UPDATE ap_titles SET status='pago', paid_amount=?, paid_at=NOW(), paid_by_user_id=?, account_id=? WHERE id=?");
      $st->execute([$paid_amount, user_id(), $account_id, $id]);
      
      // Registra movimentação de saída no caixa
      $st = $pdo->prepare("SELECT description, category FROM ap_titles WHERE id=?");
      $st->execute([$id]);
      $ap = $st->fetch();
      
      $st = $pdo->prepare("INSERT INTO cash_movements (account_id, movement_type, amount, description, category, reference_type, reference_id, created_by_user_id, created_at)
                          VALUES (?, 'saida', ?, ?, ?, 'ap_title', ?, ?, NOW())");
      $st->execute([$account_id, $paid_amount, $ap['description'], $ap['category'], $id, user_id()]);
      
      $pdo->commit();
      flash_set('success', 'Pagamento registrado com sucesso!');
      redirect($base.'/app.php?page=fin_pagar');
    }
  }
}

// Filtros
$status_filter = $_GET['status'] ?? 'aberto';
$category_filter = $_GET['category'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = "1=1";
$params = [];

if($status_filter && $status_filter !== 'todos'){
  $where .= " AND t.status=?";
  $params[] = $status_filter;
}

if($category_filter){
  $where .= " AND t.category=?";
  $params[] = $category_filter;
}

if($q){
  $where .= " AND (t.description LIKE ? OR s.name LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

// Busca contas a pagar
$st = $pdo->prepare("SELECT t.*, s.name as supplier_name, a.name as account_name, u.name as paid_by_name
                    FROM ap_titles t
                    LEFT JOIN suppliers s ON s.id = t.supplier_id
                    LEFT JOIN accounts a ON a.id = t.account_id
                    LEFT JOIN users u ON u.id = t.paid_by_user_id
                    WHERE $where
                    ORDER BY 
                      CASE WHEN t.status='aberto' THEN 0 ELSE 1 END,
                      t.due_date ASC");
$st->execute($params);
$rows = $st->fetchAll();

// Busca fornecedores para o form
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE active=1 ORDER BY name")->fetchAll();

// Busca contas para baixa
$accounts = $pdo->query("SELECT id, name FROM accounts WHERE active=1 ORDER BY name")->fetchAll();

// Busca categorias
$categories = $pdo->query("SELECT DISTINCT name FROM expense_categories WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Totais
$total_aberto = 0;
$total_pago = 0;
foreach($rows as $r){
  if($r['status'] === 'aberto'){
    $total_aberto += $r['amount'];
  } else {
    $total_pago += $r['paid_amount'];
  }
}

$f = flash_get();
?>

<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h5 style="font-weight:900">💸 Contas a Pagar</h5>
      <p class="text-muted small mb-0">Gerencie pagamentos a fornecedores e despesas</p>
    </div>
    <div>
      <button class="btn btn-primary" onclick="document.getElementById('formBox').style.display='block'">
        + Lançamento Manual
      </button>
    </div>
  </div>
</div>

<?php if($f): ?>
  <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
<?php endif; ?>

<!-- Formulário de Lançamento Manual -->
<div class="card p-3 mb-3" id="formBox" style="display:none;">
  <h6>➕ Novo Lançamento Manual</h6>
  <form method="post" class="row g-3">
    <input type="hidden" name="action" value="create_manual">
    
    <div class="col-md-6">
      <label class="form-label">Descrição *</label>
      <input type="text" name="description" class="form-control" required placeholder="Ex: Aluguel do mês">
    </div>
    
    <div class="col-md-2">
      <label class="form-label">Valor (R$) *</label>
      <input type="text" name="amount" class="form-control" required placeholder="0,00">
    </div>
    
    <div class="col-md-2">
      <label class="form-label">Vencimento *</label>
      <input type="date" name="due_date" class="form-control" required value="<?= date('Y-m-d') ?>">
    </div>
    
    <div class="col-md-3">
      <label class="form-label">Categoria</label>
      <select name="category" class="form-select">
        <option value="">Nenhuma</option>
        <?php foreach($categories as $cat): ?>
          <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="col-md-3">
      <label class="form-label">Fornecedor (Opcional)</label>
      <select name="supplier_id" class="form-select">
        <option value="">Nenhum</option>
        <?php foreach($suppliers as $sup): ?>
          <option value="<?= h($sup['id']) ?>"><?= h($sup['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Lançar</button>
      <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('formBox').style.display='none'">Cancelar</button>
    </div>
  </form>
</div>

<!-- Cards de Totais -->
<div class="row mb-3">
  <div class="col-md-6">
    <div class="card p-3 bg-danger bg-opacity-10">
      <div class="text-muted small">Total em Aberto</div>
      <div class="h4 mb-0 text-danger">R$ <?= number_format($total_aberto, 2, ',', '.') ?></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3 bg-success bg-opacity-10">
      <div class="text-muted small">Total Pago</div>
      <div class="h4 mb-0 text-success">R$ <?= number_format($total_pago, 2, ',', '.') ?></div>
    </div>
  </div>
</div>

<!-- Filtros -->
<div class="card p-3 mb-3">
  <form class="row g-2" method="get">
    <input type="hidden" name="page" value="fin_pagar">
    
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="todos" <?= $status_filter === 'todos' ? 'selected' : '' ?>>Todos os Status</option>
        <option value="aberto" <?= $status_filter === 'aberto' ? 'selected' : '' ?>>Em Aberto</option>
        <option value="pago" <?= $status_filter === 'pago' ? 'selected' : '' ?>>Pagos</option>
      </select>
    </div>
    
    <div class="col-md-3">
      <select name="category" class="form-select">
        <option value="">Todas Categorias</option>
        <?php foreach($categories as $cat): ?>
          <option value="<?= h($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Buscar descrição ou fornecedor" value="<?= h($q) ?>">
    </div>
    
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">Filtrar</button>
    </div>
  </form>
</div>

<!-- Lista de Contas a Pagar -->
<div class="card p-3">
  <h6>📋 Lista de Contas</h6>
  
  <?php if(empty($rows)): ?>
    <div class="alert alert-info">
      Nenhuma conta a pagar encontrada com os filtros aplicados.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>Descrição</th>
            <th>Fornecedor</th>
            <th>Categoria</th>
            <th>Vencimento</th>
            <th class="text-end">Valor</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): 
            $vencido = $r['status'] === 'aberto' && strtotime($r['due_date']) < time();
          ?>
            <tr class="<?= $vencido ? 'table-danger' : '' ?>">
              <td>
                <strong><?= h($r['description']) ?></strong>
                <?php if($r['is_card_tax']): ?>
                  <span class="badge bg-info">Taxa Cartão</span>
                <?php endif; ?>
              </td>
              <td><?= h($r['supplier_name']) ?></td>
              <td class="small text-muted"><?= h($r['category']) ?></td>
              <td>
                <?= date('d/m/Y', strtotime($r['due_date'])) ?>
                <?php if($vencido): ?>
                  <span class="badge bg-danger">Vencido</span>
                <?php endif; ?>
              </td>
              <td class="text-end"><strong>R$ <?= number_format($r['amount'], 2, ',', '.') ?></strong></td>
              <td>
                <?php if($r['status'] === 'aberto'): ?>
                  <span class="badge bg-warning">Aberto</span>
                <?php else: ?>
                  <span class="badge bg-success">Pago</span>
                  <div class="small text-muted">
                    <?= date('d/m/Y', strtotime($r['paid_at'])) ?>
                    <br><?= h($r['account_name']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if($r['status'] === 'aberto'): ?>
                  <button class="btn btn-sm btn-success" onclick="openPayModal(<?= h(json_encode($r)) ?>)">
                    💰 Pagar
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Modal de Pagamento -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Registrar Pagamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="id" id="payId">
        
        <div class="modal-body">
          <div class="alert alert-info">
            <strong id="payDescription"></strong><br>
            <span class="text-muted" id="payDetails"></span>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Conta/Caixa *</label>
            <select name="account_id" class="form-select" required>
              <option value="">Selecione...</option>
              <?php foreach($accounts as $acc): ?>
                <option value="<?= h($acc['id']) ?>"><?= h($acc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Valor Pago (R$) *</label>
            <input type="text" name="paid_amount" id="payAmount" class="form-control" required>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">Confirmar Pagamento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openPayModal(data) {
  document.getElementById('payId').value = data.id;
  document.getElementById('payDescription').textContent = data.description;
  document.getElementById('payDetails').textContent = 'Venc: ' + new Date(data.due_date).toLocaleDateString('pt-BR') + ' | Valor: R$ ' + parseFloat(data.amount).toLocaleString('pt-BR', {minimumFractionDigits: 2});
  document.getElementById('payAmount').value = parseFloat(data.amount).toFixed(2).replace('.', ',');
  
  const modal = new bootstrap.Modal(document.getElementById('payModal'));
  modal.show();
}
</script>
