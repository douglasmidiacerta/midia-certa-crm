<?php
/**
 * Marketing - Produtos em Destaque
 * Gerenciar grupos de produtos (Cartão de Visita, Panfletos, etc)
 */
require_login();
if(!can_admin()) { flash_set('success', 'Sem permissão.'); redirect($base.'/app.php'); }

// POST: Ações
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if($_POST['action'] === 'create_featured') {
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $order_num = (int)($_POST['order_num'] ?? 0);
        
        if($category_name) {
            // Upload de imagem
            $image_path = null;
            if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/featured';
                if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $file = $_FILES['image'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_name = 'featured_' . time() . '_' . uniqid() . '.' . $ext;
                
                if(move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $new_name)) {
                    $image_path = 'uploads/featured/' . $new_name;
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO site_featured_products (category_name, description, image_path, order_num, active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$category_name, $description, $image_path, $order_num]);
            
            flash_set('success', 'Grupo criado com sucesso!');
        }
        redirect($base.'/app.php?page=marketing_produtos');
    }
    
    if($_POST['action'] === 'update_featured') {
        $id = (int)$_POST['id'];
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $order_num = (int)($_POST['order_num'] ?? 0);
        
        // Upload de nova imagem (se houver)
        $image_path = $_POST['current_image'] ?? null;
        if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/featured';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_name = 'featured_' . time() . '_' . uniqid() . '.' . $ext;
            
            if(move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $new_name)) {
                // Remover imagem antiga
                if($image_path && file_exists(__DIR__ . '/../' . $image_path)) {
                    unlink(__DIR__ . '/../' . $image_path);
                }
                $image_path = 'uploads/featured/' . $new_name;
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE site_featured_products 
            SET category_name=?, description=?, image_path=?, order_num=?
            WHERE id=?
        ");
        $stmt->execute([$category_name, $description, $image_path, $order_num, $id]);
        
        flash_set('success', 'Grupo atualizado!');
        redirect($base.'/app.php?page=marketing_produtos');
    }
    
    if($_POST['action'] === 'toggle_featured') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE site_featured_products SET active = NOT active WHERE id=?")->execute([$id]);
        flash_set('success', 'Status atualizado!');
        redirect($base.'/app.php?page=marketing_produtos');
    }
    
    if($_POST['action'] === 'delete_featured') {
        $id = (int)$_POST['id'];
        $feat = $pdo->query("SELECT image_path FROM site_featured_products WHERE id=$id")->fetch();
        
        if($feat && $feat['image_path'] && file_exists(__DIR__ . '/../' . $feat['image_path'])) {
            unlink(__DIR__ . '/../' . $feat['image_path']);
        }
        
        $pdo->prepare("DELETE FROM site_featured_products WHERE id=?")->execute([$id]);
        flash_set('success', 'Grupo removido!');
        redirect($base.'/app.php?page=marketing_produtos');
    }
    
    if($_POST['action'] === 'add_product_to_featured') {
        $featured_id = (int)$_POST['featured_id'];
        $item_id = (int)$_POST['item_id'];
        
        try {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO site_featured_products_items (featured_id, item_id, order_num)
                VALUES (?, ?, 0)
            ");
            $stmt->execute([$featured_id, $item_id]);
            flash_set('success', 'Produto adicionado!');
        } catch(Exception $e) {
            flash_set('success', 'Erro: produto já está no grupo.', 'danger');
        }
        redirect($base.'/app.php?page=marketing_produtos&edit='.$featured_id);
    }
    
    if($_POST['action'] === 'remove_product_from_featured') {
        $id = (int)$_POST['id'];
        $featured_id = (int)$_POST['featured_id'];
        
        $pdo->prepare("DELETE FROM site_featured_products_items WHERE id=?")->execute([$id]);
        flash_set('success', 'Produto removido do grupo!');
        redirect($base.'/app.php?page=marketing_produtos&edit='.$featured_id);
    }
}

