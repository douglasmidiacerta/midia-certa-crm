<?php
require_role(['admin','vendas']);

function next_business_day(string $date): string {
  $ts = strtotime($date);
  if($ts === false) return date('Y-m-d');
  do {
    $ts = strtotime('+1 day', $ts);
    $dow = (int)date('N', $ts);
  } while($dow >= 6);
  return date('Y-m-d', $ts);
}

$id = (int)($_GET['id'] ?? 0);
if(!$id){
  flash_set('danger','O.S não especificada.');
  redirect($base.'/app.php?page=os');
}

// Busca O.S
$st = $pdo->prepare("SELECT o.*, c.name as client_name FROM os o JOIN clients c ON c.id=o.client_id WHERE o.id=?");
$st->execute([$id]);
$os = $st->fetch();

if(!$os){
  flash_set('danger','O.S não encontrada.');
  redirect($base.'/app.php?page=os');
}

// Só permite editar ORÇAMENTOS
if(($os['doc_kind'] ?? 'sale') !== 'budget'){
  flash_set('danger','Apenas ORÇAMENTOS podem ser editados. Vendas já finalizadas não podem ser alteradas.');
  redirect($base.'/app.php?page=os_view&id='.$id);
}

// Busca linhas (itens)
$st = $pdo->prepare("SELECT l.*, i.name as item_name, i.type as item_type 
                     FROM os_lines l 
                     JOIN items i ON i.id=l.item_id 
                     WHERE l.os_id=?");
$st->execute([$id]);
$lines = $st->fetchAll();

// Busca títulos a receber (entrada/saldo)
$st = $pdo->prepare("SELECT * FROM ar_titles WHERE os_id=? ORDER BY kind");
$st->execute([$id]);
$ar_titles = $st->fetchAll();

// Busca clientes para o select
$clients = $pdo->query("SELECT id,name,phone FROM clients WHERE active=1 ORDER BY name")->fetchAll();

// Busca categorias e itens
$categories = $pdo->query("SELECT id,name FROM item_categories WHERE active=1 ORDER BY name")->fetchAll();
$items_by_category = [];
$items_query = $pdo->query("SELECT id,name,type,category_id,price,cost FROM items WHERE active=1 ORDER BY name");
foreach($items_query as $item){
  $cat_id = $item['category_id'] ?? 0;
  if(!isset($items_by_category[$cat_id])) $items_by_category[$cat_id] = [];
  $items_by_category[$cat_id][] = $item;
}

// Busca adquirentes de cartão
$acquirers = $pdo->query("SELECT id,name FROM card_acquirers WHERE active=1 ORDER BY name")->fetchAll();

// Processamento do formulário
if($_SERVER['REQUEST_METHOD']==='POST'){
  $client_id = (int)($_POST['client_id'] ?? 0);
  $delivery_method = $_POST['delivery_method'] ?? 'retirada';
  $delivery_fee_charged = 0.0;
  if($delivery_method !== 'retirada'){
    $delivery_fee_charged = (float)str_replace(',','.',($_POST['delivery_fee_charged'] ?? '0'));
  }
  $delivery_deadline = $_POST['delivery_deadline'] ?? null;
  if($delivery_deadline){
    $delivery_deadline = date('Y-m-d', strtotime($delivery_deadline));
  }
  $notes = trim($_POST['notes'] ?? '');
  
  $entry_amount = (float)str_replace(',','.',($_POST['entry_amount'] ?? '0'));
  $entry_method = $_POST['entry_method'] ?? 'pix';
  $entry_card_acquirer_id = null;
  $entry_installments = 1;
  
  if(strpos($entry_method, 'cartao_') === 0){
    $entry_card_acquirer_id = (int)substr($entry_method, 7);
    $entry_installments = (int)($_POST['entry_installments'] ?? 1);
    if($entry_installments < 1) $entry_installments = 1;
  }
  
  $saldo_amount = (float)str_replace(',','.',($_POST['saldo_amount'] ?? '0'));
  
  if(!$client_id){
    flash_set('danger','Selecione um cliente.');
    redirect($base.'/app.php?page=os_edit&id='.$id);
  }
  
  $pdo->beginTransaction();
  
  // Atualiza OS
  $st = $pdo->prepare("UPDATE os SET client_id=?, delivery_method=?, delivery_fee_charged=?, delivery_deadline=?, notes=? WHERE id=?");
  $st->execute([$client_id, $delivery_method, $delivery_fee_charged, $delivery_deadline, $notes, $id]);
  
  // Remove linhas antigas e cria novas
  $pdo->prepare("DELETE FROM os_lines WHERE os_id=?")->execute([$id]);
  
  $item_ids = $_POST['item_id'] ?? [];
  $qtys = $_POST['qty'] ?? [];
  $prices = $_POST['unit_price'] ?? [];
  $line_notes = $_POST['line_notes'] ?? [];
  
  $stL = $pdo->prepare("INSERT INTO os_lines (os_id,item_id,qty,unit_price,unit_cost,notes,created_at) VALUES (?,?,?,?,0,?,NOW())");
  for($i=0;$i<count($item_ids);$i++){
    $iid = (int)$item_ids[$i];
    if(!$iid) continue;
    $q = (float)str_replace(',','.',($qtys[$i] ?? '1'));
    $p = (float)str_replace(',','.',($prices[$i] ?? '0'));
    $n = trim($line_notes[$i] ?? '');
    $stL->execute([$id,$iid,$q,$p,$n]);
  }
  
  // Remove títulos antigos e cria novos (orçamento sempre mantém como rascunho)
  $pdo->prepare("DELETE FROM ar_titles WHERE os_id=?")->execute([$id]);
  
  // Entrada
  if($entry_amount > 0){
    if($entry_card_acquirer_id > 0){
      $acq = $pdo->prepare("SELECT * FROM card_acquirers WHERE id=?");
      $acq->execute([$entry_card_acquirer_id]);
      $acquirer = $acq->fetch();
      
      if($acquirer){
        $stFee = $pdo->prepare("SELECT fee_percent FROM card_acquirer_fees WHERE acquirer_id=? AND installments=?");
        $stFee->execute([$entry_card_acquirer_id, $entry_installments]);
        $feeRow = $stFee->fetch();
        $tax_percent = $feeRow ? $feeRow['fee_percent'] : 0;
        
        $tax_amount = ($entry_amount * $tax_percent) / 100;
        $net_amount = $entry_amount - $tax_amount;
        
        $payment_days = (int)($acquirer['payment_days'] ?? 30);
        $installment_value = $entry_amount / $entry_installments;
        $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, card_acquirer_id, tax_amount, net_amount, created_at) 
                              VALUES (?,?,?,?,?,'rascunho',?,?,?,NOW())");
        
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
          $stAR->execute([$id, 'entrada', $installment_value, $entry_method, $due, $entry_card_acquirer_id, $tax_amount / $entry_installments, $net_amount / $entry_installments]);
        }
      } else {
        $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, created_at) VALUES (?,?,?,?,?,'rascunho',NOW())");
        $stAR->execute([$id,'entrada',$entry_amount,$entry_method,date('Y-m-d')]);
      }
    } else {
      $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, created_at) VALUES (?,?,?,?,?,'rascunho',NOW())");
      $stAR->execute([$id,'entrada',$entry_amount,$entry_method,date('Y-m-d')]);
    }
  }
  
  // Saldo
  if($saldo_amount > 0){
    $stAR = $pdo->prepare("INSERT INTO ar_titles (os_id, kind, amount, method, due_date, status, created_at) VALUES (?,?,?,'na_retirada',?,'rascunho',NOW())");
    $stAR->execute([$id,'saldo',$saldo_amount,date('Y-m-d')]);
  }
  
  $pdo->commit();
  audit($pdo,'edit','os',$id,['type'=>'budget_edit']);
  
  flash_set('success','Orçamento atualizado com sucesso!');
  redirect($base.'/app.php?page=os_view&id='.$id);
}

