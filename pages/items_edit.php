<?php
require_once __DIR__.'/../config/utils.php';
require_once __DIR__.'/../config/auth.php';
require_login();

function upload_item_image($file, $required, $current_path = null) {
  if (!$file || !isset($file['error'])) {
    return [$current_path, $required ? 'Envie a imagem do produto.' : null];
  }

  if ($file['error'] === UPLOAD_ERR_NO_FILE) {
    return [$current_path, $required ? 'Envie a imagem do produto.' : null];
  }

  if ($file['error'] !== UPLOAD_ERR_OK) {
    return [$current_path, 'Falha no upload da imagem.'];
  }

  $tmp = $file['tmp_name'];
  $info = @getimagesize($tmp);
  if ($info === false) {
    return [$current_path, 'Arquivo inválido. Envie uma imagem JPG, PNG ou WebP.'];
  }

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'webp'];
  if (!in_array($ext, $allowed, true)) {
    return [$current_path, 'Formato não suportado. Use JPG, PNG ou WebP.'];
  }

  $dir = __DIR__ . '/../uploads/items';
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }

  $filename = 'item_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = $dir . '/' . $filename;

  if (!move_uploaded_file($tmp, $dest)) {
    return [$current_path, 'Não foi possível salvar a imagem.'];
  }

  if ($current_path && file_exists(__DIR__ . '/../' . $current_path)) {
    unlink(__DIR__ . '/../' . $current_path);
  }

  return ['uploads/items/' . $filename, null];
}

$id = (int)($_GET['id'] ?? 0);
if($id<=0){
  flash('Item inválido.');
  header('Location: '.$base.'/app.php?page=items');
  exit;
}

$st = $pdo->prepare("SELECT * FROM items WHERE id=? LIMIT 1");
$st->execute([$id]);
$item = $st->fetch();
if(!$item){
  flash('Item não encontrado.');
  header('Location: '.$base.'/app.php?page=items');
  exit;
}

// Categorias
$cats = $pdo->query("SELECT id,name FROM categories WHERE active=1 ORDER BY sort_order, name")->fetchAll();

// Fornecedores
$suppliers = $pdo->query("SELECT id,name FROM suppliers WHERE active=1 ORDER BY name")->fetchAll();

// Fornecedores e custos do item atual
$item_suppliers = $pdo->prepare("
  SELECT isc.*, s.name as supplier_name 
  FROM item_supplier_costs isc 
  JOIN suppliers s ON s.id = isc.supplier_id 
  WHERE isc.item_id = ? AND isc.active = 1
  ORDER BY s.name
");
$item_suppliers->execute([$id]);
$current_suppliers = $item_suppliers->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? 'save';

  if($action==='delete'){
    if(!can_admin() && !can_finance()){
      flash('Sem permissão para excluir.');
      header('Location: '.$base.'/app.php?page=items_edit&id='.$id);
      exit;
    }
    $pdo->prepare("UPDATE items SET active=0 WHERE id=?")->execute([$id]);
    audit($pdo,'item','delete',$id,['name'=>$item['name']]);
    flash('Item removido (inativado).');
    header('Location: '.$base.'/app.php?page=items');
    exit;
  }

  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? 'produto';
  $category_id = (int)($_POST['category_id'] ?? 0);
  $format = trim($_POST['format'] ?? '');
  $vias = trim($_POST['vias'] ?? '');
  $colors = trim($_POST['colors'] ?? '');
  $price = money_to_decimal($_POST['price'] ?? '0');
  [$image_path, $image_error] = upload_item_image($_FILES['image'] ?? null, empty($item['image_path']), $item['image_path'] ?? null);

  if($name===''){ flash('Informe o nome.'); }
  else if($category_id<=0){ flash('Selecione a categoria.'); }
  else if($image_error){ flash($image_error); }
  else {
    $pdo->prepare("UPDATE items SET name=?, type=?, category_id=?, format=?, vias=?, colors=?, image_path=?, price=? WHERE id=?")
      ->execute([$name,$type,$category_id,$format,$vias,$colors,$image_path,$price,$id]);
    
    // Atualizar fornecedores
    $supplier_ids = $_POST['supplier_id'] ?? [];
    $supplier_costs = $_POST['supplier_cost'] ?? [];
    $supplier_notes = $_POST['supplier_notes'] ?? [];
    
    // Inativar todos os fornecedores atuais
    $pdo->prepare("UPDATE item_supplier_costs SET active=0 WHERE item_id=?")->execute([$id]);
    
    // Adicionar/atualizar fornecedores
    foreach($supplier_ids as $idx => $sup_id){
      $sup_id = (int)$sup_id;
      if($sup_id <= 0) continue;
      
      $cost = money_to_decimal($supplier_costs[$idx] ?? '0');
      $notes = trim($supplier_notes[$idx] ?? '');
      
      // Verificar se já existe
      $check = $pdo->prepare("SELECT id FROM item_supplier_costs WHERE item_id=? AND supplier_id=? LIMIT 1");
      $check->execute([$id, $sup_id]);
      $existing = $check->fetch();
      
      if($existing){
        // Atualizar
        $pdo->prepare("UPDATE item_supplier_costs SET cost=?, notes=?, active=1 WHERE id=?")
          ->execute([$cost, $notes, $existing['id']]);
      } else {
        // Inserir
        $pdo->prepare("INSERT INTO item_supplier_costs (item_id, supplier_id, cost, notes, active, created_at) VALUES (?,?,?,?,1,NOW())")
          ->execute([$id, $sup_id, $cost, $notes]);
      }
    }
    
    audit($pdo,'item','update',$id,['name'=>$name]);
    flash('Item atualizado.');
    header('Location: '.$base.'/app.php?page=items_edit&id='.$id);
    exit;
  }
}
?>

