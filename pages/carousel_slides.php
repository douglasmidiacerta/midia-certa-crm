<?php
/**
 * Gerenciamento de Slides do Carousel
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$base = $config['base_path'] ?? '';
$page_title = 'Gerenciar Slides do Carousel';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'] ?? '';
        $subtitle = $_POST['subtitle'] ?? '';
        $button_text = $_POST['button_text'] ?? '';
        $button_link = $_POST['button_link'] ?? '';
        $background_color = $_POST['background_color'] ?? '';
        $text_color = $_POST['text_color'] ?? '#ffffff';
        $order_num = (int)($_POST['order_num'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Upload de imagem
        $image_path = $_POST['current_image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/carousel/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'slide_' . time() . '_' . uniqid() . '.' . $extension;
            $target = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $image_path = 'uploads/carousel/' . $filename;
                
                // Deletar imagem antiga se existir
                if (!empty($_POST['current_image']) && file_exists(__DIR__ . '/../' . $_POST['current_image'])) {
                    unlink(__DIR__ . '/../' . $_POST['current_image']);
                }
            }
        }
        
        if ($id) {
            // Atualizar
            $stmt = $pdo->prepare("
                UPDATE carousel_slides 
                SET title = ?, subtitle = ?, button_text = ?, button_link = ?, 
                    image_path = ?, background_color = ?, text_color = ?, 
                    order_num = ?, active = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $subtitle, $button_text, $button_link, $image_path, 
                          $background_color, $text_color, $order_num, $active, $id]);
            $success = "Slide atualizado com sucesso!";
        } else {
            // Inserir
            $stmt = $pdo->prepare("
                INSERT INTO carousel_slides 
                (title, subtitle, button_text, button_link, image_path, background_color, 
                 text_color, order_num, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $subtitle, $button_text, $button_link, $image_path, 
                          $background_color, $text_color, $order_num, $active]);
            $success = "Slide criado com sucesso!";
        }
        
        header("Location: " . $base . "/app.php?page=carousel_slides&success=" . urlencode($success));
        exit;
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Pegar imagem para deletar
            $slide = $pdo->query("SELECT image_path FROM carousel_slides WHERE id = $id")->fetch();
            if ($slide && !empty($slide['image_path'])) {
                $image_file = __DIR__ . '/../' . $slide['image_path'];
                if (file_exists($image_file)) {
                    unlink($image_file);
                }
            }
            
            $pdo->exec("DELETE FROM carousel_slides WHERE id = $id");
            $success = "Slide deletado com sucesso!";
        }
        header("Location: " . $base . "/app.php?page=carousel_slides&success=" . urlencode($success));
        exit;
    }
}

// Buscar todos os slides
$slides = $pdo->query("SELECT * FROM carousel_slides ORDER BY order_num, id")->fetchAll();

// Buscar slide para edição
$editing = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $editing = $pdo->query("SELECT * FROM carousel_slides WHERE id = $edit_id")->fetch();
}

$success_msg = $_GET['success'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $editing ? 'Editar Slide' : 'Gerenciar Slides do Carousel' ?></h1>
    <?php if ($editing): ?>
        <a href="<?= h($base) ?>/app.php?page=carousel_slides" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    <?php endif; ?>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= h($success_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= $editing ? 'Editar Slide' : 'Novo Slide' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <?php if ($editing): ?>
                        <input type="hidden" name="id" value="<?= $editing['id'] ?>">
                        <input type="hidden" name="current_image" value="<?= h($editing['image_path']) ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" name="title" class="form-control" 
                               value="<?= h($editing['title'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subtítulo</label>
                        <textarea name="subtitle" class="form-control" rows="3"><?= h($editing['subtitle'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Imagem de Fundo</label>
                        <?php if ($editing && !empty($editing['image_path'])): ?>
                            <div class="mb-2">
                                <img src="<?= h($base . '/' . $editing['image_path']) ?>" 
                                     alt="Preview" class="img-thumbnail" style="max-height: 150px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Deixe em branco para usar cor de fundo. Recomendado: 1920x500px</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cor de Fundo (se não usar imagem)</label>
                        <input type="text" name="background_color" class="form-control" 
                               value="<?= h($editing['background_color'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)') ?>"
                               placeholder="linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
                        <small class="text-muted">Use gradiente CSS ou cor sólida</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cor do Texto</label>
                        <input type="color" name="text_color" class="form-control form-control-color" 
                               value="<?= h($editing['text_color'] ?? '#ffffff') ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Texto do Botão</label>
                            <input type="text" name="button_text" class="form-control" 
                                   value="<?= h($editing['button_text'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Link do Botão</label>
                            <input type="text" name="button_link" class="form-control" 
                                   value="<?= h($editing['button_link'] ?? '') ?>"
                                   placeholder="/site/produtos.php">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="order_num" class="form-control" 
                                   value="<?= h($editing['order_num'] ?? 0) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" name="active" class="form-check-input" 
                                       id="active" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="active">Ativo</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Slide
                        </button>
                        <?php if ($editing): ?>
                            <a href="<?= h($base) ?>/app.php?page=carousel_slides" class="btn btn-secondary">
                                Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Slides Cadastrados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($slides)): ?>
                    <p class="text-muted">Nenhum slide cadastrado ainda.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ordem</th>
                                    <th>Preview</th>
                                    <th>Título</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slides as $slide): ?>
                                    <tr>
                                        <td><?= h($slide['order_num']) ?></td>
                                        <td>
                                            <?php if (!empty($slide['image_path'])): ?>
                                                <img src="<?= h($base . '/' . $slide['image_path']) ?>" 
                                                     alt="Preview" style="height: 40px; border-radius: 4px;">
                                            <?php else: ?>
                                                <div style="width: 60px; height: 40px; background: <?= h($slide['background_color']) ?>; border-radius: 4px;"></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= h($slide['title']) ?></strong>
                                            <?php if ($slide['subtitle']): ?>
                                                <br><small class="text-muted"><?= h(substr($slide['subtitle'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $slide['active'] ? 'success' : 'secondary' ?>">
                                                <?= $slide['active'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="<?= h($base) ?>/app.php?page=carousel_slides&edit=<?= $slide['id'] ?>" 
                                               class="btn btn-sm btn-primary me-1">
                                                ✏️ Editar
                                            </a>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Deseja realmente deletar este slide?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $slide['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    🗑️ Deletar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Dicas</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Use imagens com <strong>1920x500px</strong> para melhor resultado</li>
                    <li>O campo "Ordem" define a sequência dos slides</li>
                    <li>Você pode usar imagens ou gradientes de fundo</li>
                    <li>Apenas slides <strong>ativos</strong> aparecem no site</li>
                    <li>Recomendado: 3 a 5 slides para melhor experiência</li>
                </ul>
            </div>
        </div>
    </div>
</div>
