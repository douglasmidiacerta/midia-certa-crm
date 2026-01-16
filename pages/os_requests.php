<?php
/**
 * Página de Solicitações de Alteração/Exclusão de O.S (apenas Master)
 */

require_role(['admin']);

$base = $config['base_path'] ?? '';

// POST: Aprovar solicitação
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['approve_request'])){
  $request_id = (int)$_POST['request_id'];
  $review_notes = trim($_POST['review_notes'] ?? '');
  
  // Busca a solicitação
  $request_st = $pdo->prepare("
    SELECT r.*, o.id as os_id, o.code as os_code, o.status as os_status, o.client_id,
           c.name as client_name,
           (SELECT SUM(qty * unit_price) FROM os_lines WHERE os_id = o.id) as os_total
    FROM os_change_requests r
    JOIN os o ON o.id = r.os_id
    LEFT JOIN clients c ON c.id = o.client_id
    WHERE r.id = ? AND r.status = 'pending'
  ");
  $request_st->execute([$request_id]);
  $request = $request_st->fetch();
  
  if(!$request){
    flash_set('danger', 'Solicitação não encontrada ou já foi processada.');
    redirect($base.'/app.php?page=os_requests');
  }
  
  $pdo->beginTransaction();
  
  // Atualiza status da solicitação
  $pdo->prepare("UPDATE os_change_requests SET status='approved', reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=?")
    ->execute([user_id(), $review_notes, $request_id]);
  
  // Executa a ação solicitada
  if($request['request_type'] === 'reopen'){
    // REABERTURA: Reverte lançamentos do caixa (se houver)
    try {
      // 1. Busca títulos a receber da O.S
      $ar_ids_st = $pdo->prepare("SELECT id FROM ar_titles WHERE os_id=?");
      $ar_ids_st->execute([$request['os_id']]);
      $ar_ids = $ar_ids_st->fetchAll(PDO::FETCH_COLUMN);
      
      // 2. Deleta lançamentos de caixa relacionados aos títulos
      if(!empty($ar_ids)){
        $placeholders = implode(',', array_fill(0, count($ar_ids), '?'));
        $pdo->prepare("DELETE FROM cash_movements WHERE reference_type='ar_title' AND reference_id IN ($placeholders)")->execute($ar_ids);
      }
    } catch(PDOException $e){
      // Se der erro (coluna não existe), continua sem reverter caixa
      error_log("Aviso: Não foi possível reverter lançamentos do caixa - " . $e->getMessage());
    }
    
    // Muda para tipo "reaberta" e status "atendimento" para permitir edições
    $pdo->prepare("UPDATE os SET doc_kind='reaberta', status='atendimento', notes=CONCAT(COALESCE(notes,''), '\n\n[REABERTA PARA ALTERAÇÕES]\nAprovado por: ".user()['name']."') WHERE id=?")
      ->execute([$request['os_id']]);
    
    audit($pdo,'os','reopen_approved',$request['os_id'],['request_id'=>$request_id]);
    $pdo->commit();
    
    flash_set('success', 'Solicitação de reabertura aprovada! O.S #'.$request['os_code'].' foi reaberta.');
    
  } elseif($request['request_type'] === 'delete'){
    // EXCLUSÃO: Salva log e exclui
    $os_st = $pdo->prepare("SELECT * FROM os WHERE id=?");
    $os_st->execute([$request['os_id']]);
    $os_data = $os_st->fetch();
    
    if($os_data){
      // Calcula total
      $total_st = $pdo->prepare("SELECT SUM(qty * unit_price) as total FROM os_lines WHERE os_id=?");
      $total_st->execute([$request['os_id']]);
      $total_row = $total_st->fetch();
      $os_total = $total_row['total'] ?? 0;
      
      $os_data_json = json_encode($os_data);
      
      // Salva log
      $pdo->prepare("INSERT INTO os_deletion_log (os_id, os_code, client_id, client_name, total_value, deleted_by, deletion_reason, os_data) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
          $request['os_id'], 
          $os_data['code'], 
          $os_data['client_id'], 
          $request['client_name'] ?? 'N/A', 
          $os_total, 
          user_id(), 
          $request['reason'] . ' (Aprovado por: '.user()['name'].')',
          $os_data_json
        ]);
      
      // Reverte lançamentos do caixa (se houver)
      try {
        // 1. Busca títulos a receber da O.S
        $ar_ids_st = $pdo->prepare("SELECT id FROM ar_titles WHERE os_id=?");
        $ar_ids_st->execute([$request['os_id']]);
        $ar_ids = $ar_ids_st->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. Deleta lançamentos de caixa relacionados aos títulos
        if(!empty($ar_ids)){
          $placeholders = implode(',', array_fill(0, count($ar_ids), '?'));
          $pdo->prepare("DELETE FROM cash_movements WHERE reference_type='ar_title' AND reference_id IN ($placeholders)")->execute($ar_ids);
        }
      } catch(PDOException $e){
        // Se der erro (coluna não existe), continua sem reverter caixa
        error_log("Aviso: Não foi possível reverter lançamentos do caixa - " . $e->getMessage());
      }
      
      // Marca como excluída
      $pdo->prepare("UPDATE os SET status='excluida', notes=CONCAT(COALESCE(notes,''), '\n\n[EXCLUÍDA]\nMotivo: ', ?) WHERE id=?")
        ->execute([$request['reason'], $request['os_id']]);
      
      audit($pdo,'os','delete_approved',$request['os_id'],['request_id'=>$request_id]);
      $pdo->commit();
      
      flash_set('success', 'Solicitação de exclusão aprovada! O.S #'.$request['os_code'].' foi excluída.');
    }
  }
  
  redirect($base.'/app.php?page=os_requests');
}

