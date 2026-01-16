<?php
require_role(['admin','vendas','financeiro']);

// Regra: na tela de NOVA VENDA não exibimos custo (custo é tratado no Financeiro/Compras)
$canSeeCost = false;

$clients = $pdo->query("SELECT id,name,phone FROM clients WHERE active=1 ORDER BY name")->fetchAll();

// Busca categorias e itens organizados
$categories = $pdo->query("SELECT id,name FROM categories WHERE active=1 ORDER BY sort_order, name")->fetchAll();
$items_by_category = [];
foreach($categories as $cat){
  $items = $pdo->prepare("SELECT i.id,i.name,i.type,i.price,i.cost FROM items i WHERE i.category_id=? AND i.active=1 ORDER BY i.name");
  $items->execute([$cat['id']]);
  $items_by_category[$cat['id']] = $items->fetchAll();
}

$acquirers = $pdo->query("SELECT id,name,payment_system FROM card_acquirers WHERE active=1 ORDER BY name")->fetchAll();

function next_os(PDO $pdo): array {
  $pdo->beginTransaction();
  $row = $pdo->query("SELECT next_os_number FROM settings WHERE id=1 FOR UPDATE")->fetch();
  if(!$row){
    $pdo->exec("INSERT INTO settings (id,next_os_number,created_at) VALUES (1,17000,NOW())");
    $n = 17000;
  } else {
    $n = (int)$row['next_os_number'];
  }

  $max_os = (int)$pdo->query("SELECT MAX(os_number) FROM os")->fetchColumn();
  if ($max_os >= $n) {
    $n = $max_os + 1;
    $next = $n + 1;
    $stmt = $pdo->prepare("UPDATE settings SET next_os_number=? WHERE id=1");
    $stmt->execute([$next]);
  } else {
    $pdo->exec("UPDATE settings SET next_os_number = next_os_number + 1 WHERE id=1");
  }
  $pdo->commit();
  $code = str_pad((string)$n, 6, '0', STR_PAD_LEFT); // 017000
  return [$n,$code];
}