// Buscar grupos de destaque
$featured_groups = $pdo->query("
    SELECT f.*, 
    (SELECT COUNT(*) FROM site_featured_products_items WHERE featured_id = f.id) as total_produtos
    FROM site_featured_products f
    ORDER BY order_num, id
")->fetchAll();

// Se estiver editando um grupo
$editing = null;
$editing_products = [];
if(isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editing = $pdo->query("SELECT * FROM site_featured_products WHERE id=$edit_id")->fetch();
    
    if($editing) {
        $editing_products = $pdo->query("
            SELECT fpi.*, i.name as item_name, i.price, c.name as category_name
            FROM site_featured_products_items fpi
            JOIN items i ON i.id = fpi.item_id
            JOIN categories c ON c.id = i.category_id
            WHERE fpi.featured_id = $edit_id
            ORDER BY fpi.order_num, i.name
        ")->fetchAll();
    }
}

// Buscar todos os produtos para adicionar
$all_products = $pdo->query("
    SELECT i.*, c.name as category_name
    FROM items i
    JOIN categories c ON c.id = i.category_id
    WHERE i.active = 1
    ORDER BY c.sort_order, i.name
")->fetchAll();
?>

<style>
.featured-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.featured-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.featured-image {
    height: 150px;
    background-size: cover;
    background-position: center;
    background-color: #f3f4f6;
}

.product-mini-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 10px;
    background: #f9fafb;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-weight: 900;">⭐ Produtos em Destaque</h2>
            <p class="text-muted mb-0">Gerencie grupos de produtos destacados na home do site</p>
        </div>
        <div>
            <?php if($editing): ?>
                <a href="<?= h($base) ?>/app.php?page=marketing_produtos" class="btn btn-outline-secondary me-2">
                    ← Voltar
                </a>
            <?php endif; ?>
            <a href="<?= h($base) ?>/" target="_blank" class="btn btn-outline-primary">
                👁️ Ver Site
            </a>
        </div>
    </div>

    <?php if(!$editing): ?>
        <!-- Criar Novo Grupo -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">➕ Criar Novo Grupo de Destaque</h5>
                
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_featured">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Nome do Grupo *</label>
                            <input type="text" class="form-control" name="category_name" required placeholder="Ex: Cartão de Visita">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Imagem Representativa</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <small class="text-muted">Opcional - 500x500px</small>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Ordem</label>
                            <input type="number" class="form-control" name="order_num" value="0" min="0">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold">Descrição</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Breve descrição sobre este grupo de produtos"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                ✅ Criar Grupo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Grupos -->
        <h5 class="fw-bold mb-3">📦 Grupos Criados (<?= count($featured_groups) ?>)</h5>
        
        <?php if(empty($featured_groups)): ?>
            <div class="alert alert-info">
                <strong>ℹ️ Nenhum grupo criado ainda.</strong> Crie o primeiro grupo acima.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($featured_groups as $group): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="featured-card">
                            <?php if($group['image_path']): ?>
                                <div class="featured-image" style="background-image: url('<?= h($base.'/'.$group['image_path']) ?>')"></div>
                            <?php else: ?>
                                <div class="featured-image d-flex align-items-center justify-content-center">
                                    <span style="font-size: 3rem;">📦</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="fw-bold mb-0"><?= h($group['category_name']) ?></h5>
                                    <span class="badge <?= $group['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $group['active'] ? '✓' : '✗' ?>
                                    </span>
                                </div>
                                
                                <p class="text-muted small mb-3"><?= h($group['description']) ?></p>
                                
                                <div class="alert alert-light mb-3">
                                    <strong><?= (int)$group['total_produtos'] ?></strong> produto(s) neste grupo
                                </div>
                                
                                <div class="d-flex gap-1">
                                    <a href="?page=marketing_produtos&edit=<?= (int)$group['id'] ?>" class="btn btn-sm btn-primary flex-grow-1">
                                        ✏️ Editar & Produtos
                                    </a>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleFeatured(<?= $group['id'] ?>)">
                                        <?= $group['active'] ? '👁️' : '🚫' ?>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Remover este grupo?')) deleteFeatured(<?= $group['id'] ?>)">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Editar Grupo -->
        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">✏️ Editar Grupo</h5>
                        
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_featured">
                            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                            <input type="hidden" name="current_image" value="<?= h($editing['image_path']) ?>">
                            
                            <?php if($editing['image_path']): ?>
                                <div class="mb-3">
                                    <img src="<?= h($base.'/'.$editing['image_path']) ?>" class="img-fluid rounded" alt="Imagem atual">
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nome do Grupo *</label>
                                <input type="text" class="form-control" name="category_name" value="<?= h($editing['category_name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nova Imagem</label>
                                <input type="file" class="form-control" name="image" accept="image/*">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Ordem</label>
                                <input type="number" class="form-control" name="order_num" value="<?= (int)$editing['order_num'] ?>" min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Descrição</label>
                                <textarea class="form-control" name="description" rows="3"><?= h($editing['description']) ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                💾 Salvar Alterações
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">📦 Produtos neste Grupo (<?= count($editing_products) ?>)</h5>
                        
                        <!-- Adicionar Produto -->
                        <form method="post" class="mb-4">
                            <input type="hidden" name="action" value="add_product_to_featured">
                            <input type="hidden" name="featured_id" value="<?= (int)$editing['id'] ?>">
                            
                            <div class="input-group">
                                <select class="form-select" name="item_id" required>
                                    <option value="">Selecione um produto...</option>
                                    <?php foreach($all_products as $prod): ?>
                                        <option value="<?= (int)$prod['id'] ?>">
                                            <?= h($prod['name']) ?> - <?= h($prod['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-success">+ Adicionar</button>
                            </div>
                        </form>
                        
                        <!-- Lista de Produtos -->
                        <?php if(empty($editing_products)): ?>
                            <div class="alert alert-info">
                                Nenhum produto adicionado ainda. Use o campo acima para adicionar.
                            </div>
                        <?php else: ?>
                            <?php foreach($editing_products as $prod): ?>
                                <div class="product-mini-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= h($prod['item_name']) ?></strong><br>
                                            <small class="text-muted"><?= h($prod['category_name']) ?> - R$ <?= number_format($prod['price'], 2, ',', '.') ?></small>
                                        </div>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="remove_product_from_featured">
                                            <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
                                            <input type="hidden" name="featured_id" value="<?= (int)$editing['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover produto?')">
                                                🗑️ Remover
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<form id="actionForm" method="post" style="display:none;">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="id" id="actionId">
</form>

<script>
function toggleFeatured(id) {
    document.getElementById('actionType').value = 'toggle_featured';
    document.getElementById('actionId').value = id;
    document.getElementById('actionForm').submit();
}

function deleteFeatured(id) {
    document.getElementById('actionType').value = 'delete_featured';
    document.getElementById('actionId').value = id;
    document.getElementById('actionForm').submit();
}
</script>