// POST: Rejeitar solicitação
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reject_request'])){
  $request_id = (int)$_POST['request_id'];
  $review_notes = trim($_POST['review_notes'] ?? '');
  
  if(empty($review_notes)){
    flash_set('danger', 'Informe o motivo da rejeição.');
    redirect($base.'/app.php?page=os_requests');
  }
  
  $pdo->prepare("UPDATE os_change_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=? AND status='pending'")
    ->execute([user_id(), $review_notes, $request_id]);
  
  audit($pdo,'os_request','reject',$request_id,['notes'=>$review_notes]);
  flash_set('info', 'Solicitação rejeitada.');
  redirect($base.'/app.php?page=os_requests');
}

// Busca todas as solicitações
$requests_st = $pdo->query("
  SELECT r.*, 
         o.code as os_code, o.status as os_status,
         c.name as client_name,
         (SELECT SUM(qty * unit_price) FROM os_lines WHERE os_id = o.id) as os_total,
         u.name as requester_name,
         rev.name as reviewer_name
  FROM os_change_requests r
  JOIN os o ON o.id = r.os_id
  LEFT JOIN clients c ON c.id = o.client_id
  JOIN users u ON u.id = r.requested_by
  LEFT JOIN users rev ON rev.id = r.reviewed_by
  ORDER BY 
    CASE r.status 
      WHEN 'pending' THEN 1
      WHEN 'approved' THEN 2
      WHEN 'rejected' THEN 3
    END,
    r.requested_at DESC
");
$requests = $requests_st->fetchAll();

// Conta pendentes
$pending_count = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
?>

<div class="card">
  <div class="card-body">
    <h4 class="mb-3">
      🔔 Solicitações de Alteração/Exclusão de O.S
      <?php if($pending_count > 0): ?>
        <span class="badge bg-danger"><?= $pending_count ?> Pendente<?= $pending_count > 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </h4>
    
    <?php if(empty($requests)): ?>
      <div class="alert alert-info">
        Nenhuma solicitação encontrada.
      </div>
    <?php else: ?>
      
      <!-- Solicitações Pendentes -->
      <?php
      $pending = array_filter($requests, fn($r) => $r['status'] === 'pending');
      if(!empty($pending)):
      ?>
        <h5 class="mt-3">⏳ Pendentes de Aprovação</h5>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>O.S</th>
                <th>Cliente</th>
                <th>Valor</th>
                <th>Solicitante</th>
                <th>Motivo</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($pending as $r): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($r['requested_at'])) ?></td>
                  <td>
                    <?php if($r['request_type'] === 'reopen'): ?>
                      <span class="badge bg-warning text-dark">🔄 Reabertura</span>
                    <?php else: ?>
                      <span class="badge bg-danger">🗑️ Exclusão</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($r['os_id']) ?>" target="_blank">
                      #<?= h($r['os_code']) ?>
                    </a>
                    <br><small class="text-muted"><?= h($r['os_status']) ?></small>
                  </td>
                  <td><?= h($r['client_name']) ?></td>
                  <td>R$ <?= number_format((float)$r['os_total'], 2, ',', '.') ?></td>
                  <td><?= h($r['requester_name']) ?></td>
                  <td>
                    <small><?= nl2br(h(substr($r['reason'], 0, 100))) ?><?= strlen($r['reason']) > 100 ? '...' : '' ?></small>
                    <button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#viewReasonModal<?= $r['id'] ?>">Ver mais</button>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $r['id'] ?>">
                      ✓ Aprovar
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['id'] ?>">
                      ✗ Rejeitar
                    </button>
                  </td>
                </tr>
                
                <!-- Modal Ver Motivo -->
                <div class="modal fade" id="viewReasonModal<?= $r['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Motivo da Solicitação</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p><strong>O.S:</strong> #<?= h($r['os_code']) ?></p>
                        <p><strong>Solicitante:</strong> <?= h($r['requester_name']) ?></p>
                        <p><strong>Motivo:</strong></p>
                        <div class="p-3 bg-light rounded"><?= nl2br(h($r['reason'])) ?></div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Modal Aprovar -->
                <div class="modal fade" id="approveModal<?= $r['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">✓ Aprovar Solicitação</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                      </div>
                      <form method="post">
                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                        <div class="modal-body">
                          <p><strong>O.S:</strong> #<?= h($r['os_code']) ?> - <?= h($r['client_name']) ?></p>
                          <p><strong>Tipo:</strong> <?= $r['request_type'] === 'reopen' ? '🔄 Reabertura' : '🗑️ Exclusão' ?></p>
                          <p><strong>Solicitado por:</strong> <?= h($r['requester_name']) ?></p>
                          <p><strong>Motivo:</strong></p>
                          <div class="p-2 bg-light rounded mb-3"><small><?= nl2br(h($r['reason'])) ?></small></div>
                          
                          <div class="mb-3">
                            <label class="form-label">Observações da Aprovação (opcional)</label>
                            <textarea class="form-control" name="review_notes" rows="2" placeholder="Adicione observações se necessário..."></textarea>
                          </div>
                          
                          <div class="alert alert-warning mb-0">
                            <small><strong>⚠️ Ao aprovar:</strong><br>
                            <?php if($r['request_type'] === 'reopen'): ?>
                              - Lançamentos do caixa serão revertidos<br>
                              - Status mudará para "atendimento"<br>
                              - O.S ficará disponível para edição
                            <?php else: ?>
                              - O.S será marcada como "excluída"<br>
                              - Lançamentos do caixa serão revertidos<br>
                              - Log será salvo permanentemente
                            <?php endif; ?>
                            </small>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                          <button type="submit" name="approve_request" class="btn btn-success">✓ Confirmar Aprovação</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                
                <!-- Modal Rejeitar -->
                <div class="modal fade" id="rejectModal<?= $r['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">✗ Rejeitar Solicitação</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                      </div>
                      <form method="post">
                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                        <div class="modal-body">
                          <p><strong>O.S:</strong> #<?= h($r['os_code']) ?> - <?= h($r['client_name']) ?></p>
                          <p><strong>Solicitado por:</strong> <?= h($r['requester_name']) ?></p>
                          
                          <div class="mb-3">
                            <label class="form-label"><strong>Motivo da Rejeição *</strong></label>
                            <textarea class="form-control" name="review_notes" rows="3" required placeholder="Explique por que está rejeitando..."></textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                          <button type="submit" name="reject_request" class="btn btn-danger">✗ Confirmar Rejeição</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      
      <!-- Histórico -->
      <?php
      $processed = array_filter($requests, fn($r) => $r['status'] !== 'pending');
      if(!empty($processed)):
      ?>
        <h5 class="mt-4">📋 Histórico de Solicitações</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Data Solicitação</th>
                <th>Tipo</th>
                <th>O.S</th>
                <th>Solicitante</th>
                <th>Status</th>
                <th>Revisado por</th>
                <th>Data Revisão</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($processed as $r): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($r['requested_at'])) ?></td>
                  <td>
                    <?php if($r['request_type'] === 'reopen'): ?>
                      <small>🔄 Reabertura</small>
                    <?php else: ?>
                      <small>🗑️ Exclusão</small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($r['os_id']) ?>" target="_blank">
                      #<?= h($r['os_code']) ?>
                    </a>
                  </td>
                  <td><small><?= h($r['requester_name']) ?></small></td>
                  <td>
                    <?php if($r['status'] === 'approved'): ?>
                      <span class="badge bg-success">✓ Aprovada</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">✗ Rejeitada</span>
                    <?php endif; ?>
                  </td>
                  <td><small><?= h($r['reviewer_name']) ?></small></td>
                  <td><small><?= $r['reviewed_at'] ? date('d/m/Y H:i', strtotime($r['reviewed_at'])) : '-' ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      
    <?php endif; ?>
  </div>
</div>
