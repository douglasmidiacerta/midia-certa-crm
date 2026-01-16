<?php
// Página PÚBLICA para aprovação de arte pelo cliente
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/utils.php';
$config = require __DIR__ . '/config/config.php';

$token = $_GET['token'] ?? '';
if(!$token){
  die('Token inválido.');
}

// Busca o token
$st = $pdo->prepare("SELECT * FROM os_approval_tokens WHERE token=? LIMIT 1");
$st->execute([$token]);
$tok = $st->fetch();

if(!$tok){
  die('Link inválido ou expirado.');
}

if($tok['used_at']){
  die('Este link já foi utilizado.');
}

if(strtotime($tok['expires_at']) < time()){
  die('Este link expirou. Entre em contato conosco.');
}

// Busca a OS
$st = $pdo->prepare("SELECT o.*, c.name as client_name, c.whatsapp, u.name as seller_name
                     FROM os o
                     JOIN clients c ON c.id = o.client_id
                     JOIN users u ON u.id = o.seller_user_id
                     WHERE o.id=?");
$st->execute([$tok['os_id']]);
$os = $st->fetch();

if(!$os){
  die('Pedido não encontrado.');
}

// Busca arquivo PDF da arte
$st = $pdo->prepare("SELECT * FROM os_files WHERE os_id=? AND kind='arte_pdf' ORDER BY created_at DESC LIMIT 1");
$st->execute([$os['id']]);
$arte = $st->fetch();

// Busca dados do cliente para a declaração
$client_st = $pdo->prepare("SELECT name, cpf, cnpj FROM clients WHERE id=?");
$client_st->execute([$os['client_id']]);
$client_data = $client_st->fetch();

// Processa aprovação/rejeição
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';
  $client_name = trim($_POST['client_name'] ?? '');
  $signature = trim($_POST['signature'] ?? '');
  $rejection_reason = trim($_POST['rejection_reason'] ?? '');
  
  if(!$client_name){
    $error = 'Por favor, digite seu nome completo.';
  } elseif(!$signature && $action === 'approve'){
    $error = 'Por favor, assine digitalmente (digite seu nome novamente para confirmar).';
  } else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if($action === 'approve'){
      // Aprova e muda status para CONFERÊNCIA automaticamente
      $pdo->prepare("UPDATE os_approval_tokens SET used_at=NOW(), approved=1, client_ip=?, client_name=?, client_signature=? WHERE id=?")
          ->execute([$ip, $client_name, $signature, $tok['id']]);
      
      // Atualiza status: aguardando_aprovacao -> conferencia
      $pdo->prepare("UPDATE os SET approved_at=NOW(), status='conferencia' WHERE id=?")
          ->execute([$os['id']]);
      
      $success = true;
      $approved = true;
      
    } elseif($action === 'reject'){
      // Rejeita
      if(!$rejection_reason){
        $error = 'Por favor, informe o motivo da rejeição.';
      } else {
        $pdo->prepare("UPDATE os_approval_tokens SET used_at=NOW(), rejected=1, rejection_reason=?, client_ip=?, client_name=? WHERE id=?")
            ->execute([$rejection_reason, $ip, $client_name, $tok['id']]);
        
        $success = true;
        $approved = false;
      }
    }
  }
}

