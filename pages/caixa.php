<?php
require_role(['admin','financeiro']);

$action = $_POST['action'] ?? '';

if($_SERVER['REQUEST_METHOD']==='POST' && $action){
  // Cadastrar banco
  if($action==='add_bank'){
    $name = trim($_POST['name'] ?? '');
    if($name===''){ flash_set('danger','Informe o nome do banco.'); redirect($base.'/app.php?page=caixa'); }
    $st = $pdo->prepare("INSERT INTO cash_banks (name, created_at) VALUES (?,?)");
    try{
      $st->execute([$name, now()]);
      audit($pdo,'create','cash_banks',(int)$pdo->lastInsertId(),['name'=>$name]);
      flash_set('success','Banco cadastrado.');
    }catch(Exception $e){
      flash_set('danger','Não foi possível cadastrar. (Banco já existe?)');
    }
    redirect($base.'/app.php?page=caixa');
  }

  // Cadastrar conta
  if($action==='add_account'){
    $bank_id = (int)($_POST['bank_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $pix_key = trim($_POST['pix_key'] ?? '');
    if(!$bank_id || $name===''){ flash_set('danger','Informe banco e nome da conta.'); redirect($base.'/app.php?page=caixa'); }
    $st = $pdo->prepare("INSERT INTO cash_accounts (bank_id, name, pix_key, type, active, created_at) VALUES (?,?,?,?,1,?)");
    $st->execute([$bank_id, $name, $pix_key ?: null, 'banco', now()]);
    audit($pdo,'create','cash_accounts',(int)$pdo->lastInsertId(),['bank_id'=>$bank_id,'name'=>$name]);
    flash_set('success','Conta cadastrada.');
    redirect($base.'/app.php?page=caixa');
  }

  // Movimento manual (entrada/saida)
  if($action==='manual_move'){
    $cash_account_id = (int)($_POST['cash_account_id'] ?? 0);
    $direction = $_POST['direction'] ?? 'entrada';
    $amount = (float)str_replace(',','.',($_POST['amount'] ?? '0'));
    $desc = trim($_POST['description'] ?? '');
    if(!$cash_account_id || $amount<=0 || $desc===''){ flash_set('danger','Preencha conta, valor e descrição.'); redirect($base.'/app.php?page=caixa'); }

    // SAÍDA: se não for admin, vira solicitação
    if($direction==='saida' && !can_admin()){
      $st = $pdo->prepare("INSERT INTO cash_withdraw_requests (cash_account_id, amount, reason, status, requested_by_user_id, created_at)
                           VALUES (?,?,?,?,?,?)");
      $st->execute([$cash_account_id, $amount, $desc, 'pendente', user_id(), now()]);
      audit($pdo,'withdraw_request','cash_withdraw_requests',(int)$pdo->lastInsertId(),['account'=>$cash_account_id,'amount'=>$amount]);
      flash_set('success','Solicitação enviada ao ADMIN para aprovação.');
      redirect($base.'/app.php?page=caixa');
    }

    // ENTRADA ou SAÍDA por admin: executa direto
    $st = $pdo->prepare("INSERT INTO cash_moves (cash_account_id, direction, amount, description, created_by_user_id, created_at)
                         VALUES (?,?,?,?,?,?)");
    $st->execute([$cash_account_id, $direction, $amount, $desc, user_id(), now()]);
    audit($pdo,'cash_move','cash_moves',(int)$pdo->lastInsertId(),['direction'=>$direction,'amount'=>$amount]);
    flash_set('success','Movimento lançado.');
    redirect($base.'/app.php?page=caixa');
  }

  // Admin aprova/rejeita retirada
  if($action==='decide_withdraw' && can_admin()){
    $rid = (int)($_POST['request_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    if(!$rid || !in_array($decision,['aprovar','rejeitar'],true)){ redirect($base.'/app.php?page=caixa'); }
    $status = $decision==='aprovar' ? 'aprovada' : 'rejeitada';
    $pdo->prepare("UPDATE cash_withdraw_requests SET status=?, approved_by_user_id=?, approved_at=NOW() WHERE id=? AND status='pendente'")
        ->execute([$status, user_id(), $rid]);
    audit($pdo,'withdraw_'.$status,'cash_withdraw_requests',$rid,[]);
    flash_set('success','Solicitação atualizada.');
    redirect($base.'/app.php?page=caixa');
  }

  // Financeiro executa retirada aprovada
  if($action==='execute_withdraw' && can_finance()){
    $rid = (int)($_POST['request_id'] ?? 0);
    $req = $pdo->prepare("SELECT * FROM cash_withdraw_requests WHERE id=?");
    $req->execute([$rid]);
    $req = $req->fetch();
    if(!$req || $req['status']!=='aprovada'){ flash_set('danger','Solicitação inválida.'); redirect($base.'/app.php?page=caixa'); }

    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO cash_moves (cash_account_id, direction, amount, description, created_by_user_id, created_at)
                   VALUES (?,?,?,?,?,?)")
        ->execute([(int)$req['cash_account_id'], 'saida', (float)$req['amount'], 'Retirada aprovada: '.$req['reason'], user_id(), now()]);
    $pdo->prepare("UPDATE cash_withdraw_requests SET status='executada', executed_by_user_id=?, executed_at=NOW() WHERE id=?")
        ->execute([user_id(), $rid]);
    $pdo->commit();

    audit($pdo,'withdraw_executed','cash_withdraw_requests',$rid,[]);
    flash_set('success','Retirada executada e registrada no caixa.');
    redirect($base.'/app.php?page=caixa');
  }
}

$banks = $pdo->query("SELECT id,name FROM cash_banks ORDER BY name")->fetchAll();

$accounts = $pdo->query("SELECT a.*, b.name bank_name
  FROM cash_accounts a
  LEFT JOIN cash_banks b ON b.id=a.bank_id
  WHERE a.active=1
  ORDER BY b.name, a.name")->fetchAll();

function cash_balance($pdo, $cash_account_id){
  $st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN direction='entrada' THEN amount ELSE 0 END),0) ent,
    COALESCE(SUM(CASE WHEN direction='saida' THEN amount ELSE 0 END),0) sai
    FROM cash_moves WHERE cash_account_id=?");
  $st->execute([$cash_account_id]);
  $r = $st->fetch();
  return (float)$r['ent'] - (float)$r['sai'];
}

$recent_moves = $pdo->query("SELECT m.*, a.name account_name, b.name bank_name, u.name user_name
  FROM cash_moves m
  JOIN cash_accounts a ON a.id=m.cash_account_id
  LEFT JOIN cash_banks b ON b.id=a.bank_id
  JOIN users u ON u.id=m.created_by_user_id
  ORDER BY m.id DESC
  LIMIT 200")->fetchAll();

$pending = can_admin()
  ? $pdo->query("SELECT r.*, a.name account_name, b.name bank_name, u.name requester
      FROM cash_withdraw_requests r
      JOIN cash_accounts a ON a.id=r.cash_account_id
      LEFT JOIN cash_banks b ON b.id=a.bank_id
      JOIN users u ON u.id=r.requested_by_user_id
      WHERE r.status='pendente'
      ORDER BY r.id DESC")->fetchAll()
  : [];

$approved = can_finance()
  ? $pdo->query("SELECT r.*, a.name account_name, b.name bank_name
      FROM cash_withdraw_requests r
      JOIN cash_accounts a ON a.id=r.cash_account_id
      LEFT JOIN cash_banks b ON b.id=a.bank_id
      WHERE r.status='aprovada'
      ORDER BY r.id DESC")->fetchAll()
  : [];
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h4 class="m-0">Caixa</h4>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div style="font-weight:900">Bancos e Contas</div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <form method="post" class="d-flex gap-2">
            <input type="hidden" name="action" value="add_bank">
            <input class="form-control form-control-sm" name="name" placeholder="Novo banco (ex: Banco do Brasil)" required>
            <button class="btn btn-sm btn-outline-primary">Adicionar</button>
          </form>
        </div>
        <div class="col-md-6">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="add_account">
            <div class="col-5">
              <select class="form-select form-select-sm" name="bank_id" required>
                <option value="">Banco...</option>
                <?php foreach($banks as $b): ?>
                  <option value="<?= h($b['id']) ?>"><?= h($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-4">
              <input class="form-control form-control-sm" name="name" placeholder="Conta" required>
            </div>
            <div class="col-3">
              <input class="form-control form-control-sm" name="pix_key" placeholder="Chave Pix (opcional)">
            </div>
            <div class="col-12">
              <button class="btn btn-sm btn-outline-success">Cadastrar conta</button>
            </div>
          </form>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Banco</th><th>Conta</th><th>Chave Pix</th><th class="text-end">Saldo</th></tr></thead>
          <tbody>
            <?php foreach($accounts as $a): $bal=cash_balance($pdo,(int)$a['id']); ?>
              <tr>
                <td><?= h($a['bank_name'] ?: '-') ?></td>
                <td><?= h($a['name']) ?></td>
                <td class="text-muted small"><?= h($a['pix_key'] ?: '-') ?></td>
                <td class="text-end"><b><?= h(money($bal)) ?></b></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-3">
      <div style="font-weight:900" class="mb-2">Lançamento manual</div>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="manual_move">
        <div class="col-md-4">
          <label class="form-label small">Conta</label>
          <select class="form-select form-select-sm" name="cash_account_id" required>
            <option value="">Selecione...</option>
            <?php foreach($accounts as $a): ?>
              <option value="<?= h($a['id']) ?>"><?= h(($a['bank_name']?:'-').' • '.$a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Tipo</label>
          <select class="form-select form-select-sm" name="direction">
            <option value="entrada">Entrada</option>
            <option value="saida">Saída</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Valor</label>
          <input class="form-control form-control-sm" name="amount" placeholder="0,00" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Descrição</label>
          <input class="form-control form-control-sm" name="description" required>
        </div>
        <div class="col-md-12">
          <button class="btn btn-sm btn-primary">Salvar</button>
          <span class="text-muted small ms-2">* Saída lançada pelo Financeiro vira solicitação pro ADMIN aprovar.</span>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <?php if(can_admin()): ?>
      <div class="card p-3 mb-3">
        <div style="font-weight:900" class="mb-2">Solicitações de retirada (pendentes)</div>
        <?php if(!$pending): ?>
          <div class="text-muted small">Nenhuma solicitação pendente.</div>
        <?php endif; ?>
        <?php foreach($pending as $r): ?>
          <div class="border rounded p-2 mb-2">
            <div><b><?= h($r['bank_name'] ?: '-') ?> • <?= h($r['account_name']) ?></b></div>
            <div>Valor: <b><?= h(money((float)$r['amount'])) ?></b></div>
            <div class="text-muted small"><?= h($r['reason']) ?></div>
            <div class="text-muted small">Solicitado por: <?= h($r['requester']) ?></div>
            <form method="post" class="d-flex gap-2 mt-2">
              <input type="hidden" name="action" value="decide_withdraw">
              <input type="hidden" name="request_id" value="<?= h($r['id']) ?>">
              <button class="btn btn-sm btn-success" name="decision" value="aprovar">Aprovar</button>
              <button class="btn btn-sm btn-outline-danger" name="decision" value="rejeitar">Rejeitar</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if(can_finance()): ?>
      <div class="card p-3 mb-3">
        <div style="font-weight:900" class="mb-2">Retiradas aprovadas (executar)</div>
        <?php if(!$approved): ?>
          <div class="text-muted small">Nenhuma retirada aprovada.</div>
        <?php endif; ?>
        <?php foreach($approved as $r): ?>
          <div class="border rounded p-2 mb-2">
            <div><b><?= h($r['bank_name'] ?: '-') ?> • <?= h($r['account_name']) ?></b></div>
            <div>Valor: <b><?= h(money((float)$r['amount'])) ?></b></div>
            <div class="text-muted small"><?= h($r['reason']) ?></div>
            <form method="post" class="mt-2">
              <input type="hidden" name="action" value="execute_withdraw">
              <input type="hidden" name="request_id" value="<?= h($r['id']) ?>">
              <button class="btn btn-sm btn-primary w-100">Executar retirada</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card p-3">
      <div style="font-weight:900" class="mb-2">Movimentos recentes</div>
      <div class="table-responsive" style="max-height:380px; overflow:auto">
        <table class="table table-sm align-middle">
          <thead><tr><th>Tipo</th><th>Conta</th><th class="text-end">Valor</th></tr></thead>
          <tbody>
            <?php foreach($recent_moves as $m): ?>
              <tr>
                <td><?= h($m['direction']) ?></td>
                <td class="text-muted small"><?= h(($m['bank_name']?:'-').' • '.$m['account_name']) ?></td>
                <td class="text-end"><?= h(money((float)$m['amount'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
