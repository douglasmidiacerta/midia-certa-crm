<?php
require_role(['admin','financeiro']);

// Atualizações
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['os_id'] ?? 0);
  if(!$id){ flash_set('danger','O.S inválida'); redirect($base.'/app.php?page=expedicao'); }

  if($action==='save_delivery'){
    $delivery_fee = (float)str_replace(',','.',($_POST['delivery_fee'] ?? '0'));
    $delivery_cost = (float)str_replace(',','.',($_POST['delivery_cost'] ?? '0'));
    $delivery_motoboy = trim($_POST['delivery_motoboy'] ?? '');
    $delivery_pay_to = $_POST['delivery_pay_to'] ?? '';
    $delivery_pay_mode = $_POST['delivery_pay_mode'] ?? '';
    $delivery_notes = trim($_POST['delivery_notes'] ?? '');

    $ship_post_cost = (float)str_replace(',','.',($_POST['ship_post_cost'] ?? '0'));
    $ship_freight_cost = (float)str_replace(',','.',($_POST['ship_freight_cost'] ?? '0'));
    $ship_service = trim($_POST['ship_service'] ?? '');

    // Se for correios, o custo total = post + frete (sem sobrescrever se user quiser diferente)
    $os = $pdo->prepare("SELECT delivery_method FROM os WHERE id=?");
    $os->execute([$id]);
    $os = $os->fetch();
    if(!$os){ flash_set('danger','O.S não encontrada'); redirect($base.'/app.php?page=expedicao'); }

    if($os['delivery_method']==='correios'){
      $delivery_cost = $ship_post_cost + $ship_freight_cost;
    }

    $st = $pdo->prepare("UPDATE os
      SET delivery_fee=?, delivery_cost=?, delivery_pay_to=?, delivery_pay_mode=?, delivery_motoboy=?, delivery_notes=?,
          ship_post_cost=?, ship_freight_cost=?, ship_service=?
      WHERE id=?");
    $st->execute([$delivery_fee,$delivery_cost,$delivery_pay_to,$delivery_pay_mode,$delivery_motoboy,$delivery_notes,$ship_post_cost,$ship_freight_cost,$ship_service,$id]);

    audit($pdo,'update','os',$id,['delivery_fee'=>$delivery_fee,'delivery_cost'=>$delivery_cost]);
    flash_set('success','Expedição atualizada.');
    redirect($base.'/app.php?page=expedicao');
  }

  if($action==='finalize'){
    // Finalizar exige tudo pago: aqui só valida se não existe título em aberto/rascunho
    $st = $pdo->prepare("SELECT COUNT(*) c FROM ar_titles WHERE os_id=? AND status<>'recebido'");
    $st->execute([$id]);
    $c = (int)($st->fetch()['c'] ?? 0);
    if($c>0){
      flash_set('danger','Ainda existem títulos sem baixa. Dê baixa no financeiro antes de finalizar.');
      redirect($base.'/app.php?page=os_view&id='.$id);
    }
    $pdo->prepare("UPDATE os SET status='finalizada' WHERE id=?")->execute([$id]);
    audit($pdo,'status','os',$id,['to'=>'finalizada']);
    flash_set('success','O.S finalizada.');
    redirect($base.'/app.php?page=os_view&id='.$id);
  }
}

$q = trim($_GET['q'] ?? '');

$sql = "SELECT o.id,o.code,o.status,o.delivery_method,o.due_date,o.delivery_fee,o.delivery_cost,o.ship_post_cost,o.ship_freight_cost,o.ship_service,o.delivery_motoboy,o.delivery_pay_to,o.delivery_pay_mode,o.delivery_notes,
               c.name client_name
        FROM os o
        JOIN clients c ON c.id=o.client_id
        WHERE COALESCE(o.doc_kind,'sale')='sale'
          AND o.status<>'cancelada'
          AND o.delivery_method IN ('motoboy','correios')";
