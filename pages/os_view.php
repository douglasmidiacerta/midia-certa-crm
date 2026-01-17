<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_login();
$id = (int)($_GET['id'] ?? 0);
if(!$id){ flash_set('danger','O.S inválida'); redirect($base.'/app.php?page=os'); exit; }

$st = $pdo->prepare("SELECT o.*,
                            COALESCE(c.name, '(Cliente não encontrado)') as client_name,
                            COALESCE(NULLIF(c.whatsapp,''), c.phone) client_phone,
                            c.address_street, c.address_number, c.address_neighborhood, c.address_city, c.address_state, c.address_complement,
                            COALESCE(u.name, '(Vendedor não encontrado)') as seller_name
                     FROM os o
                     LEFT JOIN clients c ON c.id=o.client_id
                     LEFT JOIN users u ON u.id=o.seller_user_id
                     WHERE o.id=?");
$st->execute([$id]);
$os = $st->fetch();
if(!$os){ flash_set('danger','O.S não encontrada'); redirect($base.'/app.php?page=os'); exit; }

$lines = $pdo->prepare("SELECT l.*, COALESCE(i.name, '(Item removido)') as item_name, i.type item_type FROM os_lines l LEFT JOIN items i ON i.id=l.item_id WHERE l.os_id=? ORDER BY l.id");
$lines->execute([$id]);
$lines = $lines->fetchAll();

$ar = $pdo->prepare("SELECT t.* FROM ar_titles t WHERE t.os_id=? ORDER BY t.id");
$ar->execute([$id]);
$ar = $ar->fetchAll();

$files = $pdo->prepare("SELECT f.* FROM os_files f WHERE f.os_id=? ORDER BY f.id DESC");

// Regra: após "Gerar venda", vendedor não altera mais nada (somente visualizar/imprimir)
if(user_role()==='vendas' && ($os['status'] ?? '')!=='atendimento' && ($os['status'] ?? '')!=='refugado'){
  if($_SERVER['REQUEST_METHOD']==='POST'){
    flash_set('danger','Esta O.S já foi enviada para o Financeiro e não pode mais ser alterada pelo vendedor.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }
}
$files->execute([$id]);
$files = $files->fetchAll();

$cash_accounts = $pdo->query("SELECT id,name FROM accounts WHERE active=1 ORDER BY name")->fetchAll();

$audit_st = $pdo->prepare("
  SELECT a.*, u.name AS user_name
  FROM audit_logs a
  LEFT JOIN users u ON u.id = a.user_id
  WHERE a.entity = 'os' AND a.entity_id = ?
  ORDER BY a.created_at DESC
  LIMIT 50
");
$audit_st->execute([$id]);
$audit_rows = $audit_st->fetchAll();

function has_kind($files, $kind): bool {
  foreach($files as $f) if(($f['kind'] ?? '')===$kind) return true;
  return false;
}

function has_arte($files): bool {
  foreach($files as $f) if($f['kind']==='arte_pdf') return true;
  return false;
}

$action = $_POST['action'] ?? null;
if($_SERVER['REQUEST_METHOD']==='POST' && $action){
  // Upload files
  if($action==='upload_file'){
    require_login();
    $kind = $_POST['kind'] ?? 'outro';

    if(empty($_FILES['file']['name'])){
      flash_set('danger','Selecione um arquivo.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }

    $uploadDir = __DIR__ . '/../uploads/os_'.$id;
    if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $orig = $_FILES['file']['name'];
    $tmp  = $_FILES['file']['tmp_name'];
    $mime = $_FILES['file']['type'] ?? '';
    $size = (int)($_FILES['file']['size'] ?? 0);

    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', pathinfo($orig, PATHINFO_FILENAME));
    $fname = date('Ymd_His')."_".$safe.".".$ext;
    $dest = $uploadDir.'/'.$fname;

    if(!move_uploaded_file($tmp, $dest)){
      flash_set('danger','Falha ao enviar arquivo.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    $rel = 'uploads/os_'.$id.'/'.$fname;

    $stF = $pdo->prepare("INSERT INTO os_files (os_id,kind,file_path,original_name,mime,size,created_by_user_id,created_at)
                          VALUES (?,?,?,?,?,?,?,?)");
    $stF->execute([$id,$kind,$rel,$orig,$mime,$size,user_id(),now()]);
    $file_id = (int)$pdo->lastInsertId();

    audit($pdo,'upload','os_files',$file_id,['os_id'=>$id,'kind'=>$kind,'name'=>$orig]);

    flash_set('success','Arquivo anexado.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }

  // Ação: enviar para aprovação do cliente (gera token e link)
  if($action==='send_approval'){
    require_once __DIR__ . '/../config/os_tokens.php';
    
    if(!has_arte($files)){
      flash_set('danger','Anexe a arte (PDF) antes de enviar para aprovação.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    
    try {
      $result = send_approval_request($pdo, $config, $id);
      
      // Atualiza status para "arte" (aguardando aprovação)
      $pdo->prepare("UPDATE os SET status='arte' WHERE id=?")->execute([$id]);
      
      audit($pdo,'send_approval','os',$id,['token'=>$result['token']]);
      
      // Redireciona direto para WhatsApp
      header('Location: ' . $result['whatsapp_link']);
      exit;
      
    } catch(Exception $e){
      flash_set('danger','Erro ao gerar link: ' . $e->getMessage());
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
  }

  // Converter orçamento -> venda (feito pelo vendedor)
  if($action==='convert_to_sale'){
    require_role(['admin','vendas']);
    if(($os['doc_kind'] ?? 'sale')!=='budget'){
      flash_set('danger','Esta O.S já é uma venda.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE os SET doc_kind='sale' WHERE id=?")->execute([$id]);
    // títulos deixam de ser rascunho e passam a contar no financeiro
    $pdo->prepare("UPDATE ar_titles SET status='aberto', due_date=COALESCE(?,due_date) WHERE os_id=? AND status='rascunho'")
        ->execute([$os['due_date'] ?? null, $id]);
    $pdo->commit();
    audit($pdo,'convert','os',$id,['from'=>'budget','to'=>'sale']);
    flash_set('success','Orçamento convertido em VENDA. Agora o financeiro passa a contar.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }

  // Criar venda (envia para o Financeiro / Análise de arquivo)
  if($action==='create_sale'){
    require_role(['admin','vendas']);
    // Não faz sentido em orçamento: use "Converter"
    if(($os['doc_kind'] ?? 'sale')==='budget'){
      flash_set('danger','Esta O.S é um orçamento. Converta para VENDA primeiro.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    if(($os['status'] ?? '')!=='atendimento' && ($os['status'] ?? '')!=='refugado'){
      flash_set('danger','A venda já foi enviada para o financeiro.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }

        // Regras obrigatórias para enviar ao Financeiro
    if(!has_kind($files,'arte_pdf')){
      flash_set('danger','Para gerar a venda, anexe a ARTE (PDF) antes.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    if(!has_kind($files,'entrada_comprovante') && !has_kind($files,'comprovante')){
      flash_set('danger','Para gerar a venda, anexe o COMPROVANTE da ENTRADA antes.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    // Verifica se cliente aprovou (via sistema online)
    $approval_check = $pdo->prepare("SELECT * FROM os_approval_tokens WHERE os_id=? AND approved=1 LIMIT 1");
    $approval_check->execute([$id]);
    if(!$approval_check->fetch()){
      flash_set('danger','Para gerar a venda, o cliente precisa aprovar a arte. Envie o link de aprovação primeiro.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }

$pdo->beginTransaction();
    
    // Status: ABERTO/ATENDIMENTO -> AGUARDANDO APROVAÇÃO DO CLIENTE
    $pdo->prepare("UPDATE os SET status='aguardando_aprovacao', sales_locked=1, sales_locked_at=NOW(), sales_locked_by_user_id=? WHERE id=?")->execute([user_id(),$id]);
    
    // Títulos deixam de ser rascunho e passam a contar no financeiro
    $pdo->prepare("UPDATE ar_titles SET status='aberto' WHERE os_id=? AND status='rascunho'")->execute([$id]);
    
    // REGRA: Venda gera compra automática dos itens
    // Busca os itens da OS com seus fornecedores e custos
    $items_st = $pdo->prepare("
      SELECT l.*, i.name as item_name, i.type as item_type, 
             isc.supplier_id, isc.cost as supplier_cost,
             s.name as supplier_name
      FROM os_lines l
      JOIN items i ON i.id = l.item_id
      LEFT JOIN item_supplier_costs isc ON isc.item_id = l.item_id AND isc.is_default = 1
      LEFT JOIN suppliers s ON s.id = isc.supplier_id
      WHERE l.os_id = ?
    ");
    $items_st->execute([$id]);
    $items = $items_st->fetchAll();
    
    if(!empty($items)){
      // Gera código para a compra automática
      $purchase_code = 'AUTO-OS-' . $os['code'];
      
      // Cria a compra (O.C) automática
      $purchase_st = $pdo->prepare("
        INSERT INTO purchases (code, supplier_id, created_by_user_id, status, notes, created_at)
        VALUES (?, ?, ?, 'aberta', ?, NOW())
      ");
      
      // Agrupa itens por fornecedor
      $items_by_supplier = [];
      foreach($items as $item){
        $supp_id = $item['supplier_id'] ?? 0;
        if(!isset($items_by_supplier[$supp_id])) {
          $items_by_supplier[$supp_id] = [
            'supplier_name' => $item['supplier_name'] ?? 'Fornecedor não definido',
            'items' => []
          ];
        }
        $items_by_supplier[$supp_id]['items'][] = $item;
      }
      
      // Cria uma compra para cada fornecedor
      $purchase_count = 0;
      foreach($items_by_supplier as $supp_id => $data){
        $purchase_code_full = $purchase_code . ($purchase_count > 0 ? '-' . ($purchase_count + 1) : '');
        $notes = "Compra automática gerada pela venda OS #{$os['code']}";
        
        $purchase_st->execute([
          $purchase_code_full,
          $supp_id > 0 ? $supp_id : null,
          user_id(),
          $notes
        ]);
        $purchase_id = (int)$pdo->lastInsertId();
        
        // Adiciona as linhas da compra
        $purchase_line_st = $pdo->prepare("
          INSERT INTO purchase_lines (purchase_id, description, category, qty, unit_price, created_at)
          VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $total_purchase = 0;
        foreach($data['items'] as $item){
          $cost = (float)($item['supplier_cost'] ?? $item['unit_cost'] ?? 0);
          $qty = (float)$item['qty'];
          $description = $item['item_name'] . ' (' . $item['item_type'] . ')';
          
          $purchase_line_st->execute([
            $purchase_id,
            $description,
            'Produção',
            $qty,
            $cost
          ]);
          
          $total_purchase += ($qty * $cost);
        }
        
        // Cria conta a pagar para esta compra
        if($total_purchase > 0 && $supp_id > 0){
          $ap_st = $pdo->prepare("
            INSERT INTO ap_titles (purchase_id, supplier_id, description, amount, due_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'aberto', NOW())
          ");
          $ap_st->execute([
            $purchase_id,
            $supp_id,
            "Compra automática - OS #{$os['code']}",
            $total_purchase,
            date('Y-m-d', strtotime('+30 days'))
          ]);
        }
        
        $purchase_count++;
      }
    }
    
    // Gera conta a pagar para taxas de cartão (se houver)
    $card_taxes_st = $pdo->prepare("
      SELECT ar.*, ca.name as acquirer_name, ar.tax_amount
      FROM ar_titles ar
      JOIN card_acquirers ca ON ca.id = ar.card_acquirer_id
      WHERE ar.os_id = ? AND ar.tax_amount > 0 AND ar.card_acquirer_id IS NOT NULL
    ");
    $card_taxes_st->execute([$id]);
    $card_taxes = $card_taxes_st->fetchAll();
    
    foreach($card_taxes as $tax){
      // Verifica se já existe conta a pagar para esta taxa
      $exists = $pdo->prepare("SELECT COUNT(*) as c FROM ap_titles WHERE description LIKE ? AND amount = ?");
      $exists->execute(['%Taxa cartão%OS #' . $os['code'] . '%', $tax['tax_amount']]);
      
      if((int)$exists->fetch()['c'] == 0){
        // Cria conta a pagar para a taxa
        $tax_ap_st = $pdo->prepare("
          INSERT INTO ap_titles (supplier_id, description, amount, due_date, status, is_card_tax, card_acquirer_id, created_at)
          VALUES (NULL, ?, ?, ?, 'aberto', 1, ?, NOW())
        ");
        $tax_ap_st->execute([
          'Taxa cartão - ' . $tax['acquirer_name'] . ' - OS #' . $os['code'],
          $tax['tax_amount'],
          $tax['due_date'],
          $tax['card_acquirer_id']
        ]);
      }
    }
    
    $pdo->commit();

    audit($pdo,'create_sale','os',$id,['from'=>$os['status'],'to'=>'conferencia','auto_purchases_created'=>true]);
    flash_set('success','Venda enviada para o Financeiro e compras automáticas geradas!');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }

  // Atualizar status
  if($action==='update_status'){
    $new = $_POST['new_status'] ?? $_POST['status'] ?? '';
    $allowed = ['atendimento','conferencia','producao','disponivel','finalizada','cancelada','refugado'];
    if(!in_array($new,$allowed,true)){ flash_set('danger','Status inválido'); redirect($base.'/app.php?page=os_view&id='.$id); exit; }

    // regras
    $arte_ok = has_arte($files);
    if($new==='producao' && !$arte_ok){
      flash_set('danger','Para ir para PRODUÇÃO, é obrigatório anexar a ARTE (PDF).');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    // Verifica aprovação do cliente para produção
    if($new==='producao'){
      $approval_check = $pdo->prepare("SELECT * FROM os_approval_tokens WHERE os_id=? AND approved=1 LIMIT 1");
      $approval_check->execute([$id]);
      if(!$approval_check->fetch()){
        flash_set('danger','Para ir para PRODUÇÃO, o cliente precisa aprovar a arte primeiro.');
        redirect($base.'/app.php?page=os_view&id='.$id); exit;
      }
    }

    // cancelamento: somente admin (outros solicitam em etapa futura)
    if($new==='cancelada' && !can_admin()){
      flash_set('danger','Somente o ADMIN pode cancelar. Use "Solicitar cancelamento" (próxima etapa).');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }

    // Regra: O.S FINALIZADA não volta (somente com MASTER)
    if(($os['status'] ?? '')==='finalizada' && !can_admin()){
      flash_set('danger','O.S finalizada não pode ser alterada. Solicite abertura/cancelamento ao MASTER.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }

    // Quem pode mudar o quê (fluxo):
    // Vendas: atendimento/refugado -> conferencia (via botão "Criar venda") e pode anexar/editar observações.
    // Financeiro: conferencia -> producao -> disponivel -> finalizada; e pode marcar refugado.
    if($new==='conferencia' || $new==='atendimento'){
      // manter para admin apenas (vendas usa create_sale)
      require_role(['admin']);
    } elseif($new==='refugado'){
      require_role(['admin','financeiro']);
    } elseif($new==='finalizada'){
      require_role(['admin','financeiro']);
    } else {
      require_role(['admin','financeiro']);
    }

    $pdo->prepare("UPDATE os SET status=? WHERE id=?")->execute([$new,$id]);
    audit($pdo,'status','os',$id,['from'=>$os['status'],'to'=>$new]);
    flash_set('success','Status atualizado.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }

  // Solicitar reabertura/cancelamento ao MASTER (admin)
  if($action==='request_master'){
    require_role(['vendas','financeiro']);
    $reqType = $_POST['req_type'] ?? '';
    if(!in_array($reqType,['reabrir','cancelar'],true)){
      flash_set('danger','Tipo de solicitação inválido.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    $reason = trim($_POST['reason'] ?? '');
    if($reason==='') $reason = '(sem descrição)';
    // evita duplicar
    $stChk = $pdo->prepare("SELECT COUNT(*) c FROM os_master_requests WHERE os_id=? AND status='pendente'");
    $stChk->execute([$id]);
    if((int)$stChk->fetch()['c']>0){
      flash_set('warning','Já existe uma solicitação pendente para esta O.S.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    $pdo->prepare("INSERT INTO os_master_requests (os_id,req_type,reason,status,requested_by_user_id,created_at) VALUES (?,?,?,'pendente',?,NOW())")
        ->execute([$id,$reqType,$reason,user_id()]);
    audit($pdo,'request_master','os',$id,['type'=>$reqType]);
    flash_set('success','Solicitação enviada ao MASTER.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }

  // Limpar arquivos ausentes do banco (admin only)
  if($action==='clean_missing_files'){
    require_role(['admin']);
    
    $files_to_clean = $pdo->prepare("SELECT * FROM os_files WHERE os_id=?");
    $files_to_clean->execute([$id]);
    $all_files = $files_to_clean->fetchAll();
    
    $deleted_count = 0;
    foreach($all_files as $f){
      $filePath = __DIR__ . '/../' . $f['file_path'];
      if(!file_exists($filePath)){
        $pdo->prepare("DELETE FROM os_files WHERE id=?")->execute([$f['id']]);
        $deleted_count++;
        audit($pdo,'delete_missing_file','os_files',$f['id'],['os_id'=>$id,'path'=>$f['file_path']]);
      }
    }
    
    flash_set('success', "Limpeza concluída: $deleted_count arquivo(s) fantasma(s) removido(s) do banco.");
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }

  // Baixa de título a receber (exige comprovante)
  if($action==='receive_ar'){
    require_role(['admin','financeiro']);
    $ar_id = (int)($_POST['ar_id'] ?? 0);
    $account_id = (int)($_POST['account_id'] ?? 0);
    $method = $_POST['method'] ?? 'pix';

    if(!$ar_id || !$account_id){
      flash_set('danger','Informe a conta/caixa.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }

    // comprovante obrigatório
    if(empty($_FILES['file']['name'])){
      flash_set('danger','Comprovante é obrigatório para dar baixa.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }

    // upload comprovante
    $uploadDir = __DIR__ . '/../uploads/os_'.$id;
    if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $orig = $_FILES['file']['name'];
    $tmp  = $_FILES['file']['tmp_name'];
    $mime = $_FILES['file']['type'] ?? '';
    $size = (int)($_FILES['file']['size'] ?? 0);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/','_', pathinfo($orig, PATHINFO_FILENAME));
    $fname = date('Ymd_His')."_COMPROVANTE_".$safe.".".$ext;
    $dest = $uploadDir.'/'.$fname;
    if(!move_uploaded_file($tmp,$dest)){
      flash_set('danger','Falha ao enviar comprovante.');
      redirect($base.'/app.php?page=os_view&id='.$id); exit;
    }
    $rel = 'uploads/os_'.$id.'/'.$fname;

    $stF = $pdo->prepare("INSERT INTO os_files (os_id,kind,file_path,original_name,mime,size,created_by_user_id,created_at)
                          VALUES (?,?,?,?,?,?,?,?)");
    $stF->execute([$id,'comprovante',$rel,$orig,$mime,$size,user_id(),now()]);
    $file_id = (int)$pdo->lastInsertId();

    // receber
    $stT = $pdo->prepare("UPDATE ar_titles SET status='recebido', method=?, received_at=NOW(), received_by_user_id=?, account_id=?, proof_file_id=? WHERE id=? AND os_id=?");
    $stT->execute([$method,user_id(),$account_id,$file_id,$ar_id,$id]);

    // lançar movimento de caixa
    $amt = (float)$pdo->query("SELECT amount FROM ar_titles WHERE id=".$ar_id)->fetch()['amount'];
    $stM = $pdo->prepare("INSERT INTO cash_movements (account_id, movement_type, amount, description, category, reference_type, reference_id, created_by_user_id, created_at)
                          VALUES (?, 'entrada', ?, ?, ?, 'ar_title', ?, ?, ?)");
    $stM->execute([$account_id,$amt,"Recebimento O.S ".$os['code'],null,$ar_id,user_id(),now()]);

    audit($pdo,'receive','ar_titles',$ar_id,['amount'=>$amt,'account_id'=>$account_id]);

    // se todos recebidos, libera finalização (não finaliza automático)
    flash_set('success','Recebimento baixado com comprovante.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }

}

$arte_ok = has_arte($files);
$total = 0;
foreach($lines as $l){ $total += ((float)$l['qty'])*((float)$l['unit_price']); }

$entrada = 0; $saldo=0;
foreach($ar as $t){
  if($t['kind']==='entrada') $entrada += (float)$t['amount'];
  if($t['kind']==='saldo') $saldo += (float)$t['amount'];
}

$phone = preg_replace('/\D+/','', (string)$os['client_phone']);

// endereço de entrega do cliente (para motoboy)
$addrParts = [];
if(!empty($os['address_street'])) $addrParts[] = $os['address_street'];
if(!empty($os['address_number'])) $addrParts[] = $os['address_number'];
if(!empty($os['address_neighborhood'])) $addrParts[] = $os['address_neighborhood'];
if(!empty($os['address_city'])) $addrParts[] = $os['address_city'];
if(!empty($os['address_state'])) $addrParts[] = $os['address_state'];
if(!empty($os['address_complement'])) $addrParts[] = $os['address_complement'];
$clientAddr = trim(implode(' - ', array_filter($addrParts)));

// Mensagem padrão (envio de orçamento/pedido)
$waText = "Pedido - O.S ".$os['code']."\n".
          "Status: ".strtoupper($os['status'])."\n".
          "Entrada: R$ ".number_format($entrada,2,',','.')." (a confirmar)\n".
          "Saldo: R$ ".number_format($saldo,2,',','.')."\n\n".
          "ATENÇÃO: A conferência completa da arte é responsabilidade do cliente. Ao aprovar, o cliente concorda com tudo contido na O.S.";

// Mensagens operacionais (quando estiver DISPONÍVEL)
$balcaoAddr = "Av Amazonas, 1502 - loja 09 - Barro Preto - BH/MG";
$waReadyRetirada = "Seu pedido ".$os['code']." está disponível para retirada em nosso balcão.\n".
                  $balcaoAddr.".\n".
                  "Você deverá pagar o valor de R$ ".number_format($saldo,2,',','.')." (saldo devedor).";

$waReadyMotoboy = "Obaa! Seu pedido ".$os['code']." ficou pronto e você escolheu receber por motoboy.\n".
                 "Para a liberação, precisamos do pagamento do restante (se houver): R$ ".number_format($saldo,2,',','.').".\n".
                 "Após a confirmação do financeiro, o motoboy será enviado até o endereço de cadastro:\n".
                 ($clientAddr ? $clientAddr : "(endereço não cadastrado — favor confirmar)");

// Gera link de acompanhamento para o cliente
require_once __DIR__ . '/../config/os_tokens.php';
$tracking_token = get_or_create_tracking_token($pdo, $id);
$tracking_url = get_tracking_url($config, $tracking_token);

// Mensagem do WhatsApp com link de acompanhamento
$waTextWithTracking = $waText . "\n\n";
$waTextWithTracking .= "📊 *Acompanhe seu pedido em tempo real:*\n";
$waTextWithTracking .= $tracking_url . "\n\n";
$waTextWithTracking .= "Você será notificado em cada etapa! 🔔";

$waLink = $phone ? ("https://wa.me/55".$phone."?text=".urlencode($waTextWithTracking)) : "#";
$waReadyLink = $phone ? ("https://wa.me/55".$phone."?text=".urlencode(($os['delivery_method']==='motoboy'?$waReadyMotoboy:$waReadyRetirada))) : "#";

// POST: Solicitar reabertura
if($_SERVER['REQUEST_METHOD']==='POST' && ($action ?? '') === 'request_reopen'){
  // Verifica permissões (qualquer usuário logado pode solicitar, exceto se não tiver permissão de vendas)
  if(!can_sales()){
    flash_set('danger','Você não tem permissão para solicitar reabertura de O.S.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }
  
  $reason = trim($_POST['reason'] ?? '');
  if(empty($reason)){
    flash_set('danger','Informe o motivo da reabertura.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }
  
  // Cria solicitação
  $pdo->prepare("INSERT INTO os_change_requests (os_id, request_type, requested_by, reason) VALUES (?, 'reopen', ?, ?)")
    ->execute([$id, user_id(), $reason]);
  
  audit($pdo,'os','request_reopen',$id,['reason'=>$reason]);
  flash_set('success','Solicitação de reabertura enviada para aprovação do Master!');
  redirect($base.'/app.php?page=os_view&id='.$id); exit;
}

// POST: Solicitar exclusão
if($_SERVER['REQUEST_METHOD']==='POST' && ($action ?? '') === 'request_delete'){
  // Verifica permissões
  if(!can_sales() && !can_admin()){
    flash_set('danger','Você não tem permissão para solicitar exclusão de O.S.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }
  
  $reason = trim($_POST['reason'] ?? '');
  if(empty($reason)){
    flash_set('danger','Informe o motivo da exclusão.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }
  
  // Cria solicitação
  $pdo->prepare("INSERT INTO os_change_requests (os_id, request_type, requested_by, reason) VALUES (?, 'delete', ?, ?)")
    ->execute([$id, user_id(), $reason]);
  
  audit($pdo,'os','request_delete',$id,['reason'=>$reason]);
  flash_set('success','Solicitação de exclusão enviada para aprovação do Master!');
  redirect($base.'/app.php?page=os_view&id='.$id); exit;
}

// POST: Master pode excluir diretamente
if($_SERVER['REQUEST_METHOD']==='POST' && ($action ?? '') === 'delete_direct'){
  require_role(['admin']);
  
  $reason = trim($_POST['reason'] ?? '');
  if(empty($reason)){
    flash_set('danger','Informe o motivo da exclusão.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }
  
  $pdo->beginTransaction();
  
  // Calcula total
  $total_st = $pdo->prepare("SELECT SUM(qty * unit_price) as total FROM os_lines WHERE os_id=?");
  $total_st->execute([$id]);
  $total_row = $total_st->fetch();
  $os_total = $total_row['total'] ?? 0;
  
  // Salva log antes de excluir
  $os_data_json = json_encode($os);
  $pdo->prepare("INSERT INTO os_deletion_log (os_id, os_code, client_id, client_name, total_value, deleted_by, deletion_reason, os_data) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([$id, $os['code'], $os['client_id'], $os['client_name'], $os_total, user_id(), $reason, $os_data_json]);
  
  // Reverte lançamentos do caixa se houver
  try {
    // 1. Busca títulos a receber da O.S
    $ar_ids_st = $pdo->prepare("SELECT id FROM ar_titles WHERE os_id=?");
    $ar_ids_st->execute([$id]);
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
  
  // Marca como excluída (exclusão lógica)
  $pdo->prepare("UPDATE os SET status='excluida', notes=CONCAT(COALESCE(notes,''), '\n\n[EXCLUÍDA]\nMotivo: ', ?) WHERE id=?")
    ->execute([$reason, $id]);
  
  $pdo->commit();
  
  audit($pdo,'os','delete_direct',$id,['reason'=>$reason]);
  flash_set('success','O.S #'.$os['code'].' foi excluída com sucesso!');
  redirect($base.'/app.php?page=os'); exit;
}

// POST: Aprovar pedido pendente
if($_SERVER['REQUEST_METHOD']==='POST' && ($action ?? '') === 'aprovar_pedido'){
  if($os['status'] !== 'pedido_pendente'){
    flash_set('danger','Este pedido não está pendente.');
    redirect($base.'/app.php?page=os_view&id='.$id); exit;
  }
  
  $novo_status = trim($_POST['novo_status'] ?? 'atendimento');
  $observacao_aprovacao = trim($_POST['observacao_aprovacao'] ?? '');
  $sem_arte = isset($_POST['sem_arte']) ? 1 : 0;
  
  // Se aceitar sem arte, muda para "atendimento" para poder anexar depois
  if($sem_arte){
    $novo_status = 'atendimento';
    $observacao_aprovacao .= "\n[ACEITO SEM ARTE - Anexar arte e enviar para aprovação do cliente]";
  }
  
  $pdo->prepare("UPDATE os SET status=?, notes=CONCAT(COALESCE(notes,''), '\n\n[PEDIDO APROVADO]\n', ?) WHERE id=?")
    ->execute([$novo_status, $observacao_aprovacao, $id]);
  
  audit($pdo,'os','aprovar_pedido',$id,['novo_status'=>$novo_status, 'sem_arte'=>$sem_arte]);
  
  if($sem_arte){
    flash_set('success','Pedido aprovado SEM ARTE! Lembre-se de anexar a arte e enviar para aprovação do cliente.');
  } else {
    flash_set('success','Pedido aprovado com sucesso! Status alterado para: '.strtoupper($novo_status));
  }
  redirect($base.'/app.php?page=os_view&id='.$id); exit;
}
?>

<!-- Alerta Pedido Pendente -->
<?php if($os['status'] === 'pedido_pendente'): ?>
<div class="alert alert-warning mb-4" style="border-left: 5px solid #f59e0b; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
  <div class="d-flex align-items-start">
    <div style="font-size: 3rem; margin-right: 20px;">⚠️</div>
    <div class="flex-grow-1">
      <h4 class="fw-bold mb-3">🚨 PEDIDO AGUARDANDO SUA APROVAÇÃO</h4>
      
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="card bg-white">
            <div class="card-body">
              <h6 class="fw-bold mb-2">📋 Informações do Pedido</h6>
              <p class="mb-1"><strong>Origem:</strong> <?= strtoupper(h($os['origem'] ?? 'site')) ?></p>
              <p class="mb-1"><strong>Cliente:</strong> <?= h($os['client_name']) ?></p>
              <p class="mb-1"><strong>Telefone:</strong> <?= h($os['client_phone']) ?></p>
              <p class="mb-1"><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($os['created_at'])) ?></p>
              <?php if($os['pagamento_preferencial']): ?>
                <p class="mb-1"><strong>Pagamento:</strong> <?= h($os['pagamento_preferencial']) ?></p>
              <?php endif; ?>
              <?php if($os['prazo_desejado']): ?>
                <p class="mb-0"><strong>Prazo desejado:</strong> <?= date('d/m/Y', strtotime($os['prazo_desejado'])) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <div class="col-md-6">
          <div class="card bg-white">
            <div class="card-body">
              <h6 class="fw-bold mb-2">✅ Próximos Passos</h6>
              <ol class="mb-0 ps-3">
                <li>Entre em contato com o cliente</li>
                <li>Confirme forma de pagamento</li>
                <li>Defina prazo de entrega</li>
                <li>Aprove o pedido abaixo</li>
              </ol>
              
              <div class="mt-3">
                <?php if($os['client_phone']): ?>
                  <a href="https://wa.me/55<?= preg_replace('/\D/', '', $os['client_phone']) ?>?text=<?= urlencode('Olá! Recebemos seu pedido #'.$os['code'].' e estamos analisando. Em breve entraremos em contato!') ?>" target="_blank" class="btn btn-sm btn-success w-100">
                    📱 Enviar WhatsApp
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <form method="post" class="card bg-white">
        <div class="card-body">
          <input type="hidden" name="action" value="aprovar_pedido">
          
          <h6 class="fw-bold mb-3">✍️ Aprovar Pedido</h6>
          
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Mudar status para:</label>
              <select class="form-select" name="novo_status" id="novo_status" required>
                <option value="atendimento">Atendimento (padrão)</option>
                <option value="arte">Arte</option>
                <option value="conferencia">Conferência</option>
                <option value="producao">Produção</option>
              </select>
              <small class="text-muted">Escolha o status inicial após aprovação</small>
            </div>
            
            <div class="col-md-6">
              <label class="form-label fw-bold">Observações da aprovação:</label>
              <textarea class="form-control" name="observacao_aprovacao" rows="2" placeholder="Ex: Confirmado pagamento via Pix, prazo 3 dias úteis"></textarea>
            </div>
            
            <div class="col-12">
              <div class="alert alert-info mb-0">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="sem_arte" id="sem_arte" value="1">
                  <label class="form-check-label fw-bold" for="sem_arte">
                    📎 Cliente ainda não enviou a arte (aceitar sem arte)
                  </label>
                  <div class="text-muted small mt-1">
                    Marque esta opção se o pedido não possui arte. O status será "Atendimento" para você poder anexar a arte posteriormente e depois enviar para aprovação do cliente.
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Confirma a aprovação deste pedido?')">
              ✅ Aprovar e Iniciar Produção
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalRecusarPedido">
              ❌ Recusar Pedido
            </button>
          </div>
          
          <script>
          // Quando marcar "sem arte", força status para "atendimento"
          document.getElementById('sem_arte').addEventListener('change', function() {
            const selectStatus = document.getElementById('novo_status');
            if(this.checked) {
              selectStatus.value = 'atendimento';
              selectStatus.disabled = true;
            } else {
              selectStatus.disabled = false;
            }
          });
          </script>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Recusar Pedido -->
<div class="modal fade" id="modalRecusarPedido" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="new_status" value="cancelada">
        
        <div class="modal-header">
          <h5 class="modal-title">❌ Recusar Pedido</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        
        <div class="modal-body">
          <div class="alert alert-danger">
            <strong>Atenção:</strong> Ao recusar, o pedido será cancelado e o cliente será notificado.
          </div>
          
          <label class="form-label fw-bold">Motivo da recusa *</label>
          <textarea class="form-control" name="status_notes" rows="3" required placeholder="Ex: Produto fora de estoque, valor não aprovado, etc."></textarea>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
          <button type="submit" class="btn btn-danger">Confirmar Recusa</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="text-muted small">O.S</div>
          <h4 style="font-weight:900">#<?= h($os['code']) ?></h4>
          <div><b>Cliente:</b> <?= h($os['client_name']) ?> <?= $os['client_phone']?('• '.h($os['client_phone'])):'' ?></div>
          <div><b>Vendedor:</b> <?= h($os['seller_name']) ?></div>
          <div><b>Entrega:</b> <?= h($os['delivery_method']) ?></div>
        </div>
        <div class="text-end">
          <?php if(($os['status'] ?? '') === 'excluida'): ?>
            <div class="alert alert-danger p-2 mb-2">
              <strong>⛔ O.S EXCLUÍDA</strong>
            </div>
          <?php endif; ?>
          <?php if(($os['doc_kind'] ?? 'sale')==='budget'): ?>
            <div class="badge bg-warning text-dark">ORÇAMENTO</div>
          <?php elseif(($os['doc_kind'] ?? 'sale')==='reaberta'): ?>
            <div class="badge bg-info text-dark">🔄 REABERTA</div>
          <?php else: ?>
            <div class="badge bg-success">VENDA</div>
          <?php endif; ?>
          <?php if(($os['status'] ?? '') === 'excluida'): ?>
            <span class="badge bg-dark">⛔ EXCLUÍDA</span><br>
          <?php else: ?>
            <span class="badge bg-primary"><?= strtoupper(h($os['status'])) ?></span><br>
          <?php endif; ?>
          <div class="btn-group mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
              🖨️ Imprimir
            </button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= h($base) ?>/app.php?page=os_print_professional&id=<?= h($id) ?>" target="_blank">📄 OS Profissional (A4)</a></li>
              <li><a class="dropdown-item" href="<?= h($base) ?>/app.php?page=os_label&id=<?= h($id) ?>" target="_blank">🏷️ Etiqueta (9cm)</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= h($base) ?>/app.php?page=os_print&id=<?= h($id) ?>" target="_blank">📋 Impressão Simples</a></li>
            </ul>
          </div>
          <?php if(($os['doc_kind'] ?? 'sale')==='budget' && can_sales()): ?>
            <div class="mt-2">
              <a class="btn btn-sm btn-warning" href="<?= h($base) ?>/app.php?page=os_edit&id=<?= h($id) ?>">✏️ Editar Orçamento</a>
              <form method="post" class="mt-2">
                <input type="hidden" name="action" value="convert_to_sale">
                <button class="btn btn-sm btn-primary" type="submit">Converter em Venda</button>
              </form>
            </div>
          <?php endif; ?>

          <?php if(($os['doc_kind'] ?? 'sale')==='sale' && can_sales() && in_array(($os['status'] ?? ''), ['atendimento','refugado'], true)): ?>
            <form method="post" class="mt-2">
              <input type="hidden" name="action" value="create_sale">
              <button class="btn btn-sm btn-primary" type="submit">Criar venda (enviar p/ Financeiro)</button>
            </form>
          <?php endif; ?>
          <?php if($phone): ?>
            <a class="btn btn-sm btn-success mt-2" target="_blank" href="<?= h($waLink) ?>">
              📱 Enviar WhatsApp
            </a>
            <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="navigator.clipboard.writeText('<?= h($tracking_url) ?>'); alert('Link de acompanhamento copiado!')">
              📋 Link Acompanhamento
            </button>
            <?php if(($os['status'] ?? '')==='disponivel' && can_finance()): ?>
              <a class="btn btn-sm btn-success mt-2" target="_blank" href="<?= h($waReadyLink) ?>">Avisar (pedido pronto)</a>
            <?php endif; ?>
          <?php endif; ?>
          
          <!-- Botões de Solicitação de Alteração/Exclusão -->
          <div class="mt-3">
            <hr>
            <h6 class="text-muted small">AÇÕES ADMINISTRATIVAS</h6>
            
            <?php if(can_sales() && ($os['status'] ?? '') !== 'excluida'): ?>
              <!-- Botão Solicitar Reabertura -->
              <button type="button" class="btn btn-sm btn-warning mt-1" data-bs-toggle="modal" data-bs-target="#requestReopenModal">
                🔄 Solicitar Reabertura
              </button>
            <?php endif; ?>
            
            <?php if((can_sales() || can_admin()) && ($os['status'] ?? '') !== 'excluida'): ?>
              <!-- Botão Solicitar Exclusão -->
              <button type="button" class="btn btn-sm btn-danger mt-1" data-bs-toggle="modal" data-bs-target="#requestDeleteModal">
                🗑️ Solicitar Exclusão
              </button>
            <?php endif; ?>
            
            <?php if(can_admin() && ($os['status'] ?? '') !== 'excluida'): ?>
              <!-- Botão Excluir Diretamente (Master Only) -->
              <button type="button" class="btn btn-sm btn-dark mt-1" data-bs-toggle="modal" data-bs-target="#deleteDirectModal">
                ⚠️ Excluir Imediatamente (Master)
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <hr>

      <h6 style="font-weight:900">Itens</h6>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Item</th><th>Qtd</th><th>Preço</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach($lines as $l): ?>
            <tr>
              <td><?= h($l['item_name']) ?> <span class="text-muted small">(<?= h($l['item_type']) ?>)</span></td>
              <td><?= h($l['qty']) ?></td>
              <td>R$ <?= number_format((float)$l['unit_price'],2,',','.') ?></td>
              <td>R$ <?= number_format(((float)$l['qty'])*((float)$l['unit_price']),2,',','.') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end">
        <div style="min-width:240px">
          <div class="d-flex justify-content-between"><span>Total</span><b>R$ <?= number_format($total,2,',','.') ?></b></div>
          <div class="d-flex justify-content-between"><span>Entrada</span><b>R$ <?= number_format($entrada,2,',','.') ?></b></div>
          <div class="d-flex justify-content-between"><span>Saldo</span><b>R$ <?= number_format($saldo,2,',','.') ?></b></div>
        </div>
      </div>

      <hr>

      <div class="row g-2">
        <div class="col-md-6">
          <h6 style="font-weight:900">Arquivos</h6>
          <div class="small text-muted">Arte (PDF) é obrigatória para PRODUÇÃO.</div>

          <form method="post" enctype="multipart/form-data" class="d-flex gap-2 mt-2">
            <input type="hidden" name="action" value="upload_file">
            <select class="form-select form-select-sm" name="kind" style="max-width:160px">
              <option value="arte_pdf">Arte (PDF)</option>
              <option value="entrada_comprovante">Comprovante da entrada</option>
              <option value="outro">Outro</option>
            </select>
            <input class="form-control form-control-sm" type="file" name="file" required>
            <button class="btn btn-sm btn-primary" type="submit">Anexar</button>
          </form>

          <ul class="mt-3">
            <?php 
            $hasFiles = false;
            foreach($files as $f): 
              // Verifica se o arquivo existe fisicamente
              $filePath = __DIR__ . '/../' . $f['file_path'];
              $fileExists = file_exists($filePath);
              
              if($fileExists):
                $hasFiles = true;
            ?>
              <li class="small">
                <b><?= h($f['kind']) ?></b> —
                <a target="_blank" href="<?= h($base) ?>/<?= h($f['file_path']) ?>"><?= h($f['original_name'] ?: basename($f['file_path'])) ?></a>
                <span class="text-muted"> (<?= h($f['created_at']) ?>)</span>
              </li>
            <?php 
              endif;
            endforeach; 
            
            if(!$hasFiles && !empty($files)):
            ?>
              <li class="small text-warning">
                <i class="bi bi-exclamation-triangle"></i> 
                Arquivos registrados no banco mas não encontrados no servidor.
                <?php if(can_admin()): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="if(confirm('Limpar registros de arquivos ausentes?')) { document.getElementById('cleanFilesForm').submit(); }">
                    🗑️ Limpar registros
                  </button>
                <?php endif; ?>
              </li>
            <?php elseif(empty($files)): ?>
              <li class="small text-muted">Nenhum arquivo anexado ainda.</li>
            <?php endif; ?>
          </ul>
          
          <?php if(can_admin()): ?>
          <form id="cleanFilesForm" method="post" style="display:none;">
            <input type="hidden" name="action" value="clean_missing_files">
          </form>
          <?php endif; ?>
        </div>

        <div class="col-md-6">
          <h6 style="font-weight:900">Andamento</h6>

          <?php 
            // Verifica se tem token de aprovação pendente
            require_once __DIR__ . '/../config/os_tokens.php';
            $token_st = $pdo->prepare("SELECT * FROM os_approval_tokens WHERE os_id=? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
            $token_st->execute([$id]);
            $pending_token = $token_st->fetch();
            
            // Verifica se já foi aprovado
            $approved_st = $pdo->prepare("SELECT * FROM os_approval_tokens WHERE os_id=? AND approved=1 ORDER BY used_at DESC LIMIT 1");
            $approved_st->execute([$id]);
            $approval_record = $approved_st->fetch();
            
            // Verifica se foi rejeitado
            $rejected_st = $pdo->prepare("SELECT * FROM os_approval_tokens WHERE os_id=? AND rejected=1 ORDER BY used_at DESC LIMIT 1");
            $rejected_st->execute([$id]);
            $rejection_record = $rejected_st->fetch();
          ?>
          
          <?php if($rejection_record): ?>
            <div class="alert alert-danger mb-2">
              ❌ <strong>Arte Rejeitada pelo Cliente</strong><br>
              <small>Cliente: <?= h($rejection_record['client_name']) ?></small><br>
              <small>Data: <?= h($rejection_record['used_at']) ?></small><br>
              <small>IP: <?= h($rejection_record['client_ip']) ?></small><br>
              <div class="mt-2 p-2" style="background: rgba(255,255,255,0.3); border-radius: 4px;">
                <strong>Motivo da rejeição:</strong><br>
                <?= nl2br(h($rejection_record['rejection_reason'])) ?>
              </div>
              <div class="mt-2">
                <small class="text-muted">💡 Faça as correções necessárias e envie uma nova aprovação</small>
              </div>
            </div>
          <?php elseif($approval_record): ?>
            <div class="alert alert-success mb-2">
              ✅ <strong>Arte Aprovada pelo Cliente</strong><br>
              <small>Cliente: <?= h($approval_record['client_name']) ?></small><br>
              <small>Data: <?= h($approval_record['used_at']) ?></small><br>
              <small>IP: <?= h($approval_record['client_ip']) ?></small>
            </div>
          <?php elseif($pending_token): ?>
            <?php 
              $approval_url = get_approval_url($config, $pending_token['token']);
              $whatsapp_link = get_whatsapp_approval_link($os['client_phone'], $os, $approval_url);
            ?>
            <div class="alert alert-warning mb-2">
              ⏳ <strong>Aguardando Aprovação do Cliente</strong><br>
              <small>Link enviado, expira em: <?= h($pending_token['expires_at']) ?></small><br>
              <a href="<?= h($whatsapp_link) ?>" target="_blank" class="btn btn-sm btn-success mt-1">
                📱 Reenviar WhatsApp
              </a>
              <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="navigator.clipboard.writeText('<?= h($approval_url) ?>'); alert('Link copiado!')">
                📋 Copiar Link
              </button>
            </div>
          <?php else: ?>
            <?php if(can_sales() && has_arte($files)): ?>
              <form method="post" class="mb-2">
                <input type="hidden" name="action" value="send_approval">
                <button class="btn btn-sm btn-primary" type="submit">
                  🎨 Enviar Arte para Aprovação do Cliente
                </button>
                <div class="text-muted small mt-1">Cliente receberá link por WhatsApp para aprovar</div>
              </form>
            <?php elseif(!has_arte($files)): ?>
              <div class="alert alert-warning small mb-2">
                ⚠️ Anexe a arte (PDF) para enviar para aprovação do cliente
              </div>
            <?php else: ?>
              <div class="text-muted small">Aguardando envio para aprovação</div>
            <?php endif; ?>
          <?php endif; ?>

          <?php
            $isBudget = (($os['doc_kind'] ?? 'sale')==='budget');
            // Fluxo novo:
            // Aberto(atendimento) -> Análise(conferencia) -> Produção -> Disponível -> Finalizada
            // + Refugado (financeiro devolve para o vendedor corrigir)
            $statusList = [];
            if(can_admin()){
              $statusList = ['atendimento','conferencia','producao','disponivel','finalizada','refugado','cancelada'];
            } elseif(can_finance()){
              $statusList = ['conferencia','producao','disponivel','finalizada','refugado'];
            }
          ?>

          <?php if(!empty($statusList)): ?>
          <form method="post" class="d-flex gap-2 align-items-end mt-2">
            <input type="hidden" name="action" value="update_status">
            <div style="flex:1">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <?php foreach($statusList as $s): ?>
                  <option value="<?= h($s) ?>" <?= $os['status']===$s?'selected':'' ?>><?= strtoupper($s) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="text-muted small">
                Para PRODUÇÃO: precisa Arte + Aprovação. Refugado devolve para o vendedor corrigir.
                O vendedor envia novamente pelo botão "Criar venda".
              </div>
            </div>
            <button class="btn btn-primary" type="submit">Atualizar</button>
          </form>
          <?php else: ?>
            <div class="text-muted small">Status operacional é controlado pelo Financeiro/Master.</div>
          <?php endif; ?>

          <?php if(!can_admin() && ($os['status'] ?? '')==='finalizada'): ?>
            <hr>
            <h6 class="mb-1" style="font-weight:900">Solicitar ao MASTER</h6>
            <div class="text-muted small mb-2">Reabertura ou cancelamento só com liberação do admin (MASTER).</div>
            <form method="post" class="mt-2">
              <input type="hidden" name="action" value="request_master">
              <div class="row g-2">
                <div class="col-12">
                  <select class="form-select form-select-sm" name="req_type" required>
                    <option value="reabrir">Solicitar reabertura</option>
                    <option value="cancelar">Solicitar cancelamento</option>
                  </select>
                </div>
                <div class="col-12">
                  <input class="form-control form-control-sm" name="reason" placeholder="Motivo (obrigatório)" required>
                </div>
                <div class="col-12">
                  <button class="btn btn-sm btn-outline-primary" type="submit">Enviar solicitação</button>
                </div>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <hr>

      <h6 style="font-weight:900">Histórico de Alterações</h6>
      <?php if(empty($audit_rows)): ?>
        <div class="text-muted small">Nenhum registro encontrado para esta O.S.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Data</th>
                <th>Usuário</th>
                <th>Ação</th>
                <th>Detalhes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($audit_rows as $a): ?>
                <tr>
                  <td class="text-muted small"><?= h($a['created_at']) ?></td>
                  <td><?= h($a['user_name'] ?? '-') ?></td>
                  <td><span class="badge bg-light text-dark"><?= h($a['action'] ?? '') ?></span></td>
                  <td class="small">
                    <?php
                      $details = $a['details'] ?? $a['payload'] ?? null;
                      if (is_string($details)) {
                        $decoded = json_decode($details, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                          // É um JSON válido, vamos formatar melhor
                          $formatted = [];
                          foreach($decoded as $key => $value) {
                            if(is_array($value)) {
                              $formatted[] = "<strong>$key:</strong> " . json_encode($value, JSON_UNESCAPED_UNICODE);
                            } else {
                              $formatted[] = "<strong>$key:</strong> " . h($value);
                            }
                          }
                          echo implode(' | ', $formatted);
                        } else {
                          echo h($details);
                        }
                      } elseif (is_array($details)) {
                        $formatted = [];
                        foreach($details as $key => $value) {
                          if(is_array($value)) {
                            $formatted[] = "<strong>$key:</strong> " . json_encode($value, JSON_UNESCAPED_UNICODE);
                          } else {
                            $formatted[] = "<strong>$key:</strong> " . h($value);
                          }
                        }
                        echo implode(' | ', $formatted);
                      } else {
                        echo '-';
                      }
                    ?>
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

<!-- Modal: Solicitar Reabertura -->
<div class="modal fade" id="requestReopenModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title">🔄 Solicitar Reabertura da O.S</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="request_reopen">
        <div class="modal-body">
          <p class="text-muted">Esta solicitação será enviada para aprovação do Master.</p>
          <p><strong>O.S:</strong> #<?= h($os['code']) ?> - <?= h($os['client_name']) ?></p>
          <div class="mb-3">
            <label class="form-label"><strong>Motivo da Reabertura *</strong></label>
            <textarea class="form-control" name="reason" rows="4" required placeholder="Descreva o motivo da reabertura..."></textarea>
            <small class="text-muted">Explique por que precisa reabrir esta O.S.</small>
          </div>
          <div class="alert alert-info mb-0">
            <small><strong>ℹ️ O que acontece ao reabrir:</strong><br>
            - Lançamentos do caixa serão revertidos<br>
            - Você poderá fazer alterações na O.S<br>
            - As compras (O.C) não serão afetadas</small>
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

<!-- Modal: Solicitar Exclusão -->
<div class="modal fade" id="requestDeleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">🗑️ Solicitar Exclusão da O.S</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="request_delete">
        <div class="modal-body">
          <p class="text-muted">Esta solicitação será enviada para aprovação do Master.</p>
          <p><strong>O.S:</strong> #<?= h($os['code']) ?> - <?= h($os['client_name']) ?></p>
          <div class="mb-3">
            <label class="form-label"><strong>Motivo da Exclusão *</strong></label>
            <textarea class="form-control" name="reason" rows="4" required placeholder="Descreva o motivo da exclusão..."></textarea>
            <small class="text-muted">Explique por que esta O.S precisa ser excluída.</small>
          </div>
          <div class="alert alert-warning mb-0">
            <small><strong>⚠️ Atenção:</strong><br>
            - A exclusão será registrada em log<br>
            - Lançamentos do caixa serão revertidos<br>
            - Esta ação não pode ser desfeita após aprovação</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Enviar Solicitação</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Excluir Diretamente (Master) -->
<div class="modal fade" id="deleteDirectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title">⚠️ Excluir O.S Imediatamente (Master)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" onsubmit="return confirm('⚠️ ATENÇÃO: Tem certeza que deseja EXCLUIR esta O.S? Esta ação não pode ser desfeita!')">
        <input type="hidden" name="action" value="delete_direct">
        <div class="modal-body">
          <div class="alert alert-danger">
            <strong>⚠️ EXCLUSÃO IMEDIATA - APENAS MASTER</strong><br>
            Esta ação não passa por aprovação e é executada imediatamente!
          </div>
          <p><strong>O.S:</strong> #<?= h($os['code']) ?> - <?= h($os['client_name']) ?></p>
          <p><strong>Total:</strong> R$ <?= number_format((float)$os['total'], 2, ',', '.') ?></p>
          <div class="mb-3">
            <label class="form-label"><strong>Motivo da Exclusão *</strong></label>
            <textarea class="form-control" name="reason" rows="4" required placeholder="Descreva o motivo da exclusão..."></textarea>
          </div>
          <div class="alert alert-warning mb-0">
            <small><strong>O que será feito:</strong><br>
            - ✅ Log completo será salvo<br>
            - ✅ Lançamentos do caixa serão revertidos<br>
            - ⚠️ As compras (O.C) NÃO serão afetadas<br>
            - ❌ Esta ação não pode ser desfeita</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-dark">⚠️ Excluir Agora</button>
        </div>
      </form>
    </div>
  </div>
</div>