$base = $config['base_path'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aprovação de Arte - Mídia Certa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; padding: 20px; }
    .card { max-width: 800px; margin: 0 auto; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .header-logo { text-align: center; padding: 20px; background: #0b1f3a; color: white; border-radius: 8px 8px 0 0; }
    .terms-box { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 8px; margin: 20px 0; }
    .warning-text { color: #856404; font-weight: bold; }
    .pdf-viewer { width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header-logo">
      <h2>Mídia Certa</h2>
      <p class="mb-0">Aprovação de Arte para Impressão</p>
    </div>
    
    <div class="card-body">
      <?php if(isset($success) && $success): ?>
        <?php if($approved): ?>
          <div class="alert alert-success">
            <h4>✅ Arte Aprovada com Sucesso!</h4>
            <p class="mb-0">Obrigado, <strong><?= h($client_name) ?></strong>! Sua arte foi aprovada e seguirá para produção.</p>
            <p class="mb-0 mt-2">Entraremos em contato em breve para informar sobre o andamento.</p>
          </div>
        <?php else: ?>
          <div class="alert alert-warning">
            <h4>⚠️ Arte Rejeitada</h4>
            <p class="mb-0">Obrigado pelo feedback, <strong><?= h($client_name) ?></strong>.</p>
            <p class="mb-0 mt-2">Entraremos em contato para fazer as correções necessárias.</p>
          </div>
        <?php endif; ?>
        
      <?php else: ?>
        <h4>Pedido #<?= h($os['code']) ?></h4>
        <p><strong>Cliente:</strong> <?= h($os['client_name']) ?></p>
        
        <?php if($arte): ?>
          <div class="mb-3">
            <label class="form-label"><strong>Visualizar Arte (PDF):</strong></label>
            <iframe src="<?= h($base.'/'.$arte['file_path']) ?>" class="pdf-viewer"></iframe>
            <div class="text-center mt-2">
              <a href="<?= h($base.'/'.$arte['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">📥 Baixar PDF</a>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-warning">
            ⚠️ Ainda não há arte anexada para este pedido.
          </div>
        <?php endif; ?>
        
        <div class="terms-box">
          <h5 class="warning-text">⚠️ IMPORTANTE - LEIA COM ATENÇÃO</h5>
          <p><strong>Erros ortográficos podem acontecer, e nós da Mídia Certa queremos que sua impressão seja impecável.</strong></p>
          <p>Portanto <strong>confira sua arte antes de aprovar para impressão:</strong></p>
          <ul>
            <li>📌 Disposição de layout</li>
            <li>📌 Ortografia</li>
            <li>📌 Números de telefone</li>
            <li>📌 E-mail</li>
            <li>📌 Endereço</li>
          </ul>
          
          <div class="alert alert-warning">
            <p class="mb-2">💡 <strong>Se você confirmar a impressão desta arte, significa que está de acordo com todas as informações e conteúdo dela.</strong></p>
            <p class="mb-0">Sendo assim não nos responsabilizaremos por eventuais erros que não sejam:</p>
          </div>
          
          <ul>
            <li>🔴 Impressão em tamanho diferente do pedido</li>
            <li>🔴 Distorção de cor acima de 10%</li>
            <li>🔴 Atraso de produção superior a 24 horas</li>
          </ul>
          
          <div class="alert alert-danger" style="border: 3px solid #dc3545; background: #f8d7da;">
            <h5 class="mb-2" style="color: #721c24;"><strong>🚫 ATENÇÃO: APROVAÇÃO É DEFINITIVA E IRREVERSÍVEL!</strong></h5>
            <p class="mb-2" style="color: #721c24; font-size: 1.05rem;">
              <strong>▶ É DE EXTREMA IMPORTÂNCIA CONFERIR. DEPOIS DESSA CONFIRMAÇÃO NÃO SERÁ MAIS POSSÍVEL ALTERAR.</strong>
            </p>
            <p class="mb-0" style="color: #721c24;">
              Ao clicar em "APROVAR ARTE", o arquivo vai <strong>IMEDIATAMENTE</strong> para a impressora. 
              Não aceitamos reclamações sobre erros que você não conferiu antes de aprovar.
            </p>
          </div>
          
          <p class="small text-muted">
            Para retirada do pedido será preciso pagar o restante contra a entrega (Somente TED, dinheiro ou cartão no ato da coleta ou entrega).
          </p>
        </div>
        
        <div class="alert alert-info">
          <h6><strong>📝 Declaração de Responsabilidade</strong></h6>
          <p class="mb-2">Ao aprovar, você declara:</p>
          <div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #0d6efd;">
            <p class="mb-0" style="line-height: 1.8;">
              "Eu, <strong><?= h($client_data['name'] ?? $os['client_name']) ?></strong>, 
              <?php if(!empty($client_data['cpf'])): ?>
                portador(a) do CPF <strong><?= h($client_data['cpf']) ?></strong>, 
              <?php elseif(!empty($client_data['cnpj'])): ?>
                portador(a) do CNPJ <strong><?= h($client_data['cnpj']) ?></strong>, 
              <?php endif; ?>
              declaro que revisei a arte e estou ciente de que após a aprovação não serão aceitas alterações. 
              Autorizo a produção com base nos dados registrados sob o IP <strong><?= h($_SERVER['REMOTE_ADDR'] ?? 'não identificado') ?></strong>."
            </p>
          </div>
        </div>
        
        <?php if(isset($error)): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>
        
        <form method="post">
          <div class="mb-3">
            <label class="form-label"><strong>Seu nome completo: *</strong></label>
            <input type="text" name="client_name" class="form-control" required value="<?= h($_POST['client_name'] ?? '') ?>">
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <button type="submit" name="action" value="approve" class="btn btn-success w-100 btn-lg" onclick="return confirmApproval()">
                ✅ APROVAR ARTE
              </button>
              <small class="text-muted d-block mt-1">Declaro que conferi e aprovo</small>
            </div>
            <div class="col-md-6">
              <button type="button" class="btn btn-danger w-100 btn-lg" onclick="showRejectionForm()">
                ❌ REJEITAR ARTE
              </button>
              <small class="text-muted d-block mt-1">Preciso de alterações</small>
            </div>
          </div>
          
          <input type="hidden" name="signature" id="signature">
          
          <div id="rejectionForm" style="display: none;" class="mt-3">
            <label class="form-label"><strong>Motivo da rejeição / Alterações necessárias: *</strong></label>
            <textarea name="rejection_reason" class="form-control" rows="4" placeholder="Descreva o que precisa ser alterado..."></textarea>
            <button type="submit" name="action" value="reject" class="btn btn-warning mt-2">Enviar Rejeição</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
    
    <div class="card-footer text-center text-muted">
      <small>Mídia Certa © <?= date('Y') ?></small>
    </div>
  </div>
  
  <script>
    function confirmApproval() {
      const name = document.querySelector('input[name="client_name"]').value.trim();
      if(!name) {
        alert('Por favor, digite seu nome completo.');
        return false;
      }
      
      const confirm = prompt('Para confirmar a aprovação, digite seu nome completo novamente:');
      if(confirm && confirm.trim().toLowerCase() === name.trim().toLowerCase()) {
        document.getElementById('signature').value = confirm.trim();
        return true;
      } else {
        alert('Assinatura não confere. Por favor, digite exatamente o mesmo nome.');
        return false;
      }
    }
    
    function showRejectionForm() {
      document.getElementById('rejectionForm').style.display = 'block';
    }
  </script>
</body>
</html>
