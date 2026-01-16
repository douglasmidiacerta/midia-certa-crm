<?php
/**
 * Marketing - Gerenciamento do Hero / Banner
 * Upload de imagens e configuração de slides
 */
require_login();
if(!can_admin()) { flash_set('success', 'Sem permissão.'); redirect($base.'/app.php'); }

// POST: Upload de nova imagem do Hero
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if($_POST['action'] === 'upload_hero') {
        if(isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/hero';
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['hero_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if(!in_array($ext, $allowed)) {
                flash_set('success', 'Formato de imagem inválido. Use: JPG, PNG, GIF ou WEBP', 'danger');
            } else {
                $new_name = 'hero_' . time() . '_' . uniqid() . '.' . $ext;
                $file_path = $upload_dir . '/' . $new_name;
                
                if(move_uploaded_file($file['tmp_name'], $file_path)) {
                    $title = trim($_POST['title'] ?? '');
                    $subtitle = trim($_POST['subtitle'] ?? '');
                    $button_text = trim($_POST['button_text'] ?? '');
                    $button_link = trim($_POST['button_link'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO site_hero_images (image_path, title, subtitle, button_text, button_link, active)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute(['uploads/hero/' . $new_name, $title, $subtitle, $button_text, $button_link]);
                    
                    flash_set('success', 'Imagem adicionada com sucesso!');
                } else {
                    flash_set('success', 'Erro ao fazer upload da imagem.', 'danger');
                }
            }
        } else {
            flash_set('success', 'Selecione uma imagem.', 'danger');
        }
        redirect($base.'/app.php?page=marketing_hero');
    }
    
    if($_POST['action'] === 'update_hero') {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $button_text = trim($_POST['button_text'] ?? '');
        $button_link = trim($_POST['button_link'] ?? '');
        $order_num = (int)($_POST['order_num'] ?? 0);
        
        $stmt = $pdo->prepare("
            UPDATE site_hero_images 
            SET title=?, subtitle=?, button_text=?, button_link=?, order_num=?
            WHERE id=?
        ");
        $stmt->execute([$title, $subtitle, $button_text, $button_link, $order_num, $id]);
        
        flash_set('success', 'Hero atualizado!');
        redirect($base.'/app.php?page=marketing_hero');
    }
    
    if($_POST['action'] === 'toggle_active') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE site_hero_images SET active = NOT active WHERE id = ?")->execute([$id]);
        flash_set('success', 'Status atualizado!');
        redirect($base.'/app.php?page=marketing_hero');
    }
    
    if($_POST['action'] === 'delete_hero') {
        $id = (int)$_POST['id'];
        $hero = $pdo->prepare("SELECT image_path FROM site_hero_images WHERE id=?")->execute([$id]);
        $hero = $pdo->query("SELECT image_path FROM site_hero_images WHERE id=$id")->fetch();
        
        if($hero && file_exists(__DIR__ . '/../' . $hero['image_path'])) {
            unlink(__DIR__ . '/../' . $hero['image_path']);
        }
        
        $pdo->prepare("DELETE FROM site_hero_images WHERE id=?")->execute([$id]);
        flash_set('success', 'Hero removido!');
        redirect($base.'/app.php?page=marketing_hero');
    }
}

// Buscar imagens do Hero
$heroes = $pdo->query("SELECT * FROM site_hero_images ORDER BY order_num, id DESC")->fetchAll();
?>

<style>
.hero-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.hero-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.hero-preview {
    height: 200px;
    background-size: cover;
    background-position: center;
    position: relative;
}

.hero-preview .badge {
    position: absolute;
    top: 10px;
    right: 10px;
}

.upload-zone {
    border: 3px dashed #d1d5db;
    border-radius: 15px;
    padding: 40px;
    text-align: center;
    background: #f9fafb;
    transition: all 0.3s;
}

.upload-zone:hover {
    border-color: #f59e0b;
    background: #fffbeb;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-weight: 900;">🖼️ Hero / Banner Principal</h2>
            <p class="text-muted mb-0">Gerencie as imagens que aparecem no topo da home</p>
        </div>
        <a href="<?= h($base) ?>/" target="_blank" class="btn btn-outline-primary">
            👁️ Ver Site
        </a>
    </div>

    <!-- Upload Nova Imagem -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3">➕ Adicionar Nova Imagem</h5>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_hero">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Imagem *</label>
                        <input type="file" class="form-control" name="hero_image" accept="image/*" required>
                        <small class="text-muted">Recomendado: 1920x800px, JPG ou PNG</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Título (opcional)</label>
                        <input type="text" class="form-control" name="title" placeholder="Ex: Impressão Profissional">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold">Subtítulo (opcional)</label>
                        <textarea class="form-control" name="subtitle" rows="2" placeholder="Texto que aparece abaixo do título"></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Texto do Botão (opcional)</label>
                        <input type="text" class="form-control" name="button_text" placeholder="Ex: Ver Produtos">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Link do Botão (opcional)</label>
                        <input type="text" class="form-control" name="button_link" placeholder="Ex: /produtos.php">
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            ⬆️ Upload e Adicionar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Imagens -->
    <h5 class="fw-bold mb-3">📸 Imagens do Hero (<?= count($heroes) ?>)</h5>
    
    <?php if(empty($heroes)): ?>
        <div class="alert alert-info">
            <strong>ℹ️ Nenhuma imagem cadastrada.</strong> Adicione a primeira imagem acima.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach($heroes as $hero): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="hero-card">
                        <div class="hero-preview" style="background-image: url('<?= h($base.'/'.$hero['image_path']) ?>')">
                            <span class="badge <?= $hero['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $hero['active'] ? '✓ Ativo' : '✗ Inativo' ?>
                            </span>
                        </div>
                        
                        <div class="p-3">
                            <form method="post" id="form-<?= $hero['id'] ?>">
                                <input type="hidden" name="action" value="update_hero">
                                <input type="hidden" name="id" value="<?= (int)$hero['id'] ?>">
                                
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Título</label>
                                    <input type="text" class="form-control form-control-sm" name="title" value="<?= h($hero['title']) ?>">
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Subtítulo</label>
                                    <textarea class="form-control form-control-sm" name="subtitle" rows="2"><?= h($hero['subtitle']) ?></textarea>
                                </div>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Botão</label>
                                        <input type="text" class="form-control form-control-sm" name="button_text" value="<?= h($hero['button_text']) ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Link</label>
                                        <input type="text" class="form-control form-control-sm" name="button_link" value="<?= h($hero['button_link']) ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Ordem</label>
                                    <input type="number" class="form-control form-control-sm" name="order_num" value="<?= (int)$hero['order_num'] ?>" min="0">
                                </div>
                                
                                <div class="d-flex gap-1">
                                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                                        💾 Salvar
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm <?= $hero['active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>" onclick="toggleActive(<?= $hero['id'] ?>)">
                                        <?= $hero['active'] ? '👁️' : '🚫' ?>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Remover esta imagem?')) deleteHero(<?= $hero['id'] ?>)">
                                        🗑️
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<form id="actionForm" method="post" style="display:none;">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="id" id="actionId">
</form>

<script>
function toggleActive(id) {
    document.getElementById('actionType').value = 'toggle_active';
    document.getElementById('actionId').value = id;
    document.getElementById('actionForm').submit();
}

function deleteHero(id) {
    document.getElementById('actionType').value = 'delete_hero';
    document.getElementById('actionId').value = id;
    document.getElementById('actionForm').submit();
}
</script>
