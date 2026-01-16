<?php
require_login();
require_role(['admin','vendas','financeiro']);

$cats = $pdo->query("SELECT id,name,kind FROM categories WHERE active=1 ORDER BY sort_order, name")->fetchAll();
$suppliers = $pdo->query("SELECT id,name FROM suppliers WHERE active=1 ORDER BY name")->fetchAll();

$action2 = $_POST['action'] ?? '';
if($_SERVER['REQUEST_METHOD']==='POST' && $action2==='add_category'){
  $new_cat = trim($_POST['new_category'] ?? '');
  $new_kind = $_POST['new_kind'] ?? 'produto';
  if($new_cat){
    $st = $pdo->prepare("INSERT IGNORE INTO categories (name,kind,active,created_at) VALUES (?,?,1,NOW())");
    $st->execute([$new_cat, $new_kind]);
    flash_set('success','Categoria cadastrada.');
  } else {
    flash_set('danger','Informe o nome da categoria.');
  }
  redirect($base.'/app.php?page=items');
}

// Excluir categoria
if($_SERVER['REQUEST_METHOD']==='POST' && $action2==='delete_category'){
  $cat_id = (int)($_POST['category_id'] ?? 0);
  if($cat_id){
    // Verifica se tem produtos nesta categoria
    $check = $pdo->prepare("SELECT COUNT(*) as c FROM items WHERE category_id = ? AND active = 1");
    $check->execute([$cat_id]);
    $count = $check->fetch()['c'];
    
    if($count > 0){
      flash_set('danger',"Não é possível excluir. Existem $count produto(s) nesta categoria.");
    } else {
      $pdo->prepare("UPDATE categories SET active = 0 WHERE id = ?")->execute([$cat_id]);
      flash_set('success','Categoria excluída.');
    }
  }
  redirect($base.'/app.php?page=items');
}

// Excluir produto
if(isset($_GET['delete_item'])){
  $item_id = (int)$_GET['delete_item'];
  if($item_id && can_admin()){
    // Verifica se está sendo usado em alguma O.S
    $check = $pdo->prepare("SELECT COUNT(*) as c FROM os_lines WHERE item_id = ?");
    $check->execute([$item_id]);
    $count = $check->fetch()['c'];
    
    if($count > 0){
      flash_set('danger',"Não é possível excluir. Este produto está sendo usado em $count O.S.");
    } else {
      $pdo->prepare("UPDATE items SET active = 0 WHERE id = ?")->execute([$item_id]);
      flash_set('success','Produto excluído.');
    }
  }
  redirect($base.'/app.php?page=items');
}


function get_or_create_category($pdo, $name){
  $name = trim($name);
  if(!$name) return 0;
  $st = $pdo->prepare("SELECT id FROM categories WHERE name=? LIMIT 1");
  $st->execute([$name]);
  $id = (int)($st->fetchColumn() ?: 0);
  if($id) return $id;
  $st = $pdo->prepare("INSERT INTO categories (name,kind,active,created_at) VALUES (?,?,1,NOW())");
  $st->execute([$name,'produto']);
  return (int)$pdo->lastInsertId();
}

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