// Prepara dados para o formulário
$entrada = 0; $saldo = 0;
$entry_method = 'pix';
$entry_installments = 1;
foreach($ar_titles as $t){
  if($t['kind']==='entrada'){
    $entrada += (float)$t['amount'];
    $entry_method = $t['method'];
    if(!empty($t['card_acquirer_id'])){
      $entry_method = 'cartao_'.$t['card_acquirer_id'];
      $entry_installments = (int)($t['installment_number'] ?? 1);
    }
  }
  if($t['kind']==='saldo') $saldo += (float)$t['amount'];
}
?>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 style="font-weight:900">✏️ Editar Orçamento #<?= h($os['code']) ?></h5>
      <div class="text-muted small">Apenas orçamentos podem ser editados livremente. Altere produtos, preços e formas de pagamento.</div>
    </div>
    <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($id) ?>">← Voltar</a>
  </div>

  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Cliente *</label>
      <select class="form-select" name="client_id" required>
        <option value="">Selecione...</option>
        <?php foreach($clients as $c): ?>
          <option value="<?= h($c['id']) ?>" <?= $c['id']==$os['client_id']?'selected':'' ?>><?= h($c['name']) ?><?= $c['phone']?(' • '.h($c['phone'])):'' ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Entrega</label>
      <select class="form-select" name="delivery_method" id="deliveryMethod" onchange="toggleDeliveryFee()">
        <option value="retirada" <?= $os['delivery_method']==='retirada'?'selected':'' ?>>Retirada</option>
        <option value="motoboy" <?= $os['delivery_method']==='motoboy'?'selected':'' ?>>Motoboy</option>
        <option value="correios" <?= $os['delivery_method']==='correios'?'selected':'' ?>>Correios</option>
      </select>
    </div>

    <div class="col-md-3" id="deliveryFeeBox" style="display:<?= $os['delivery_method']!=='retirada'?'block':'none' ?>;">
      <label class="form-label">Valor do Frete</label>
      <input class="form-control money" name="delivery_fee_charged" id="deliveryFeeInput" value="<?= number_format((float)$os['delivery_fee_charged'],2,',','') ?>" inputmode="decimal">
    </div>
    
    <div class="col-md-3">
      <label class="form-label">Prazo de Entrega</label>
      <input type="date" class="form-control" name="delivery_deadline" value="<?= h($os['delivery_deadline']) ?>">
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
          <tbody>
            <?php foreach($lines as $line): ?>
              <tr>
                <td>
                  <select class="form-select form-select-sm category-select">
                    <option value="">-- Categoria --</option>
                    <?php foreach($categories as $cat): ?>
                      <option value="<?= h($cat['id']) ?>"><?= h($cat['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select class="form-select form-select-sm" name="item_id[]">
                    <option value="<?= h($line['item_id']) ?>" selected><?= h($line['item_name']) ?> (<?= h($line['item_type']) ?>)</option>
                  </select>
                </td>
                <td><input class="form-control form-control-sm qty-input" name="qty[]" value="<?= h($line['qty']) ?>"></td>
                <td><input class="form-control form-control-sm money price-input" name="unit_price[]" value="<?= number_format((float)$line['unit_price'],2,',','') ?>" inputmode="decimal"></td>
                <td><input class="form-control form-control-sm" name="line_notes[]" value="<?= h($line['notes']) ?>" placeholder="Obs"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remover">×</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLine()">+ Adicionar item</button>
    </div>

    <div class="col-md-3">
      <label class="form-label">Entrada (R$)</label>
      <input class="form-control money" name="entry_amount" id="entryAmount" value="<?= number_format($entrada,2,',','') ?>" inputmode="decimal">
    </div>
    
    <div class="col-md-3">
      <label class="form-label">Forma da entrada</label>
      <select class="form-select" name="entry_method" id="entryMethod" onchange="togglePaymentFields('entry')">
        <option value="pix" <?= $entry_method==='pix'?'selected':'' ?>>Pix</option>
        <option value="dinheiro" <?= $entry_method==='dinheiro'?'selected':'' ?>>Dinheiro</option>
        <option value="boleto" <?= $entry_method==='boleto'?'selected':'' ?>>Boleto</option>
        <optgroup label="Cartão de Crédito">
          <?php foreach($acquirers as $acq): ?>
            <option value="cartao_<?= h($acq['id']) ?>" <?= $entry_method==='cartao_'.$acq['id']?'selected':'' ?>><?= h($acq['name']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
    </div>
    
    <div class="col-md-2" id="entryCardInstallments" style="display:<?= strpos($entry_method,'cartao_')===0?'block':'none' ?>;">
      <label class="form-label">Parcelas (Cartão)</label>
      <select class="form-select" name="entry_installments">
        <?php for($i = 1; $i <= 21; $i++): ?>
          <option value="<?= $i ?>" <?= $i===$entry_installments?'selected':'' ?>><?= $i ?>x</option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Saldo (R$)</label>
      <input class="form-control" name="saldo_amount" id="saldoAmount" value="<?= number_format($saldo,2,',','') ?>" readonly>
      <div class="text-muted small">Calculado automaticamente: total - entrada.</div>
    </div>

    <div class="col-12">
      <label class="form-label">Observações internas</label>
      <textarea class="form-control" name="notes" rows="3" placeholder="Detalhes do pedido, instruções, etc."><?= h($os['notes']) ?></textarea>
    </div>

    <div class="col-12">
      <button class="btn btn-success" type="submit">💾 Salvar Alterações</button>
      <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=os_view&id=<?= h($id) ?>">Cancelar</a>
    </div>
  </form>
</div>

<script>
const PRODUCTS_BY_CATEGORY = <?= json_encode($items_by_category, JSON_UNESCAPED_UNICODE) ?>;
const CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;

const form = document.querySelector('form');
const entryEl = document.getElementById('entryAmount');
const saldoEl = document.getElementById('saldoAmount');

function toNumber(v){ return parseFloat(String(v).replace(/[^\d,\-]/g,'').replace(',','.')) || 0; }
function fmtMoney(v){ return v.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function normalizeMoneyInput(input){
  let v = toNumber(input.value);
  input.value = fmtMoney(v);
}

function recalc(){
  let total = 0;
  document.querySelectorAll('#linesTbl tbody tr').forEach(tr => {
    const q = toNumber(tr.querySelector('input[name="qty[]"]').value);
    const p = toNumber(tr.querySelector('input[name="unit_price[]"]').value);
    total += q * p;
  });
  const entry = toNumber(entryEl.value);
  const saldo = Math.max(0, total - entry);
  saldoEl.value = fmtMoney(saldo);
}

function toggleDeliveryFee(){
  const method = document.getElementById('deliveryMethod').value;
  const box = document.getElementById('deliveryFeeBox');
  box.style.display = (method !== 'retirada') ? 'block' : 'none';
}

function togglePaymentFields(type){
  const method = document.getElementById(type + 'Method').value;
  const cardBox = document.getElementById(type + 'CardInstallments');
  if(cardBox) cardBox.style.display = method.startsWith('cartao_') ? 'block' : 'none';
}

function addLine(){
  const tb = document.querySelector('#linesTbl tbody');
  const tr = document.createElement('tr');
  
  const catSel = document.createElement('select');
  catSel.className = 'form-select form-select-sm category-select';
  catSel.innerHTML = '<option value="">-- Categoria --</option>' + CATEGORIES.map(c =>
    `<option value="${c.id}">${c.name}</option>`
  ).join('');
  
  const prodSel = document.createElement('select');
  prodSel.className = 'form-select form-select-sm';
  prodSel.name = 'item_id[]';
  prodSel.innerHTML = '<option value="">Selecione categoria...</option>';
  prodSel.disabled = true;
  
  catSel.onchange = () => {
    const catId = catSel.value;
    if(!catId){
      prodSel.innerHTML = '<option value="">Selecione categoria...</option>';
      prodSel.disabled = true;
      return;
    }
    
    const products = PRODUCTS_BY_CATEGORY[catId] || [];
    prodSel.innerHTML = '<option value="">-- Produto --</option>' + products.map(p =>
      `<option value="${p.id}" data-price="${p.price}">${p.name} (${p.type})</option>`
    ).join('');
    prodSel.disabled = false;
  };
  
  prodSel.onchange = () => {
    const opt = prodSel.options[prodSel.selectedIndex];
    const pRaw = opt.getAttribute('data-price') || '0';
    tr.querySelector('input[name="unit_price[]"]').value = fmtMoney(toNumber(pRaw));
    recalc();
  };

  tr.innerHTML = `
    <td style="width:180px"></td>
    <td></td>
    <td style="width:80px"><input class="form-control form-control-sm qty-input" name="qty[]" value="1"></td>
    <td style="width:120px"><input class="form-control form-control-sm money price-input" name="unit_price[]" value="0,00" inputmode="decimal"></td>
    <td><input class="form-control form-control-sm" name="line_notes[]" placeholder="Obs"></td>
    <td style="width:50px"><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remover">×</button></td>
  `;
  tr.children[0].appendChild(catSel);
  tr.children[1].appendChild(prodSel);
  tr.querySelector('.remove-line').onclick = () => { tr.remove(); recalc(); };
  tr.querySelector('.qty-input').addEventListener('input', recalc);
  const priceEl = tr.querySelector('.price-input');
  priceEl.addEventListener('input', recalc);
  priceEl.addEventListener('blur', () => { normalizeMoneyInput(priceEl); recalc(); });
  tb.appendChild(tr);
}

// Event listeners para linhas existentes
document.querySelectorAll('.remove-line').forEach(btn => {
  btn.onclick = () => { btn.closest('tr').remove(); recalc(); };
});

document.querySelectorAll('.qty-input, .price-input').forEach(inp => {
  inp.addEventListener('input', recalc);
  if(inp.classList.contains('price-input')){
    inp.addEventListener('blur', () => { normalizeMoneyInput(inp); recalc(); });
  }
});

entryEl.addEventListener('input', recalc);
entryEl.addEventListener('blur', () => { normalizeMoneyInput(entryEl); recalc(); });

recalc();
</script>
