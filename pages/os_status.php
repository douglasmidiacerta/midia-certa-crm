<?php
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM os WHERE id=?");
$st->execute([$id]);
$os = $st->fetch();
if(!$os){ echo "<h4>O.S não encontrada</h4>"; return; }

$status_map = [
  'atendimento'=>'Aberto',
  'conferencia'=>'Análise de arquivo',
  'producao'=>'Em produção',
  'disponivel'=>'Disponível / Pronta',
  'finalizada'=>'Finalizada',
  'refugado'=>'Arquivo refugado',
  'cancelada'=>'Cancelada'
];

function can_move_to($pdo, $config, $os, $to){
  $id = (int)$os['id'];
  // rules:
  if($to==='producao'){
    // Regra Mídia Certa: SEMPRE precisa de arte anexada para produzir (produto/serviço/reimpressão)
    $st = $pdo->prepare("SELECT COUNT(*) c FROM os_files WHERE os_id=? AND kind='arte_pdf'");
    $st->execute([$id]);
    if((int)$st->fetch()['c']===0) return [false,'Para entrar em PRODUÇÃO, anexe a ARTE (PDF).'];

    if(empty($os['approved_at'])) return [false,'Para entrar em PRODUÇÃO, registre a aprovação do cliente.'];
  }

  if($to==='finalizada'){
    // saldo must be received OR admin
    $st = $pdo->prepare("SELECT status, amount FROM ar_titles WHERE os_id=? AND kind='saldo' LIMIT 1");
    $st->execute([$id]);
    $saldo = $st->fetch();
    if($saldo && (float)$saldo['amount']>0 && $saldo['status']!=='recebido' && !can_admin()){
      return [false,'Saldo pendente. Receba o saldo ou peça liberação do Admin.'];
    }
  }
  return [true,''];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $to = $_POST['to'] ?? $os['status'];
  if(!isset($status_map[$to])) $to = $os['status'];
  [$ok,$msg] = can_move_to($pdo,$config,$os,$to);
  if(!$ok){
    flash_set('danger',$msg);
    redirect($base.'/app.php?page=os_status&id='.$id);
  }
  $st = $pdo->prepare("UPDATE os SET status=? WHERE id=?");
  $st->execute([$to,$id]);
  audit($pdo,'status','os',$id,['from'=>$os['status'],'to'=>$to]);
  flash_set('success','Status atualizado.');
  redirect($base.'/app.php?page=os_view&id='.$id);
}
?>
<div class="card p-3">
  <h5 style="font-weight:900">Mudar status — <?= h($os['code']) ?></h5>
  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Novo status</label>
      <select class="form-select" name="to">
        <?php foreach($status_map as $k=>$label): ?>
          <option value="<?= h($k) ?>" <?= $os['status']===$k?'selected':'' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="muted mt-2">
        Travamentos: <b>PRODUÇÃO exige ARTE (PDF)</b>. Finalização exige <b>saldo recebido</b> (ou Admin).
      </div>
    </div>
    <div class="col-12">
      <button class="btn btn-primary">Salvar</button>
      <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($id) ?>">Voltar</a>
    </div>
  </form>
</div>