function next_business_day(string $date): string {
  $ts = strtotime($date);
  if($ts === false) return date('Y-m-d');
  do {
    $ts = strtotime('+1 day', $ts);
    $dow = (int)date('N', $ts); // 1=Mon ... 7=Sun
  } while($dow >= 6);
  return date('Y-m-d', $ts);
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_os'])){
  
  $doc_kind = $_POST['doc_kind'] ?? 'sale'; // sale|budget
  if(!in_array($doc_kind,['sale','budget'],true)) $doc_kind = 'sale';

  $client_id = (int)($_POST['client_id'] ?? 0);
  $delivery_method = $_POST['delivery_method'] ?? 'retirada';
  
  // Valor do frete cobrado do cliente (quando for motoboy ou correios)
  $delivery_fee_charged = 0.0;
  if($delivery_method !== 'retirada'){
    $delivery_fee_charged = (float)str_replace(',','.',($_POST['delivery_fee_charged'] ?? '0'));
  }
  
  // Detalhes de entrega são lançados em Expedição (Financeiro/Admin)
  $delivery_fee = 0.0;
  $delivery_cost = 0.0;
  $delivery_pay_to = '';
  $delivery_pay_mode = '';
  $delivery_motoboy = '';
  $delivery_notes = '';
  
  // Prazo de entrega prometido ao cliente
  $delivery_deadline = $_POST['delivery_deadline'] ?? null;
  if($delivery_deadline){
    $delivery_deadline = date('Y-m-d', strtotime($delivery_deadline));
  }
  
  $due_date = null;
  $notes = trim($_POST['notes'] ?? '');

  $entry_amount = (float)str_replace(',','.',($_POST['entry_amount'] ?? '0'));
  $entry_method = $_POST['entry_method'] ?? 'pix';
  
  // Dados de cartão (adquirente, modalidade, parcelas)
  $entry_card_acquirer_id = null;
  $entry_installments = 1;
  
  // Verifica se é cartão (formato: cartao_ID)
  if(strpos($entry_method, 'cartao_') === 0){
    $entry_card_acquirer_id = (int)substr($entry_method, 7); // Remove "cartao_"
    $entry_installments = (int)($_POST['entry_installments'] ?? 1);
    if($entry_installments < 1) $entry_installments = 1;
  }

  $saldo_amount = (float)str_replace(',','.',($_POST['saldo_amount'] ?? '0'));
  // Vencimento do saldo é definido pelo financeiro. Aqui salvamos "hoje" apenas para não ficar nulo.
  $saldo_due = date('Y-m-d');
  $saldo_method = 'na_retirada';
  
  // Dados de cartão para o saldo
  $saldo_card_acquirer_id = null;
  $saldo_card_mode = null;
  $saldo_installments = 1;
  if($saldo_method === 'cartao'){
    $saldo_card_acquirer_id = (int)($_POST['saldo_card_acquirer_id'] ?? 0);
    $saldo_card_mode = $_POST['saldo_card_mode'] ?? 'instant';
    $saldo_installments = (int)($_POST['saldo_installments'] ?? 1);
    if($saldo_installments < 1) $saldo_installments = 1;
  }

  if(!$client_id){
    flash_set('danger','Selecione um cliente.');
    redirect($base.'/app.php?page=os_new');
  }

  [$os_number,$code] = next_os($pdo);

  $seller_user_id = user_id();

  // Validações de upload para VENDAS (obrigatório)
  if($doc_kind === 'sale'){
    // Valida PDF de Arte (obrigatório)
    if(empty($_FILES['arte_file']['name']) || $_FILES['arte_file']['error'] !== UPLOAD_ERR_OK){
      $erro_msg = 'É obrigatório anexar o PDF da ARTE para criar uma venda.';
      
      if(!empty($_FILES['arte_file']['name']) && $_FILES['arte_file']['error'] !== UPLOAD_ERR_OK){
        switch($_FILES['arte_file']['error']){
          case UPLOAD_ERR_INI_SIZE:
          case UPLOAD_ERR_FORM_SIZE:
            $erro_msg = 'Arquivo de arte muito grande. Máximo: ' . ini_get('upload_max_filesize');
            break;
          case UPLOAD_ERR_PARTIAL:
            $erro_msg = 'Upload da arte incompleto. Tente novamente.';
            break;
        }
      }
      
      flash_set('danger', $erro_msg);
      redirect($base.'/app.php?page=os_new');
    }
    
    // Valida Comprovante de Entrada (obrigatório)
    if(empty($_FILES['entrada_comprovante']['name']) || $_FILES['entrada_comprovante']['error'] !== UPLOAD_ERR_OK){
      $erro_msg = 'É obrigatório anexar o COMPROVANTE DA ENTRADA para criar uma venda.';
      
      if(!empty($_FILES['entrada_comprovante']['name']) && $_FILES['entrada_comprovante']['error'] !== UPLOAD_ERR_OK){
        switch($_FILES['entrada_comprovante']['error']){
          case UPLOAD_ERR_INI_SIZE:
          case UPLOAD_ERR_FORM_SIZE:
            $erro_msg = 'Arquivo de comprovante muito grande. Máximo: ' . ini_get('upload_max_filesize');
            break;
          case UPLOAD_ERR_PARTIAL:
            $erro_msg = 'Upload do comprovante incompleto. Tente novamente.';
            break;
        }
      }
      
      flash_set('danger', $erro_msg);
      redirect($base.'/app.php?page=os_new');
    }
    
    // Valida tipo de arquivo da arte (apenas PDF)
    $arte_ext = strtolower(pathinfo($_FILES['arte_file']['name'], PATHINFO_EXTENSION));
    if($arte_ext !== 'pdf'){
      flash_set('danger', 'A ARTE deve ser um arquivo PDF.');
      redirect($base.'/app.php?page=os_new');
    }
  }

  $pdo->beginTransaction();
  $st = $pdo->prepare("INSERT INTO os (os_number, code, client_id, seller_user_id, os_type, doc_kind, status, delivery_method, delivery_fee_charged, delivery_deadline, due_date, notes, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
  $st->execute([$os_number,$code,$client_id,$seller_user_id,'mista',$doc_kind,'atendimento',$delivery_method,$delivery_fee_charged,$delivery_deadline,$due_date,$notes]);
  $os_id = (int)$pdo->lastInsertId();

  // Campos de entrega ficam zerados aqui (Expedição atualiza depois)
  $stD = $pdo->prepare("UPDATE os SET delivery_fee=?, delivery_cost=?, delivery_pay_to=?, delivery_pay_mode=?, delivery_motoboy=?, delivery_notes=? WHERE id=?");
  $stD->execute([$delivery_fee,$delivery_cost,$delivery_pay_to,$delivery_pay_mode,$delivery_motoboy,$delivery_notes,$os_id]);

  // linhas
  $item_ids = $_POST['item_id'] ?? [];
  $qtys = $_POST['qty'] ?? [];
  $prices = $_POST['unit_price'] ?? [];
  $costs = $_POST['unit_cost'] ?? [];
  $line_notes = $_POST['line_notes'] ?? [];

  $stL = $pdo->prepare("INSERT INTO os_lines (os_id,item_id,qty,unit_price,unit_cost,notes,created_at) VALUES (?,?,?,?,?,?,NOW())");
  for($i=0;$i<count($item_ids);$i++){
    $iid = (int)$item_ids[$i];
    if(!$iid) continue;
    $q = (float)str_replace(',','.',($qtys[$i] ?? '1'));
    $p = (float)str_replace(',','.',($prices[$i] ?? '0'));
    // Vendas não informa custo (fica 0 até o admin/financeiro preencher via compras/itens)
    $c = (float)str_replace(',','.',($costs[$i] ?? '0'));
    $n = trim($line_notes[$i] ?? '');
    $stL->execute([$os_id,$iid,$q,$p,$c,$n]);
  }

  // Títulos a receber:
  // - Sempre nascem como RASCUNHO
  // - Só viram ABERTO quando o vendedor clicar em "Criar venda" na O.S
  $ar_status = 'aberto';
  
  // Entrada
  if($entry_amount > 0){
    if($entry_card_acquirer_id > 0){
      // Busca adquirente e taxa da parcela
      $acq = $pdo->prepare("SELECT * FROM card_acquirers WHERE id=?");
      $acq->execute([$entry_card_acquirer_id]);
      $acquirer = $acq->fetch();
      
      if($acquirer){
        // Busca taxa específica para o número de parcelas
        $stFee = $pdo->prepare("SELECT fee_percent FROM card_acquirer_fees WHERE acquirer_id=? AND installments=?");
        $stFee->execute([$entry_card_acquirer_id, $entry_installments]);
        $feeRow = $stFee->fetch();
        $tax_percent = $feeRow ? $feeRow['fee_percent'] : 0;
        
        $tax_amount = ($entry_amount * $tax_percent) / 100;
        $net_amount = $entry_amount - $tax_amount;
        
        // Gera parcelas a receber (baseado no sistema de pagamento da adquirente)
        $payment_days = (int)($acquirer['payment_days'] ?? 30);
        $installment_value = $entry_amount / $entry_installments;
        $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, card_acquirer_id, tax_amount, net_amount, created_at) 
                              VALUES (?,?,?,?,?,?,?,?,?,NOW())");
        
        $base_due = date('Y-m-d');
        if($payment_days === 0){
          $base_due = date('Y-m-d');
        } elseif($payment_days === 1){
          $base_due = next_business_day(date('Y-m-d'));
        } else {
          $base_due = date('Y-m-d', strtotime('+' . $payment_days . ' days'));
        }

        for($i = 1; $i <= $entry_installments; $i++){
          $due = $base_due;
          if($i > 1){
            $due = date('Y-m-d', strtotime($base_due . ' +' . (($i - 1) * 30) . ' days'));
          }
          $stAR->execute([$os_id, 'entrada', $installment_value, $entry_method, $due, $ar_status, $entry_card_acquirer_id, $tax_amount / $entry_installments, $net_amount / $entry_installments]);
        }
        
        // Gera conta a pagar com a taxa
        if($tax_amount > 0){
          $stAP = $pdo->prepare("INSERT INTO ap_titles (description, amount, due_date, status, is_card_tax, card_acquirer_id, created_at)
                                VALUES (?,?,?,?,1,?,NOW())");
          $stAP->execute(['Taxa cartão - '.$acquirer['name'].' - OS #'.$code, $tax_amount, date('Y-m-d'), 'aberto', $entry_card_acquirer_id]);
        }
      } else {
        // Sem adquirente, cria normal
        $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, created_at) VALUES (?,?,?,?,?,?,NOW())");
        $stAR->execute([$os_id,'entrada',$entry_amount,$entry_method,date('Y-m-d'),$ar_status]);
      }
    } else {
      if($entry_method === 'boleto'){
        $installments = (int)($_POST['entry_boleto_installments'] ?? 1);
        if($installments < 1) $installments = 1;
        $installment_value = $entry_amount / $installments;
        $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, created_at) VALUES (?,?,?,?,?,?,NOW())");
        for($i = 1; $i <= $installments; $i++){
          $due_raw = $_POST['entry_boleto_due_'.$i] ?? '';
          $due = $due_raw ? date('Y-m-d', strtotime($due_raw)) : date('Y-m-d', strtotime('+' . ($i * 30) . ' days'));
          $stAR->execute([$os_id,'entrada',$installment_value,$entry_method,$due,$ar_status]);
        }
      } else {
        // Pix/dinheiro/na_retirada: disponível no dia
        $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, created_at) VALUES (?,?,?,?,?,?,NOW())");
        $stAR->execute([$os_id,'entrada',$entry_amount,$entry_method,date('Y-m-d'),$ar_status]);
      }
    }
  }
  
  // Saldo (mesmo tratamento)
  if($saldo_amount > 0){
    $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, created_at) VALUES (?,?,?,?,?,?,NOW())");
    $stAR->execute([$os_id,'saldo',$saldo_amount,$saldo_method,$saldo_due,$ar_status]);
  }

  // Upload opcional já na criação (arte + comprovante da entrada)
  $uploadDir = __DIR__ . '/../uploads/os_'.$os_id;
  if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

  $insertFile = $pdo->prepare("INSERT INTO os_files (os_id,kind,file_path,original_name,mime,size,created_by_user_id,created_at)
                               VALUES (?,?,?,?,?,?,?,NOW())");

  $handleUpload = function(string $field, string $kind) use ($pdo,$uploadDir,$os_id,$insertFile){
    if(empty($_FILES[$field]['name'] ?? '')) return;
    $orig = $_FILES[$field]['name'];
    $tmp  = $_FILES[$field]['tmp_name'];
    $mime = $_FILES[$field]['type'] ?? '';
    $size = (int)($_FILES[$field]['size'] ?? 0);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', pathinfo($orig, PATHINFO_FILENAME));
    $fname = date('Ymd_His')."_".$safe.".".$ext;
    $dest = $uploadDir.'/'.$fname;
    if(!move_uploaded_file($tmp,$dest)) return;
    $rel = 'uploads/os_'.$os_id.'/'.$fname;
    $insertFile->execute([$os_id,$kind,$rel,$orig,$mime,$size,user_id()]);
  };

  // Arte (PDF/IMG) e comprovante da entrada
  $handleUpload('arte_file','arte_pdf');
  $handleUpload('entrada_comprovante','entrada_comprovante');


  $pdo->commit();
  audit($pdo,'create','os',$os_id,['code'=>$code]);

  if($doc_kind==='budget'){
    flash_set('success',"Orçamento #$code criado.");
  } else {
    flash_set('success',"O.S #$code criada como ABERTA. Quando estiver tudo certo, clique em \"Criar venda\" dentro da O.S para enviar ao Financeiro (Análise de arquivo).");
  }
  redirect($base.'/app.php?page=os_view&id='.$os_id);
}
?>
<div class="card p-3">
  <h5 style="font-weight:900">Nova Venda O.S (Ordem de Serviço)</h5>
  <div class="text-muted small mb-2">Escolha se é <b>Orçamento</b> ou <b>Venda</b>. Orçamento não gera financeiro.</div>

  <form method="post" action="<?= h($base) ?>/app.php?page=os_new" class="row g-3" enctype="multipart/form-data" id="osForm">
    <div class="col-md-6">
      <div class="d-flex align-items-center gap-2">
        <div class="flex-grow-1">
          <label class="form-label">Cliente *</label>
          <select class="form-select" name="client_id" id="clientSelect" required>
            <option value="">Selecione...</option>
            <?php foreach($clients as $c): ?>
              <option value="<?= h($c['id']) ?>"><?= h($c['name']) ?><?= $c['phone']?(' • '.h($c['phone'])):'' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-top: 26px;">
          <button type="button" class="btn btn-outline-success" onclick="openClientModal()" title="Cadastrar cliente rápido">
            + Novo
          </button>
        </div>
      </div>
    </div>

    <div class="col-md-2">
      <label class="form-label">Tipo</label>
      <select class="form-select" name="doc_kind" required>
        <option value="budget">Orçamento</option>
        <option value="sale" selected>Venda</option>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Entrega</label>
      <select class="form-select" name="delivery_method" id="deliveryMethod" onchange="toggleDeliveryFee()">
        <option value="retirada">Retirada</option>
        <option value="motoboy">Motoboy</option>
        <option value="correios">Correios</option>
      </select>
    </div>

    <div class="col-md-2" id="deliveryFeeBox" style="display:none;">
      <label class="form-label">Valor do Frete</label>
      <input class="form-control money" name="delivery_fee_charged" id="deliveryFeeInput" value="0,00" inputmode="decimal" onfocus="if(this.value==='0,00')this.value='';" onblur="if(this.value==='')this.value='0,00';">
    </div>
    
    <div class="col-md-2">
      <label class="form-label">Prazo de Entrega *</label>
      <input type="date" class="form-control" name="delivery_deadline" id="deliveryDeadline" required>
    </div>


    <div class="col-12">
      <label class="form-label">Itens (Produtos/Serviços)</label>
      <div class="table-responsive">
        <table class="table table-sm align-middle" id="linesTbl">
          <thead>
            <tr>
              <th style="width:180px">Categoria</th>
              <th>Produto</th>
              <th style="width:80px">Qtd</th>
              <th style="width:120px">Preço</th>
              <th>Obs</th>
              <th style="width:50px"></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLine()">+ Adicionar item</button>
      <div class="text-muted small mt-1">Se precisar, você pode cadastrar item depois em <b>Cadastros → Produtos & Serviços</b>.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Entrada (R$)</label>
      <input class="form-control money" name="entry_amount" value="0,00" inputmode="decimal" onfocus="if(this.value==='0,00')this.value='';" onblur="if(this.value==='')this.value='0,00';">
      <div class="text-danger small" id="entryWarning" style="display:none;">
        ⚠️ Entrada maior que o total!
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Forma da entrada</label>
      <select class="form-select" name="entry_method" id="entryMethod" onchange="togglePaymentFields('entry')">
        <option value="pix">Pix</option>
        <option value="dinheiro">Dinheiro</option>
        <option value="boleto">Boleto</option>
        <optgroup label="Cartão de Crédito">
          <?php foreach($acquirers as $acq): ?>
            <option value="cartao_<?= h($acq['id']) ?>"><?= h($acq['name']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
      <div class="text-muted small">Financeiro confirma e dá baixa.</div>
    </div>
    
    <!-- Campos de cartão para entrada -->
    <div class="col-md-2" id="entryCardInstallments" style="display:none;">
      <label class="form-label">Parcelas (Cartão)</label>
      <select class="form-select" name="entry_installments" id="entryInstallmentsSelect">
        <?php for($i = 1; $i <= 21; $i++): ?>
          <option value="<?= $i ?>"><?= $i ?>x</option>
        <?php endfor; ?>
      </select>
    </div>
    
    <!-- Campos de boleto para entrada -->
    <div class="col-md-2" id="entryBoletoInstallments" style="display:none;">
      <label class="form-label">Qtd Parcelas (Boleto)</label>
      <input type="number" class="form-control" name="entry_boleto_installments" id="entryBoletoInstallmentsInput" value="1" min="1" max="24" onchange="generateBoletoFields('entry')">
    </div>
    
    <div class="col-12" id="entryBoletoVencimentos" style="display:none;">
      <div class="card p-3 mb-2">
        <h6>Vencimentos dos Boletos</h6>
        <div id="entryBoletoVencimentosFields" class="row g-2">
          <!-- Preenchido via JS -->
        </div>
      </div>
    </div>
    

    <div class="col-md-3">
      <label class="form-label">Saldo (R$)</label>
      <input class="form-control" name="saldo_amount" value="0,00" readonly>
      <div class="text-muted small">Calculado automaticamente: total - entrada.</div>
    </div>

    <!-- Seção de Anexos reorganizada -->
    <div class="col-12 mt-3">
      <div class="card p-3">
        <h6 class="mb-3">📎 Anexar Documentos</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label"><strong>Arte para Impressão (PDF)</strong></label>
            <input class="form-control" type="file" name="arte_file" accept="application/pdf" id="arteFile">
            <div class="text-muted small mt-1">Apenas arquivos PDF.</div>
          </div>
          
          <div class="col-md-6">
            <label class="form-label"><strong>Comprovante da Entrada</strong></label>
            <input class="form-control" type="file" name="entrada_comprovante" accept="application/pdf,image/*" id="comprovanteFile">
            <div class="text-muted small mt-1">Aceita PDF e imagens.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="alert alert-warning mb-0">
        <strong>⚠️ Atenção:</strong> Para criar uma <strong>VENDA</strong>, é obrigatório anexar o PDF da arte e o comprovante da entrada. Para <strong>ORÇAMENTOS</strong>, os anexos são opcionais.
      </div>
    </div>

    <div class="col-12">
      <label class="form-label">Observações internas</label>
      <textarea class="form-control" name="notes" rows="3" placeholder="Detalhes do pedido, instruções, etc."></textarea>
    </div>

    <div class="col-12">
      <input type="submit" name="submit_os" value="✓ Criar Venda / O.S" class="btn btn-success btn-lg">
      <a class="btn btn-outline-secondary btn-lg" href="<?= h($base) ?>/app.php?page=os">Cancelar</a>
    </div>
  </form>
</div>

<!-- Modal de Cadastro Completo de Cliente (FORA do form principal) -->
<div class="modal fade" id="clientModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Novo Cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="quickClientForm">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nome *</label>
              <input type="text" class="form-control" id="quickClientName">
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp *</label>
              <input type="text" class="form-control" id="quickClientWhatsapp" placeholder="(00) 00000-0000">
            </div>
            <div class="col-md-6">
              <label class="form-label">Contato do Responsável</label>
              <input type="text" class="form-control" id="quickClientContact">
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefone Fixo</label>
              <input type="text" class="form-control" id="quickClientPhone" placeholder="(00) 0000-0000">
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input type="email" class="form-control" id="quickClientEmail">
            </div>
            <div class="col-md-3">
              <label class="form-label">CPF</label>
              <input type="text" class="form-control" id="quickClientCpf" placeholder="000.000.000-00">
            </div>
            <div class="col-md-3">
              <label class="form-label">CNPJ</label>
              <input type="text" class="form-control" id="quickClientCnpj" placeholder="00.000.000/0000-00">
            </div>
            
            <div class="col-12 mt-3">
              <h6>Endereço</h6>
            </div>
            
            <div class="col-md-3">
              <label class="form-label">CEP</label>
              <input type="text" class="form-control" id="quickClientCep" placeholder="00000-000">
            </div>
            <div class="col-md-6">
              <label class="form-label">Rua</label>
              <input type="text" class="form-control" id="quickClientStreet">
            </div>
            <div class="col-md-3">
              <label class="form-label">Número</label>
              <input type="text" class="form-control" id="quickClientNumber">
            </div>
            <div class="col-md-4">
              <label class="form-label">Bairro</label>
              <input type="text" class="form-control" id="quickClientNeighborhood">
            </div>
            <div class="col-md-4">
              <label class="form-label">Cidade</label>
              <input type="text" class="form-control" id="quickClientCity">
            </div>
            <div class="col-md-4">
              <label class="form-label">UF</label>
              <input type="text" class="form-control" id="quickClientState" maxlength="2" placeholder="SP">
            </div>
            <div class="col-12">
              <label class="form-label">Complemento</label>
              <input type="text" class="form-control" id="quickClientComplement">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="saveQuickClient()">Salvar Cliente</button>
      </div>
    </div>
  </div>
</div>

<?php
  // custo não aparece nesta tela (mantemos o input escondido para não quebrar o POST)
  $costTd = '<td style="display:none"><input class="form-control form-control-sm" name="unit_cost[]" value="0"></td>';
?>

<script>
const COST_TD_HTML = <?= json_encode($costTd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

const entryEl = document.querySelector('input[name="entry_amount"]');
const saldoEl = document.querySelector('input[name="saldo_amount"]');
const deliveryFeeInput = document.getElementById('deliveryFeeInput');

function toNumber(v){
  if(!v) return 0;
  // aceita "1.234,56" (pt-BR) e "1234.56" (ponto decimal)
  const raw = String(v).trim();
  let s = raw;
  if(raw.includes(',')){
    // Se tem vírgula, o ponto é separador de milhar
    s = raw.replace(/\./g,'').replace(',', '.');
  } else {
    // Se não tem vírgula, o ponto é decimal
    s = raw;
  }
  const n = parseFloat(s);
  return isNaN(n) ? 0 : n;
}

function fmtMoney(n){
  const v = (isNaN(n) ? 0 : n);
  return v.toFixed(2).replace('.', ',');
}

function normalizeMoneyInput(el){
  if(!el) return;
  const n = toNumber(el.value);
  el.value = fmtMoney(n);
}

let lastAlertTime = 0;

function recalc(){
  let total = 0;
  document.querySelectorAll('#linesTbl tbody tr').forEach(tr => {
    const q = toNumber(tr.querySelector('input[name="qty[]"]')?.value);
    const p = toNumber(tr.querySelector('input[name="unit_price[]"]')?.value);
    total += (q * p);
  });
  
  // Adiciona o frete ao total (se houver)
  const deliveryFee = toNumber(deliveryFeeInput?.value || '0');
  total += deliveryFee;
  
  const entry = toNumber(entryEl.value);
  
  // Valida se entrada é maior que total (aviso visual, sem bloquear)
  const warningEl = document.getElementById('entryWarning');
  if(entry > total && total > 0){
    entryEl.style.border = '2px solid #dc3545';
    entryEl.style.backgroundColor = '#ffe6e6';
    if(warningEl) warningEl.style.display = 'block';
  } else {
    entryEl.style.border = '';
    entryEl.style.backgroundColor = '';
    if(warningEl) warningEl.style.display = 'none';
  }
  
  const saldo = Math.max(0, total - entry);
  saldoEl.value = fmtMoney(saldo);
}

function toggleDeliveryFee(){
  const method = document.getElementById('deliveryMethod').value;
  const feeBox = document.getElementById('deliveryFeeBox');
  
  if(method === 'motoboy' || method === 'correios'){
    feeBox.style.display = 'block';
  } else {
    feeBox.style.display = 'none';
    deliveryFeeInput.value = '0,00';
  }
  recalc();
}

entryEl.addEventListener('input', recalc);
entryEl.addEventListener('blur', () => normalizeMoneyInput(entryEl));

if(deliveryFeeInput){
  deliveryFeeInput.addEventListener('input', recalc);
  deliveryFeeInput.addEventListener('blur', () => normalizeMoneyInput(deliveryFeeInput));
}

// Dados das adquirentes e suas taxas
const ACQUIRERS_DATA = <?= json_encode(array_map(function($acq) use ($pdo) {
  $fees = [];
  $st = $pdo->prepare("SELECT installments, fee_percent FROM card_acquirer_fees WHERE acquirer_id=?");
  $st->execute([$acq['id']]);
  while($row = $st->fetch()){
    $fees[$row['installments']] = $row['fee_percent'];
  }
  return [
    'id' => $acq['id'],
    'name' => $acq['name'],
    'payment_system' => $acq['payment_system'],
    'fees' => $fees
  ];
}, $acquirers), JSON_UNESCAPED_UNICODE) ?>;

// Controle dos campos de pagamento (cartão e boleto)
function togglePaymentFields(type) {
  const method = document.getElementById(type + 'Method').value;
  const cardInstallmentsBox = document.getElementById(type + 'CardInstallments');
  const boletoInstallmentsBox = document.getElementById(type + 'BoletoInstallments');
  const boletoVencimentosBox = document.getElementById(type + 'BoletoVencimentos');
  
  // Esconde tudo primeiro
  cardInstallmentsBox.style.display = 'none';
  boletoInstallmentsBox.style.display = 'none';
  boletoVencimentosBox.style.display = 'none';
  
  // Mostra campos específicos
  if(method.startsWith('cartao_')){
    cardInstallmentsBox.style.display = 'block';
  } else if(method === 'boleto'){
    boletoInstallmentsBox.style.display = 'block';
    boletoVencimentosBox.style.display = 'block';
    generateBoletoFields(type);
  }
}

// Gera campos de vencimento para boleto
function generateBoletoFields(type) {
  const installments = parseInt(document.getElementById(type + 'BoletoInstallmentsInput').value) || 1;
  const container = document.getElementById(type + 'BoletoVencimentosFields');
  const amountInput = document.querySelector('input[name="' + type + '_amount"]');
  const amount = toNumber(amountInput ? amountInput.value : '0');
  const valorParcela = amount > 0 ? (amount / installments) : 0;
  
  container.innerHTML = '';
  
  for(let i = 1; i <= installments; i++){
    const defaultDate = new Date();
    defaultDate.setDate(defaultDate.getDate() + (i * 30));
    const dateStr = defaultDate.toISOString().split('T')[0];
    
    const div = document.createElement('div');
    div.className = 'col-md-3';
    div.innerHTML = `
      <label class="form-label small"><strong>Parcela ${i}/${installments}</strong> - R$ ${fmtMoney(valorParcela)}</label>
      <input type="date" name="${type}_boleto_due_${i}" class="form-control form-control-sm" value="${dateStr}" required>
    `;
    container.appendChild(div);
  }
}

// Função removida - informações financeiras não devem aparecer na O.S

// Dados dos produtos por categoria (gerado pelo PHP) 
const PRODUCTS_BY_CATEGORY = <?= json_encode($items_by_category, JSON_UNESCAPED_UNICODE) ?>;
const CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;

function addLine(){
  const tb = document.querySelector('#linesTbl tbody');
  const tr = document.createElement('tr');

  // Select de categoria
  const catSel = document.createElement('select');
  catSel.className = 'form-select form-select-sm';
  catSel.innerHTML = '<option value="">-- Categoria --</option>' + CATEGORIES.map(c =>
    `<option value="${c.id}">${c.name}</option>`
  ).join('');
  
  // Select de produto (começa desabilitado)
  const prodSel = document.createElement('select');
  prodSel.className = 'form-select form-select-sm';
  prodSel.name = 'item_id[]';
  prodSel.innerHTML = '<option value="">Selecione categoria...</option>';
  prodSel.disabled = true;
  
  // Quando seleciona categoria, carrega produtos
  catSel.onchange = () => {
    const catId = catSel.value;
    if(!catId){
      prodSel.innerHTML = '<option value="">Selecione categoria...</option>';
      prodSel.disabled = true;
      return;
    }
    
    const products = PRODUCTS_BY_CATEGORY[catId] || [];
    prodSel.innerHTML = '<option value="">-- Produto --</option>' + products.map(p =>
      `<option value="${p.id}" data-price="${p.price}" data-cost="${p.cost}">${p.name} (${p.type})</option>`
    ).join('');
    prodSel.disabled = false;
  };
  
  // Quando seleciona produto, preenche preço
  prodSel.onchange = () => {
    const opt = prodSel.options[prodSel.selectedIndex];
    const pRaw = opt.getAttribute('data-price') || '0';
    tr.querySelector('input[name="unit_price[]"]').value = fmtMoney(toNumber(pRaw));
    const costEl = tr.querySelector('input[name="unit_cost[]"]');
    if(costEl) costEl.value = opt.getAttribute('data-cost') || '0';
    recalc();
  };

  tr.innerHTML = `
    <td style="width:180px"></td>
    <td></td>
    <td style="width:80px"><input class="form-control form-control-sm" name="qty[]" value="1"></td>
    <td style="width:120px"><input class="form-control form-control-sm money" name="unit_price[]" value="0,00" inputmode="decimal"></td>
    ${COST_TD_HTML}
    <td><input class="form-control form-control-sm" name="line_notes[]" placeholder="Obs"></td>
    <td style="width:50px"><button type="button" class="btn btn-sm btn-outline-danger" title="Remover">×</button></td>
  `;
  tr.children[0].appendChild(catSel);
  tr.children[1].appendChild(prodSel);
  tr.querySelector('button').onclick = () => { tr.remove(); recalc(); };
  tr.querySelector('input[name="qty[]"]').addEventListener('input', recalc);
  const priceEl = tr.querySelector('input[name="unit_price[]"]');
  priceEl.addEventListener('input', recalc);
  priceEl.addEventListener('blur', () => { normalizeMoneyInput(priceEl); recalc(); });
  tb.appendChild(tr);
}

addLine();
recalc();

// Define data mínima de entrega para amanhã
const deliveryDeadlineInput = document.getElementById('deliveryDeadline');
if(deliveryDeadlineInput){
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  deliveryDeadlineInput.min = tomorrow.toISOString().split('T')[0];
  
  // Calcula data padrão: 4 dias úteis (pula sábado e domingo)
  function addBusinessDays(date, days) {
    let currentDate = new Date(date);
    let addedDays = 0;
    
    while(addedDays < days) {
      currentDate.setDate(currentDate.getDate() + 1);
      const dayOfWeek = currentDate.getDay();
      
      // Se não for sábado (6) nem domingo (0), conta como dia útil
      if(dayOfWeek !== 0 && dayOfWeek !== 6) {
        addedDays++;
      }
    }
    
    return currentDate;
  }
  
  const defaultDate = addBusinessDays(today, 4);
  deliveryDeadlineInput.value = defaultDate.toISOString().split('T')[0];
}

// Modal de cadastro rápido de cliente
function openClientModal() {
  const modal = new bootstrap.Modal(document.getElementById('clientModal'));
  modal.show();
}

function saveQuickClient() {
  const name = document.getElementById('quickClientName').value.trim();
  const whatsapp = document.getElementById('quickClientWhatsapp').value.trim();
  
  if(!name || !whatsapp){
    alert('Nome e WhatsApp são obrigatórios!');
    return;
  }
  
  // Envia via AJAX com todos os campos
  const formData = new FormData();
  formData.append('action', 'quick_create_client');
  formData.append('name', name);
  formData.append('whatsapp', whatsapp);
  formData.append('contact_name', document.getElementById('quickClientContact').value.trim());
  formData.append('phone', document.getElementById('quickClientPhone').value.trim());
  formData.append('email', document.getElementById('quickClientEmail').value.trim());
  formData.append('cpf', document.getElementById('quickClientCpf').value.trim());
  formData.append('cnpj', document.getElementById('quickClientCnpj').value.trim());
  formData.append('cep', document.getElementById('quickClientCep').value.trim());
  formData.append('address_street', document.getElementById('quickClientStreet').value.trim());
  formData.append('address_number', document.getElementById('quickClientNumber').value.trim());
  formData.append('address_neighborhood', document.getElementById('quickClientNeighborhood').value.trim());
  formData.append('address_city', document.getElementById('quickClientCity').value.trim());
  formData.append('address_state', document.getElementById('quickClientState').value.trim());
  formData.append('address_complement', document.getElementById('quickClientComplement').value.trim());
  
  fetch('<?= h($base) ?>/pages/client_quick_create.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.success){
      // Adiciona o novo cliente no select
      const select = document.getElementById('clientSelect');
      const option = document.createElement('option');
      option.value = data.client_id;
      option.textContent = name + (data.whatsapp ? ' • ' + data.whatsapp : '');
      option.selected = true;
      select.appendChild(option);
      
      // Fecha o modal
      bootstrap.Modal.getInstance(document.getElementById('clientModal')).hide();
      
      // Limpa o formulário
      document.getElementById('quickClientForm').reset();
      
      alert('✅ Cliente cadastrado com sucesso!');
    } else {
      alert('❌ Erro: ' + (data.error || 'Não foi possível cadastrar o cliente.'));
    }
  })
  .catch(error => {
    alert('❌ Erro ao cadastrar cliente. Tente novamente.');
    console.error(error);
  });
}
</script>