$params = [];
if($q){
  $sql .= " AND (o.code LIKE ? OR c.name LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
$sql .= " ORDER BY o.id DESC LIMIT 200";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 style="font-weight:900">Expedição</h5>
    <div class="text-muted small">Lançar custos/cobranças de motoboy/correios e preparar entrega.</div>
  </div>

  <form class="row g-2 mt-2">
    <input type="hidden" name="page" value="expedicao">
    <div class="col-md-9"><input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Buscar por código ou cliente"></div>
    <div class="col-md-3"><button class="btn btn-outline-secondary w-100">Buscar</button></div>
  </form>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Código</th><th>Cliente</th><th>Método</th><th>Status</th><th>Prev. entrega</th><th>Cobrança</th><th>Custo</th><th style="width:320px">Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><b>#<?= h($r['code']) ?></b></td>
          <td><?= h($r['client_name']) ?></td>
          <td><?= strtoupper(h($r['delivery_method'])) ?></td>
          <td><span class="badge bg-primary"><?= strtoupper(h($r['status'])) ?></span></td>
          <td><?= h($r['due_date'] ?: '-') ?></td>
          <td><?= money($r['delivery_fee']) ?></td>
          <td><?= money($r['delivery_cost']) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#exp<?= h($r['id']) ?>">Editar</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($r['id']) ?>">Abrir O.S</a>
          </td>
        </tr>
        <tr class="collapse" id="exp<?= h($r['id']) ?>">
          <td colspan="8">
            <div class="border rounded p-3 bg-white">
              <form method="post" class="row g-2">
                <input type="hidden" name="action" value="save_delivery">
                <input type="hidden" name="os_id" value="<?= h($r['id']) ?>">

                <div class="col-md-2">
                  <label class="form-label">Cobrar cliente (R$)</label>
                  <input class="form-control" name="delivery_fee" value="<?= h(number_format((float)$r['delivery_fee'],2,',','.')) ?>">
                </div>

                <?php if($r['delivery_method']==='motoboy'): ?>
                  <div class="col-md-2">
                    <label class="form-label">Custo motoboy (R$)</label>
                    <input class="form-control" name="delivery_cost" value="<?= h(number_format((float)$r['delivery_cost'],2,',','.')) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Motoboy</label>
                    <input class="form-control" name="delivery_motoboy" value="<?= h($r['delivery_motoboy'] ?? '') ?>" placeholder="Nome/identificação">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Quem recebe?</label>
                    <select class="form-select" name="delivery_pay_to">
                      <option value="empresa" <?= ($r['delivery_pay_to']??'')==='empresa'?'selected':'' ?>>Custo interno (empresa paga)</option>
                      <option value="motoboy" <?= ($r['delivery_pay_to']??'')==='motoboy'?'selected':'' ?>>Motoboy recebe direto</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Pagamento</label>
                    <select class="form-select" name="delivery_pay_mode">
                      <option value="imediato" <?= ($r['delivery_pay_mode']??'')==='imediato'?'selected':'' ?>>Na hora</option>
                      <option value="semanal" <?= ($r['delivery_pay_mode']??'')==='semanal'?'selected':'' ?>>Semanal</option>
                    </select>
                  </div>
                <?php else: ?>
                  <div class="col-md-2">
                    <label class="form-label">Motoboy (postagem)</label>
                    <input class="form-control" name="ship_post_cost" value="<?= h(number_format((float)$r['ship_post_cost'],2,',','.')) ?>">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Frete correios</label>
                    <input class="form-control" name="ship_freight_cost" value="<?= h(number_format((float)$r['ship_freight_cost'],2,',','.')) ?>">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Serviço</label>
                    <select class="form-select" name="ship_service">
                      <option value="">-</option>
                      <option value="pac" <?= ($r['ship_service']??'')==='pac'?'selected':'' ?>>PAC</option>
                      <option value="sedex" <?= ($r['ship_service']??'')==='sedex'?'selected':'' ?>>SEDEX</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Observações</label>
                    <input class="form-control" name="delivery_notes" value="<?= h($r['delivery_notes'] ?? '') ?>" placeholder="Rastreio, endereço, etc.">
                  </div>
                <?php endif; ?>

                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-primary" type="submit">Salvar</button>
                  <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($r['id']) ?>">Ir para O.S</a>
                </div>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