<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h3 class="mb-0">Editar produto/serviço</h3>
          <div class="text-muted">ID: #<?= (int)$item['id'] ?></div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary" href="<?= $base ?>/app.php?page=items">Voltar</a>
          <?php if(can_admin() || can_finance()): ?>
            <form method="post" onsubmit="return confirm('Excluir (inativar) este item?');">
              <input type="hidden" name="action" value="delete">
              <button class="btn btn-outline-danger">Excluir</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <hr>

      <form method="post" class="row g-3" autocomplete="off" enctype="multipart/form-data">
        <div class="col-12 col-lg-6">
          <label class="form-label">Nome *</label>
          <input class="form-control" name="name" value="<?= h($item['name']) ?>" required>
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label">Tipo</label>
          <select class="form-select" name="type">
            <option value="produto" <?= $item['type']==='produto'?'selected':'' ?>>Produto</option>
            <option value="servico" <?= $item['type']==='servico'?'selected':'' ?>>Serviço</option>
          </select>
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label">Categoria *</label>
          <select class="form-select" name="category_id" required>
            <option value="">Selecione...</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$item['category_id']===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label">Formato</label>
          <input class="form-control" name="format" value="<?= h($item['format']) ?>">
        </div>
        <div class="col-6 col-lg-4">
          <label class="form-label">Vias</label>
          <input class="form-control" name="vias" value="<?= h($item['vias']) ?>">
        </div>
        <div class="col-6 col-lg-4">
          <label class="form-label">Cores</label>
          <input class="form-control" name="colors" value="<?= h($item['colors']) ?>">
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label">Preço de venda</label>
          <input class="form-control money" name="price" value="<?= h(number_format((float)$item['price'],2,',','.')) ?>">
        </div>

        <div class="col-12 col-lg-8">
          <label class="form-label">Imagem do produto (site) <?= empty($item['image_path']) ? '*' : '' ?></label>
          <input class="form-control" type="file" name="image" accept="image/*" <?= empty($item['image_path']) ? 'required' : '' ?>>
          <?php if (!empty($item['image_path'])): ?>
            <div class="mt-2">
              <img src="<?= h($base . '/' . $item['image_path']) ?>" alt="Imagem do produto" style="max-height: 140px; border-radius: 8px;">
            </div>
          <?php endif; ?>
          <div class="text-muted small">Obrigatória para aparecer no site.</div>
        </div>

        <div class="col-12"><hr class="my-3"></div>

        <div class="col-12">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span style="font-weight:800">Fornecedores & Custos</span>
            <button class="btn btn-sm btn-outline-primary" type="button" id="addSupplierRow">+ adicionar fornecedor</button>
          </div>
          <div id="supplierRows"></div>
        </div>

        <div class="col-12">
          <button class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const suppliers = <?= json_encode($suppliers, JSON_UNESCAPED_UNICODE) ?>;
  const currentSuppliers = <?= json_encode($current_suppliers, JSON_UNESCAPED_UNICODE) ?>;
  const rows = document.getElementById('supplierRows');
  const addBtn = document.getElementById('addSupplierRow');

  function escapeHtml(str){
    return (str||'').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'}[c]));
  }

  function formatMoney(value){
    return parseFloat(value || 0).toFixed(2).replace('.', ',');
  }

  function rowTpl(supplier_id = '', cost = '0', notes = ''){
    const wrap = document.createElement('div');
    wrap.className = 'border rounded-3 p-3 mb-2 bg-light';
    wrap.innerHTML = `
      <div class="row g-2">
        <div class="col-12 col-md-5">
          <label class="form-label">Fornecedor</label>
          <select class="form-select" name="supplier_id[]">
            <option value="">Selecione...</option>
            ${suppliers.map(s => `<option value="${s.id}" ${s.id == supplier_id ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('')}
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Custo (R$)</label>
          <input class="form-control money" name="supplier_cost[]" value="${formatMoney(cost)}">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Observações</label>
          <input class="form-control" name="supplier_notes[]" value="${escapeHtml(notes)}" placeholder="Opcional">
        </div>
        <div class="col-12 col-md-1 d-flex align-items-end">
          <button type="button" class="btn btn-outline-danger w-100 remove-supplier" title="Remover">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
              <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
            </svg>
          </button>
        </div>
      </div>
    `;
    
    wrap.querySelector('.remove-supplier').addEventListener('click', () => wrap.remove());
    
    // Aplicar máscara de moeda nos novos inputs
    applyMoneyMask(wrap.querySelector('input.money'));
    
    return wrap;
  }

  function applyMoneyMask(inp){
    inp.addEventListener('input', function(){
      let v = inp.value.replace(/\D/g,'');
      if(v===''){ inp.value='0,00'; return; }
      while(v.length<3) v='0'+v;
      let cents=v.slice(-2);
      let reais=v.slice(0,-2);
      reais=reais.replace(/^0+(\d)/,'$1');
      reais=reais.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
      inp.value=reais+','+cents;
    });
  }

  addBtn.addEventListener('click', () => {
    rows.appendChild(rowTpl());
  });

  // Carregar fornecedores existentes
  if(currentSuppliers.length > 0){
    currentSuppliers.forEach(cs => {
      rows.appendChild(rowTpl(cs.supplier_id, cs.cost, cs.notes));
    });
  } else {
    // Se não houver fornecedores, adicionar uma linha vazia
    rows.appendChild(rowTpl());
  }

  // Aplicar máscara de moeda nos inputs existentes
  document.querySelectorAll('input.money').forEach(applyMoneyMask);
})();
</script>
