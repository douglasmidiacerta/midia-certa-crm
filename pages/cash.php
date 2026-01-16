<?php 
require_role(['admin','financeiro']);

// Processamento de cadastro/edição de conta (SOMENTE ADMIN)
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  // Verifica se é admin
  if(user_role() !== 'admin'){
    flash_set('danger', 'Apenas o administrador pode criar/editar contas.');
    redirect($base.'/app.php?page=cash');
  }
  
  $action = $_POST['action'] ?? '';
  
  if($action === 'create_account' || $action === 'update_account'){
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $initial_balance = (float)str_replace(',', '.', ($_POST['initial_balance'] ?? '0'));
    $active = !empty($_POST['active']) ? 1 : 0;
    
    if(empty($name)){
      flash_set('danger', 'Nome da conta é obrigatório.');
    } else {
      if($action === 'create_account'){
        $st = $pdo->prepare("INSERT INTO accounts (name, bank_name, account_number, initial_balance, current_balance, active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $st->execute([$name, $bank_name, $account_number, $initial_balance, $initial_balance, $active]);
        flash_set('success', 'Conta cadastrada com sucesso!');
      } else {
        $st = $pdo->prepare("UPDATE accounts SET name=?, bank_name=?, account_number=?, initial_balance=?, active=? WHERE id=?");
        $st->execute([$name, $bank_name, $account_number, $initial_balance, $active, $id]);
        flash_set('success', 'Conta atualizada com sucesso!');
      }
      redirect($base.'/app.php?page=cash');
    }
  }
}

// Busca contas e calcula saldo atual baseado nas movimentações
$accounts = $pdo->query("SELECT a.*, 
                        (a.initial_balance + COALESCE((
                          SELECT SUM(CASE 
                            WHEN m.movement_type = 'entrada' THEN m.amount 
                            WHEN m.movement_type = 'saida' THEN -m.amount 
                            ELSE 0 
                          END)
                          FROM cash_movements m 
                          WHERE m.account_id = a.id
                        ), 0)) as calculated_balance
                        FROM accounts a 
                        WHERE a.active = 1 
                        ORDER BY a.name")->fetchAll();

$total_geral = array_sum(array_column($accounts, 'calculated_balance'));

$editing = null;
if(isset($_GET['edit'])){
  $edit_id = (int)$_GET['edit'];
  $st = $pdo->prepare("SELECT * FROM accounts WHERE id=?");
  $st->execute([$edit_id]);
  $editing = $st->fetch();
}

$f = flash_get();
?>

<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h5 style="font-weight:900">💰 Gestão de Caixas</h5>
      <p class="text-muted small mb-0">Gerencie suas contas bancárias e saldos</p>
    </div>
    <div>
      <?php if(user_role() === 'admin'): ?>
        <button class="btn btn-primary" onclick="document.getElementById('formBox').style.display='block'">
          + Nova Conta
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if($f): ?>
  <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
<?php endif; ?>

<!-- Formulário de Cadastro/Edição -->
<div class="card p-3 mb-3" id="formBox" style="<?= $editing ? '' : 'display:none;' ?>">
  <h6><?= $editing ? 'Editar' : 'Nova' ?> Conta</h6>
  <form method="post">
    <input type="hidden" name="action" value="<?= $editing ? 'update_account' : 'create_account' ?>">
    <?php if($editing): ?>
      <input type="hidden" name="id" value="<?= h($editing['id']) ?>">
    <?php endif; ?>
    
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Nome da Conta *</label>
        <input type="text" name="name" class="form-control" required value="<?= h($editing['name'] ?? '') ?>" placeholder="Ex: Banco do Brasil CC">
      </div>
      
      <div class="col-md-4">
        <label class="form-label">Banco</label>
        <input type="text" name="bank_name" class="form-control" value="<?= h($editing['bank_name'] ?? '') ?>" placeholder="Ex: Banco do Brasil">
      </div>
      
      <div class="col-md-4">
        <label class="form-label">Número da Conta</label>
        <input type="text" name="account_number" class="form-control" value="<?= h($editing['account_number'] ?? '') ?>" placeholder="Ex: 12345-6">
      </div>
      
      <div class="col-md-4">
        <label class="form-label">Saldo Inicial (R$)</label>
        <input type="text" name="initial_balance" class="form-control" value="<?= h(number_format($editing['initial_balance'] ?? 0, 2, ',', '')) ?>" placeholder="0,00">
        <small class="text-muted">Saldo que já existia na conta</small>
      </div>
      
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
          <input type="checkbox" name="active" value="1" id="active" class="form-check-input" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="active">Conta Ativa</label>
        </div>
      </div>
    </div>
    
    <div class="mt-3">
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Atualizar' : 'Cadastrar' ?></button>
      <?php if($editing): ?>
        <a href="<?= h($base) ?>/app.php?page=cash" class="btn btn-outline-secondary">Cancelar</a>
      <?php else: ?>
        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('formBox').style.display='none'">Cancelar</button>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Lista de Contas -->
<div class="card p-3">
  <h6>Contas Cadastradas</h6>
  
  <?php if(empty($accounts)): ?>
    <div class="alert alert-info">
      Nenhuma conta cadastrada ainda. Clique em "+ Nova Conta" para começar.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Conta</th>
            <th>Banco</th>
            <th>Número</th>
            <th class="text-end">Saldo Inicial</th>
            <th class="text-end">Saldo Atual</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($accounts as $acc): ?>
            <tr>
              <td><strong><?= h($acc['name']) ?></strong></td>
              <td><?= h($acc['bank_name']) ?></td>
              <td><?= h($acc['account_number']) ?></td>
              <td class="text-end">R$ <?= number_format($acc['initial_balance'], 2, ',', '.') ?></td>
              <td class="text-end">
                <strong class="<?= $acc['calculated_balance'] < 0 ? 'text-danger' : 'text-success' ?>">
                  R$ <?= number_format($acc['calculated_balance'], 2, ',', '.') ?>
                </strong>
              </td>
              <td>
                <?php if($acc['active']): ?>
                  <span class="badge bg-success">Ativa</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inativa</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if(user_role() === 'admin'): ?>
                  <a href="<?= h($base) ?>/app.php?page=cash&edit=<?= h($acc['id']) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                <?php endif; ?>
                <a href="<?= h($base) ?>/app.php?page=cash_movements&account_id=<?= h($acc['id']) ?>" class="btn btn-sm btn-outline-info">Extrato</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-primary">
            <td colspan="4" class="text-end"><strong>TOTAL GERAL:</strong></td>
            <td class="text-end">
              <strong class="<?= $total_geral < 0 ? 'text-danger' : 'text-success' ?>" style="font-size: 1.2rem;">
                R$ <?= number_format($total_geral, 2, ',', '.') ?>
              </strong>
            </td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>