if($_SERVER['REQUEST_METHOD']==='POST'){
  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? 'produto';
  $format = trim($_POST['format'] ?? '');
  $vias = trim($_POST['vias'] ?? '');
  $colors = trim($_POST['colors'] ?? '');
  $category_id = (int)($_POST['category_id'] ?? 0);
  $category_new = trim($_POST['category_new'] ?? '');
  $price = (float)str_replace(',','.',($_POST['price'] ?? '0'));

  [$image_path, $image_error] = upload_item_image($_FILES['image'] ?? null, true);
  if ($image_error) {
    flash_set('danger', $image_error);
    redirect($base.'/app.php?page=items');
  }

  if(!$category_id && $category_new){
    $category_id = get_or_create_category($pdo, $category_new);
  }

  if(!$name || !$category_id){
    flash_set('danger','Nome e categoria são obrigatórios.');
    redirect($base.'/app.php?page=items');
  }

  // Verificar se é produto por m²
  $is_sqm_product = isset($_POST['is_sqm_product']) ? 1 : 0;
  $price_per_sqm = $is_sqm_product ? (float)str_replace(',','.',($_POST['price_per_sqm'] ?? '0')) : 0;
  $min_sqm = $is_sqm_product ? (float)str_replace(',','.',($_POST['min_sqm'] ?? '0')) : 0;
  
  $st=$pdo->prepare("INSERT INTO items (name,format,vias,colors,type,category_id,image_path,cost,price,is_sqm_product,price_per_sqm,min_sqm,active,created_at) VALUES (?,?,?,?,?,?,?,0,?,?,?,?,1,NOW())");
  $st->execute([$name,$format,$vias,$colors,$type,$category_id,$image_path,$price,$is_sqm_product,$price_per_sqm,$min_sqm]);
  $item_id = (int)$pdo->lastInsertId();

  // custo por fornecedor (opcional)
  $sup_ids = $_POST['supplier_id'] ?? [];
  $sup_costs = $_POST['supplier_cost'] ?? [];
  $sup_notes = $_POST['supplier_notes'] ?? [];

  for($i=0; $i<count($sup_ids); $i++){
    $sid = (int)$sup_ids[$i];
    $cost = (float)str_replace(',','.',($sup_costs[$i] ?? '0'));
    $note = trim($sup_notes[$i] ?? '');
    if(!$sid) continue;
    $st2 = $pdo->prepare("INSERT INTO item_supplier_costs (item_id,supplier_id,cost,notes,active,created_at)
                           VALUES (?,?,?,?,1,NOW())
                           ON DUPLICATE KEY UPDATE cost=VALUES(cost), notes=VALUES(notes), active=1");
    $st2->execute([$item_id,$sid,$cost,$note]);
  }

  flash_set('success','Produto/Serviço cadastrado.');
  redirect($base.'/app.php?page=items');
}

// Filtros
$search = trim($_GET['search'] ?? '');
$filter_category = (int)($_GET['category'] ?? 0);
$filter_type = $_GET['type'] ?? '';
$sort_by = $_GET['sort'] ?? 'name';
$sort_dir = $_GET['dir'] ?? 'asc';

// Validar campos de ordenação
$allowed_sorts = ['name' => 'i.name', 'type' => 'i.type', 'cat' => 'c.name', 'format' => 'i.format', 'vias' => 'i.vias', 'colors' => 'i.colors', 'price' => 'i.price'];
$sort_field = $allowed_sorts[$sort_by] ?? 'i.name';
$sort_direction = ($sort_dir === 'desc') ? 'DESC' : 'ASC';

$sql = "SELECT i.id,i.name,i.type,i.format,i.vias,i.colors,i.price,c.name cat
        FROM items i JOIN categories c ON c.id=i.category_id
        WHERE i.active=1";

$params = [];

if($search){
  $sql .= " AND (i.name LIKE ? OR i.format LIKE ? OR i.colors LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if($filter_category > 0){
  $sql .= " AND i.category_id = ?";
  $params[] = $filter_category;
}

if($filter_type){
  $sql .= " AND i.type = ?";
  $params[] = $filter_type;
}

$sql .= " ORDER BY $sort_field $sort_direction LIMIT 500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-3">
      <h5 style="font-weight:900">Produtos & Serviços</h5>
      
      <!-- Filtros -->
      <form class="row g-2 mb-3" method="get">
        <input type="hidden" name="page" value="items">
        <div class="col-md-4">
          <input class="form-control" name="search" placeholder="Buscar por nome, formato, cores..." value="<?= h($search) ?>">
        </div>
        <div class="col-md-3">
          <select class="form-select" name="category">
            <option value="">Todas as categorias</option>
            <?php foreach($cats as $cat): ?>
              <option value="<?= h($cat['id']) ?>" <?= $filter_category==$cat['id']?'selected':'' ?>><?= h($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" name="type">
            <option value="">Todos os tipos</option>
            <option value="produto" <?= $filter_type==='produto'?'selected':'' ?>>Produto</option>
            <option value="servico" <?= $filter_type==='servico'?'selected':'' ?>>Serviço</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        </div>
      </form>
      
      <?php if($search || $filter_category || $filter_type): ?>
        <div class="mb-2">
          <small class="text-muted">Mostrando <?= count($rows) ?> resultado(s)</small>
          <a href="?page=items" class="btn btn-sm btn-outline-secondary ms-2">Limpar filtros</a>
        </div>
      <?php endif; ?>
      
      <style>
        .sortable-header a {
          color: #212529;
          text-decoration: none;
          display: inline-block;
          user-select: none;
        }
        .sortable-header a:hover {
          color: #0d6efd;
          text-decoration: underline;
        }
      </style>
      
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <?php
              // Função para gerar link de ordenação
              function sort_link($field, $label, $current_sort, $current_dir, $get_params) {
                $new_dir = ($current_sort === $field && $current_dir === 'asc') ? 'desc' : 'asc';
                $icon = '';
                if($current_sort === $field) {
                  $icon = $current_dir === 'asc' ? ' ▲' : ' ▼';
                }
                $params = array_merge($get_params, ['sort' => $field, 'dir' => $new_dir]);
                $query = http_build_query($params);
                return "<a href='?{$query}'>{$label}{$icon}</a>";
              }
              
              $get_params = ['page' => 'items'];
              if($search) $get_params['search'] = $search;
              if($filter_category) $get_params['category'] = $filter_category;
              if($filter_type) $get_params['type'] = $filter_type;
              ?>
              <th class="sortable-header"><?= sort_link('name', 'Nome', $sort_by, $sort_dir, $get_params) ?></th>
              <th class="sortable-header"><?= sort_link('type', 'Tipo', $sort_by, $sort_dir, $get_params) ?></th>
              <th class="sortable-header"><?= sort_link('cat', 'Categoria', $sort_by, $sort_dir, $get_params) ?></th>
              <th class="sortable-header"><?= sort_link('format', 'Formato', $sort_by, $sort_dir, $get_params) ?></th>
              <th class="sortable-header"><?= sort_link('vias', 'Vias', $sort_by, $sort_dir, $get_params) ?></th>
              <th class="sortable-header"><?= sort_link('colors', 'Cores', $sort_by, $sort_dir, $get_params) ?></th>
              <th class="sortable-header"><?= sort_link('price', 'Preço', $sort_by, $sort_dir, $get_params) ?></th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= h($r['name']) ?></td>
              <td><?= h($r['type']) ?></td>
              <td><?= h($r['cat']) ?></td>
              <td><?= h($r['format']) ?></td>
              <td><?= h($r['vias']) ?></td>
              <td><?= h($r['colors']) ?></td>
              <td><b>R$ <?= number_format((float)$r['price'],2,',','.') ?></b></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/app.php?page=items_edit&id=<?= (int)$r['id'] ?>">Editar</a>
                <?php if(can_admin()): ?>
                  <a class="btn btn-sm btn-outline-danger" href="<?= $base ?>/app.php?page=items&delete_item=<?= (int)$r['id'] ?>" onclick="return confirm('Deseja excluir este produto?')">Excluir</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="text-muted small">
        Custo por fornecedor: ao cadastrar, você pode informar vários fornecedores com preços diferentes.
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card p-3">
      <h6 style="font-weight:900">Cadastrar produto/serviço</h6>
      <form method="post" class="row g-2" id="itemForm" enctype="multipart/form-data">
        <div class="col-12">
          <label class="form-label">Nome *</label>
          <input class="form-control" name="name" required>
        </div>

        <div class="col-6">
          <label class="form-label">Tipo *</label>
          <select class="form-select" name="type" required>
            <option value="produto">Produto</option>
            <option value="servico">Serviço</option>
          </select>
        </div>

        <div class="col-6">
          <div class="d-flex justify-content-between align-items-center gap-1">
            <label class="form-label mb-0">Categoria *</label>
            <div>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalManageCat" title="Gerenciar categorias">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                </svg>
              </button>
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCat">+</button>
            </div>
          </div>
          <select class="form-select" name="category_id" id="category_id" required>
            <option value="">Selecione...</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= h($c['id']) ?>"><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12" id="categoryNewWrap" style="display:none">
          <label class="form-label">Nova categoria</label>
          <input class="form-control" name="category_new" placeholder="Digite e cadastre">
        </div>

        <div class="col-12"><hr class="my-2"></div>

        <div class="col-12">
          <label class="form-label">Imagem do produto (site) *</label>
          <input class="form-control" type="file" name="image" accept="image/*" required>
          <div class="text-muted small">Obrigatória para exibição no site.</div>
        </div>

        <div class="col-12"><hr class="my-2"></div>

        <div class="col-12">
          <label class="form-label">Formato *</label>
          <input class="form-control" name="format" placeholder="Ex: A4, 10x15, etc" required>
        </div>

        <div class="col-6">
          <label class="form-label">Vias *</label>
          <input class="form-control" name="vias" placeholder="Ex: 1 via, 2 vias" required>
        </div>

        <div class="col-6">
          <label class="form-label">Cores *</label>
          <input class="form-control" name="colors" placeholder="Ex: 4x0, 4x4" required>
        </div>

        <div class="col-12">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_sqm_product" id="is_sqm_product" onchange="toggleSqmFields()">
            <label class="form-check-label" for="is_sqm_product">
              <strong>Produto vendido por m²</strong> (Adesivos, Banner, etc)
            </label>
          </div>
        </div>

        <div id="normalPriceFields">
          <div class="col-12">
            <label class="form-label">Preço de venda *</label>
            <input class="form-control" name="price" id="price" value="0" required>
          </div>
        </div>

        <div id="sqmPriceFields" style="display: none;">
          <div class="col-12">
            <label class="form-label">Preço por m² *</label>
            <input class="form-control" name="price_per_sqm" id="price_per_sqm" value="0" placeholder="Ex: 45.00">
            <small class="text-muted">O preço será calculado: largura x altura x preço/m²</small>
          </div>
          <div class="col-12">
            <label class="form-label">Quantidade mínima (m²)</label>
            <input class="form-control" name="min_sqm" id="min_sqm" value="0" placeholder="Ex: 1.0">
            <small class="text-muted">Quantidade mínima para venda (opcional)</small>
          </div>
        </div>

        <div class="col-12"><hr class="my-2"></div>

        <div class="d-flex align-items-center justify-content-between">
          <span style="font-weight:800">Fornecedores & custos</span>
          <button class="btn btn-sm btn-outline-primary" type="button" id="addSupplierRow">+ adicionar</button>
        </div>

        <div id="supplierRows" class="mt-2"></div>

        <div class="col-12 mt-2">
          <button class="btn btn-primary" type="submit">Salvar</button>
        </div>

        <div class="text-muted small">
          * Após subir este ZIP, rode o SQL <b>database/updates/upgrade_v3.sql</b> no phpMyAdmin (uma única vez).
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal: cadastrar categoria -->
<div class="modal fade" id="modalCat" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="add_category">
        <div class="modal-header">
          <h5 class="modal-title">Cadastrar categoria</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Nome da categoria *</label>
            <input class="form-control" name="new_category" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="new_kind">
              <option value="produto">Produto</option>
              <option value="servico">Serviço</option>
              <option value="ambos" selected>Ambos</option>
            </select>
            <div class="text-muted small mt-1">Dica: use “Ambos” se a categoria servir para produto e serviço.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar categoria</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: gerenciar categorias -->
<div class="modal fade" id="modalManageCat" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Gerenciar Categorias</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <?php if(empty($cats)): ?>
          <p class="text-muted">Nenhuma categoria cadastrada.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>Tipo</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($cats as $cat): ?>
                  <tr>
                    <td><strong><?= h($cat['name']) ?></strong></td>
                    <td>
                      <span class="badge bg-secondary"><?= h($cat['kind']) ?></span>
                    </td>
                    <td class="text-end">
                      <?php if(can_admin()): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Deseja realmente excluir esta categoria? Só será possível se não houver produtos associados.')">
                          <input type="hidden" name="action" value="delete_category">
                          <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted small">Sem permissão</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>


<script>
(function(){
  const suppliers = <?= json_encode($suppliers, JSON_UNESCAPED_UNICODE) ?>;
  const rows = document.getElementById('supplierRows');
  const addBtn = document.getElementById('addSupplierRow');
  const catSel = document.getElementById('category_id');
  const catNew = document.getElementById('categoryNewWrap');

  function rowTpl(){
    const wrap = document.createElement('div');
    wrap.className = 'border rounded-3 p-2 mb-2';
    wrap.innerHTML = `
      <div class="row g-2">
        <div class="col-12">
          <label class="form-label">Fornecedor</label>
          <select class="form-select" name="supplier_id[]">
            <option value="">Selecione...</option>
            ${suppliers.map(s=>`<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('')}
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Preço do fornecedor</label>
          <input class="form-control" name="supplier_cost[]" value="0">
        </div>
        <div class="col-6">
          <label class="form-label">Obs</label>
          <input class="form-control" name="supplier_notes[]" placeholder="opcional">
        </div>
        <div class="col-12 text-end">
          <button type="button" class="btn btn-sm btn-outline-danger">Remover</button>
        </div>
      </div>
    `;
    wrap.querySelector('button').addEventListener('click', ()=>wrap.remove());
    return wrap;
  }

  function escapeHtml(str){
    return (str||'').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'}[c]));
  }

  addBtn.addEventListener('click', ()=>{
    rows.appendChild(rowTpl());
  });

  // começa com 1 linha
  rows.appendChild(rowTpl());

  // categoria nova: se o user deixar em branco, ele digita
  catSel.addEventListener('change', ()=>{
    catNew.style.display = (catSel.value === '') ? 'block' : 'none';
  });
})();

function toggleSqmFields() {
  const isSqm = document.getElementById('is_sqm_product').checked;
  const normalFields = document.getElementById('normalPriceFields');
  const sqmFields = document.getElementById('sqmPriceFields');
  
  if(isSqm) {
    normalFields.style.display = 'none';
    sqmFields.style.display = 'block';
    document.getElementById('price').required = false;
    document.getElementById('price_per_sqm').required = true;
  } else {
    normalFields.style.display = 'block';
    sqmFields.style.display = 'none';
    document.getElementById('price').required = true;
    document.getElementById('price_per_sqm').required = false;
  }
}
</script>
