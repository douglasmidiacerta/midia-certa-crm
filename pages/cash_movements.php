<?php
require_role(['admin','financeiro']);

$account_id = (int)($_GET['account_id'] ?? 0);

if(!$account_id){
  flash_set('danger', 'Conta não especificada.');
  redirect($base.'/app.php?page=cash');
}

// Busca dados da conta
$st = $pdo->prepare("SELECT * FROM accounts WHERE id=?");
$st->execute([$account_id]);
$account = $st->fetch();

if(!$account){
  flash_set('danger', 'Conta não encontrada.');
  redirect($base.'/app.php?page=cash');
}

// Processamento de ações
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? '';
  
  // Editar movimentação (SOMENTE MASTER)
  if($action === 'edit_movement'){
    if(user_role() !== 'admin'){
      flash_set('danger', 'Apenas o master pode editar movimentações.');
    } else {
      $mov_id = (int)($_POST['mov_id'] ?? 0);
      $amount = (float)str_replace(',', '.', ($_POST['amount'] ?? '0'));
      $description = trim($_POST['description'] ?? '');
      $category = trim($_POST['category'] ?? '');
      
      if($mov_id && $amount > 0 && $description){
        $st = $pdo->prepare("UPDATE cash_movements SET amount=?, description=?, category=? WHERE id=?");
        $st->execute([$amount, $description, $category, $mov_id]);
        flash_set('success', 'Movimentação editada com sucesso!');
      }
    }
    redirect($base.'/app.php?page=cash_movements&account_id='.$account_id);
  }
  
  // Excluir movimentação (SOMENTE MASTER)
  if($action === 'delete_movement'){
    if(user_role() !== 'admin'){
      flash_set('danger', 'Apenas o master pode excluir movimentações.');
    } else {
      $mov_id = (int)($_POST['mov_id'] ?? 0);
      if($mov_id){
        $pdo->prepare("DELETE FROM cash_movements WHERE id=?")->execute([$mov_id]);
        flash_set('success', 'Movimentação excluída com sucesso!');
      }
    }
    redirect($base.'/app.php?page=cash_movements&account_id='.$account_id);
  }
  
  // Solicitar alteração (FINANCEIRO)
  if($action === 'request_change'){
    $mov_id = (int)($_POST['mov_id'] ?? 0);
    $request_type = $_POST['request_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $new_amount = $_POST['new_amount'] ?? null;
    $new_description = trim($_POST['new_description'] ?? '');
    $new_category = trim($_POST['new_category'] ?? '');
    
    if(!$mov_id || !$request_type || !$reason){
      flash_set('danger', 'Preencha todos os campos obrigatórios.');
    } else {
      if($new_amount) $new_amount = (float)str_replace(',', '.', $new_amount);
      
      $st = $pdo->prepare("INSERT INTO cash_movement_change_requests (movement_id, requested_by_user_id, request_type, reason, new_amount, new_description, new_category, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
      $st->execute([$mov_id, user_id(), $request_type, $reason, $new_amount, $new_description, $new_category]);
      flash_set('success', 'Solicitação enviada para aprovação do master.');
    }
    redirect($base.'/app.php?page=cash_movements&account_id='.$account_id);
  }
  
  if($action === 'add_movement'){
    $movement_type = $_POST['movement_type'] ?? '';
    $amount = (float)str_replace(',', '.', ($_POST['amount'] ?? '0'));
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if(empty($movement_type) || $amount <= 0 || empty($description)){
      flash_set('danger', 'Preencha todos os campos obrigatórios.');
    } else {
      $st = $pdo->prepare("INSERT INTO cash_movements (account_id, movement_type, amount, description, category, reference_type, created_by_user_id, created_at)
                          VALUES (?, ?, ?, ?, ?, 'manual', ?, NOW())");
      $st->execute([$account_id, $movement_type, $amount, $description, $category, user_id()]);
      
      flash_set('success', 'Movimentação registrada com sucesso!');
      redirect($base.'/app.php?page=cash_movements&account_id='.$account_id);
    }
  }
}

// Busca movimentações
$movements = $pdo->prepare("SELECT m.*, u.name as user_name
                           FROM cash_movements m
                           LEFT JOIN users u ON u.id = m.created_by_user_id
                           WHERE m.account_id = ?
                           ORDER BY m.created_at DESC, m.id DESC");
$movements->execute([$account_id]);
$movements = $movements->fetchAll();

// Calcula saldo atual
$saldo_atual = $account['initial_balance'];
foreach($movements as $mov){
  if($mov['movement_type'] === 'entrada'){
    $saldo_atual += $mov['amount'];
  } else {
    $saldo_atual -= $mov['amount'];
  }
}

// Busca categorias de despesas
$categories = $pdo->query("SELECT name FROM expense_categories WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$f = flash_get();
?>

<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h5 style="font-weight:900">💳 Extrato: <?= h($account['name']) ?></h5>
      <p class="text-muted small mb-0">
        <?= h($account['bank_name']) ?> 
        <?php if($account['account_number']): ?>
          | Conta: <?= h($account['account_number']) ?>
        <?php endif; ?>
      </p>
    </div>
    <div>
      <a href="<?= h($base) ?>/app.php?page=cash" class="btn btn-outline-secondary">← Voltar</a>
    </div>
  </div>
</div>

<?php if($f): ?>
  <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
<?php endif; ?>

<!-- Card de Saldos -->
<div class="row mb-3">
  <div class="col-md-4">
    <div class="card p-3">
      <div class="text-muted small">Saldo Inicial</div>
      <div class="h4 mb-0">R$ <?= number_format($account['initial_balance'], 2, ',', '.') ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 bg-light">
      <div class="text-muted small">Saldo Atual</div>
      <div class="h3 mb-0 <?= $saldo_atual < 0 ? 'text-danger' : 'text-success' ?>">
        R$ <?= number_format($saldo_atual, 2, ',', '.') ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <div class="text-muted small">Movimentações</div>
      <div class="h4 mb-0"><?= count($movements) ?></div>
    </div>
  </div>
</div>

<!-- Formulário de Nova Movimentação -->
<div class="card p-3 mb-3">
  <h6>➕ Nova Movimentação</h6>
  <form method="post" class="row g-3">
    <input type="hidden" name="action" value="add_movement">
    
    <div class="col-md-2">
      <label class="form-label">Tipo *</label>
      <select name="movement_type" class="form-select" required>
        <option value="">Selecione...</option>
        <option value="entrada">💰 Entrada</option>
        <option value="saida">💸 Saída</option>
      </select>
    </div>
    
    <div class="col-md-2">
      <label class="form-label">Valor (R$) *</label>
      <input type="text" name="amount" class="form-control" required placeholder="0,00">
    </div>
    
    <div class="col-md-3">
      <label class="form-label">Categoria</label>
      <select name="category" class="form-select">
        <option value="">Nenhuma</option>
        <?php foreach($categories as $cat): ?>
          <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
        <?php endforeach; ?>
        <option value="">---</option>
        <option value="Venda">Venda</option>
        <option value="Entrada Manual">Entrada Manual</option>
        <option value="Pagamento">Pagamento</option>
        <option value="Saque">Saque</option>
        <option value="Depósito">Depósito</option>
        <option value="Transferência">Transferência</option>
      </select>
    </div>
    
    <div class="col-md-4">
      <label class="form-label">Descrição *</label>
      <input type="text" name="description" class="form-control" required placeholder="Descreva a movimentação">
    </div>
    
    <div class="col-md-1 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">Lançar</button>
    </div>
  </form>
</div>

<!-- Lista de Movimentações -->
<div class="card p-3">
  <h6>📊 Histórico de Movimentações</h6>
  
  <?php if(empty($movements)): ?>
    <div class="alert alert-info">
      Nenhuma movimentação registrada ainda. Use o formulário acima para lançar entradas e saídas.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th style="width: 120px;">Data/Hora</th>
            <th style="width: 80px;">Tipo</th>
            <th>Descrição</th>
            <th>Categoria</th>
            <th class="text-end">Valor</th>
            <th class="text-end">Saldo</th>
            <th>Usuário</th>
            <th style="width: 100px;">Comprovante</th>
            <th style="width: 180px;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $saldo_linha = $account['initial_balance'];
          $movements_reversed = array_reverse($movements); // Inverte para calcular saldo correto
          
          foreach($movements_reversed as $mov): 
            if($mov['movement_type'] === 'entrada'){
              $saldo_linha += $mov['amount'];
            } else {
              $saldo_linha -= $mov['amount'];
            }
          endforeach;
          
          // Agora exibe na ordem correta (mais recente primeiro)
          $saldo_linha = $saldo_atual;
          foreach($movements as $mov): 
          ?>
            <tr>
              <td class="small"><?= date('d/m/Y H:i', strtotime($mov['created_at'])) ?></td>
              <td>
                <?php if($mov['movement_type'] === 'entrada'): ?>
                  <span class="badge bg-success">Entrada</span>
                <?php else: ?>
                  <span class="badge bg-danger">Saída</span>
                <?php endif; ?>
              </td>
              <td><?= h($mov['description']) ?></td>
              <td class="small text-muted"><?= h($mov['category']) ?></td>
              <td class="text-end">
                <strong class="<?= $mov['movement_type'] === 'entrada' ? 'text-success' : 'text-danger' ?>">
                  <?= $mov['movement_type'] === 'entrada' ? '+' : '-' ?> 
                  R$ <?= number_format($mov['amount'], 2, ',', '.') ?>
                </strong>
              </td>
              <td class="text-end">
                <strong class="<?= $saldo_linha < 0 ? 'text-danger' : '' ?>">
                  R$ <?= number_format($saldo_linha, 2, ',', '.') ?>
                </strong>
              </td>
              <td class="small text-muted"><?= h($mov['user_name']) ?></td>
              <td class="text-center">
                <?php 
                  // Verifica se há comprovante vinculado (de recebimento ou pagamento)
                  $proof_path = null;
                  $proof_name = null;
                  
                  if(!empty($mov['ar_proof_path'])){
                    $proof_path = $mov['ar_proof_path'];
                    $proof_name = $mov['ar_proof_name'] ?? 'Comprovante';
                  } elseif(!empty($mov['ap_proof_path'])){
                    $proof_path = $mov['ap_proof_path'];
                    $proof_name = $mov['ap_proof_name'] ?? 'Comprovante';
                  }
                  
                  if($proof_path):
                ?>
                  <a href="<?= h($base) ?>/<?= h($proof_path) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="<?= h($proof_name) ?>">
                    📄 Ver
                  </a>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if(user_role() === 'admin'): ?>
                  <!-- Master pode editar/excluir -->
                  <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= h(json_encode($mov)) ?>)">Editar</button>
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= h($mov['id']) ?>)">Excluir</button>
                <?php else: ?>
                  <!-- Financeiro pode solicitar alteração -->
                  <button class="btn btn-sm btn-outline-warning" onclick="openRequestModal(<?= h(json_encode($mov)) ?>)">Solicitar Alteração</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php 
            // Atualiza saldo para próxima linha (vai voltando)
            if($mov['movement_type'] === 'entrada'){
              $saldo_linha -= $mov['amount'];
            } else {
              $saldo_linha += $mov['amount'];
            }
            ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Modal de Edição (MASTER) -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Movimentação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="edit_movement">
        <input type="hidden" name="mov_id" id="editMovId">
        
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Valor (R$)</label>
            <input type="text" name="amount" id="editAmount" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <input type="text" name="description" id="editDescription" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Categoria</label>
            <input type="text" name="category" id="editCategory" class="form-control">
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal de Solicitação (FINANCEIRO) -->
<div class="modal fade" id="requestModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Solicitar Alteração ao Master</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="request_change">
        <input type="hidden" name="mov_id" id="reqMovId">
        
        <div class="modal-body">
          <div class="alert alert-info">
            <strong id="reqOriginal"></strong>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Tipo de Solicitação</label>
            <select name="request_type" id="reqType" class="form-select" required onchange="toggleReqFields()">
              <option value="">Selecione...</option>
              <option value="edit">Editar valores</option>
              <option value="delete">Excluir movimentação</option>
            </select>
          </div>
          
          <div id="editFields" style="display:none;">
            <div class="mb-3">
              <label class="form-label">Novo Valor (R$)</label>
              <input type="text" name="new_amount" id="reqAmount" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Nova Descrição</label>
              <input type="text" name="new_description" id="reqDescription" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Nova Categoria</label>
              <input type="text" name="new_category" id="reqCategory" class="form-control">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Motivo da Solicitação *</label>
            <textarea name="reason" class="form-control" rows="3" required placeholder="Explique por que precisa desta alteração"></textarea>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Enviar Solicitação</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModal(mov) {
  document.getElementById('editMovId').value = mov.id;
  document.getElementById('editAmount').value = parseFloat(mov.amount).toFixed(2).replace('.', ',');
  document.getElementById('editDescription').value = mov.description;
  document.getElementById('editCategory').value = mov.category || '';
  
  const modal = new bootstrap.Modal(document.getElementById('editModal'));
  modal.show();
}

function confirmDelete(movId) {
  if(confirm('Tem certeza que deseja EXCLUIR esta movimentação?\n\nEsta ação não pode ser desfeita e afetará o saldo do caixa.')){
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="delete_movement"><input type="hidden" name="mov_id" value="'+movId+'">';
    document.body.appendChild(form);
    form.submit();
  }
}

function openRequestModal(mov) {
  document.getElementById('reqMovId').value = mov.id;
  document.getElementById('reqOriginal').textContent = mov.description + ' - R$ ' + parseFloat(mov.amount).toFixed(2).replace('.', ',');
  document.getElementById('reqAmount').value = parseFloat(mov.amount).toFixed(2).replace('.', ',');
  document.getElementById('reqDescription').value = mov.description;
  document.getElementById('reqCategory').value = mov.category || '';
  document.getElementById('reqType').value = '';
  document.getElementById('editFields').style.display = 'none';
  
  const modal = new bootstrap.Modal(document.getElementById('requestModal'));
  modal.show();
}

function toggleReqFields() {
  const type = document.getElementById('reqType').value;
  document.getElementById('editFields').style.display = type === 'edit' ? 'block' : 'none';
}
</script>
