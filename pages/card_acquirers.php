<?php
// Página de gerenciamento de adquirentes de cartão (maquininhas)
require_role(['admin','financeiro']);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  
  if($action === 'create' || $action === 'update'){
    $name = trim($_POST['name'] ?? '');
    $payment_system = $_POST['payment_system'] ?? 'D+30';
    $payment_days = 0;
    if($payment_system === 'D+0') $payment_days = 0;
    elseif($payment_system === 'D+1') $payment_days = 1;
    else $payment_days = 30;
    
    $notes = trim($_POST['notes'] ?? '');
    $active = !empty($_POST['active']) ? 1 : 0;
    
    // Taxas por parcela (1x a 21x)
    $fees = [];
    for($i = 1; $i <= 21; $i++){
      $fee = (float)str_replace(',','.',($_POST['fee_'.$i] ?? '0'));
      $fees[$i] = $fee;
    }
    
    if(!$name){
      flash_set('danger','Nome da adquirente é obrigatório.');
    } else {
      $pdo->beginTransaction();
      
      if($action === 'create'){
        $st = $pdo->prepare("INSERT INTO card_acquirers (name, payment_system, payment_days, notes, active, created_at, updated_at)
                            VALUES (?,?,?,?,?, NOW(), NOW())");
        $st->execute([$name, $payment_system, $payment_days, $notes, $active]);
        $acquirer_id = (int)$pdo->lastInsertId();
        flash_set('success','Adquirente cadastrada com sucesso!');
      } else {
        $st = $pdo->prepare("UPDATE card_acquirers SET name=?, payment_system=?, payment_days=?, notes=?, active=?, updated_at=NOW() WHERE id=?");
        $st->execute([$name, $payment_system, $payment_days, $notes, $active, $id]);
        $acquirer_id = $id;
        // Remove taxas antigas
        $pdo->prepare("DELETE FROM card_acquirer_fees WHERE acquirer_id=?")->execute([$acquirer_id]);
        flash_set('success','Adquirente atualizada com sucesso!');
      }
      
      // Insere taxas
      $stFee = $pdo->prepare("INSERT INTO card_acquirer_fees (acquirer_id, installments, fee_percent) VALUES (?,?,?)");
      foreach($fees as $installment => $fee){
        if($fee > 0){
          $stFee->execute([$acquirer_id, $installment, $fee]);
        }
      }
      
      $pdo->commit();
      redirect($base.'/app.php?page=card_acquirers');
    }
  } elseif($action === 'delete'){
    $pdo->prepare("UPDATE card_acquirers SET active=0 WHERE id=?")->execute([$id]);
    flash_set('success','Adquirente desativada.');
    redirect($base.'/app.php?page=card_acquirers');
  }
}

$acquirers = $pdo->query("SELECT * FROM card_acquirers ORDER BY active DESC, name")->fetchAll();
$editing = null;
$editing_fees = [];
if(isset($_GET['edit'])){
  $edit_id = (int)$_GET['edit'];
  $st = $pdo->prepare("SELECT * FROM card_acquirers WHERE id=?");
  $st->execute([$edit_id]);
  $editing = $st->fetch();
  
  // Busca taxas
  if($editing){
    $stFees = $pdo->prepare("SELECT installments, fee_percent FROM card_acquirer_fees WHERE acquirer_id=? ORDER BY installments");
    $stFees->execute([$editing['id']]);
    while($row = $stFees->fetch()){
      $editing_fees[$row['installments']] = $row['fee_percent'];
    }
  }
}

$f = flash_get();
?>

<div class="card p-3 mb-3">
  <h5 style="font-weight:900">Adquirentes de Cartão (Maquininhas)</h5>
  <p class="text-muted small mb-0">Configure as taxas por modalidade de pagamento para cada máquina de cartão.</p>
</div>

<?php if($f): ?>
  <div class="alert alert-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-md-5">
    <div class="card p-3">
      <h6><?= $editing ? 'Editar' : 'Nova' ?> Adquirente</h6>
      <form method="post">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if($editing): ?>
          <input type="hidden" name="id" value="<?= h($editing['id']) ?>">
        <?php endif; ?>
        
        <div class="mb-3">
          <label class="form-label">Nome da Adquirente *</label>
          <input type="text" name="name" class="form-control" required value="<?= h($editing['name'] ?? '') ?>" placeholder="Ex: Stone, PagSeguro, Mercado Pago">
        </div>
        
        <div class="mb-3">
          <label class="form-label">Sistema de Pagamento *</label>
          <select name="payment_system" class="form-select" required>
            <option value="D+0" <?= ($editing['payment_system'] ?? '') === 'D+0' ? 'selected' : '' ?>>D+0 (cai no mesmo dia)</option>
            <option value="D+1" <?= ($editing['payment_system'] ?? '') === 'D+1' ? 'selected' : '' ?>>D+1 (cai no dia seguinte)</option>
            <option value="D+30" <?= ($editing['payment_system'] ?? 'D+30') === 'D+30' ? 'selected' : '' ?>>D+30 (cai em 30 dias)</option>
          </select>
          <small class="text-muted">Quando o dinheiro cai na sua conta</small>
        </div>
        
        <div class="mb-3">
          <label class="form-label"><strong>Taxas por Parcela (%)</strong></label>
          <small class="text-muted d-block mb-2">Preencha as taxas de 1x até 21x. Deixe 0 para desabilitar.</small>
          
          <div class="row g-2">
            <?php for($i = 1; $i <= 21; $i++): ?>
              <div class="col-md-3">
                <label class="form-label small"><?= $i ?>x</label>
                <input type="text" name="fee_<?= $i ?>" class="form-control form-control-sm" 
                       value="<?= h(number_format($editing_fees[$i] ?? 0, 2, ',', '')) ?>" 
                       placeholder="0,00">
              </div>
            <?php endfor; ?>
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Observações</label>
          <textarea name="notes" class="form-control" rows="2"><?= h($editing['notes'] ?? '') ?></textarea>
        </div>
        
        <div class="form-check mb-3">
          <input type="checkbox" name="active" value="1" id="active" class="form-check-input" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="active">Ativa</label>
        </div>
        
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Atualizar' : 'Cadastrar' ?></button>
        <?php if($editing): ?>
          <a href="<?= h($base) ?>/app.php?page=card_acquirers" class="btn btn-outline-secondary">Cancelar</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
  
  <div class="col-md-7">
    <div class="card p-3">
      <h6>Adquirentes Cadastradas</h6>
      <?php if(empty($acquirers)): ?>
        <p class="text-muted">Nenhuma adquirente cadastrada ainda.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Sistema</th>
                <th>Taxas</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($acquirers as $acq): ?>
                <?php
                  // Busca taxas da adquirente
                  $stFees = $pdo->prepare("SELECT COUNT(*) as c FROM card_acquirer_fees WHERE acquirer_id=?");
                  $stFees->execute([$acq['id']]);
                  $fee_count = $stFees->fetch()['c'];
                ?>
                <tr>
                  <td><strong><?= h($acq['name']) ?></strong></td>
                  <td><span class="badge bg-info"><?= h($acq['payment_system'] ?? 'D+30') ?></span></td>
                  <td><?= $fee_count ?> parcela(s)</td>
                  <td>
                    <?php if($acq['active']): ?>
                      <span class="badge bg-success">Ativa</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Inativa</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= h($base) ?>/app.php?page=card_acquirers&edit=<?= h($acq['id']) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
