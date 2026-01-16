<?php
/**
 * Marketing - Gerenciar Artigos do Site
 */
require_login();
if(!can_admin()) { flash_set('danger', 'Sem permissão.'); redirect($base.'/app.php'); }

function slugify($text) {
  $text = trim($text);
  if ($text === '') return '';
  if (function_exists('iconv')) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
  }
  $text = strtolower($text);
  $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
  $text = preg_replace('/\s+/', '-', $text);
  $text = preg_replace('/-+/', '-', $text);
  return trim($text, '-');
}

function unique_slug(PDO $pdo, $slug, $ignore_id = 0) {
  $base = $slug;
  $i = 1;
  while (true) {
    $st = $pdo->prepare("SELECT id FROM site_articles WHERE slug=? AND id<>? LIMIT 1");
    $st->execute([$slug, $ignore_id]);
    if (!$st->fetch()) return $slug;
    $slug = $base . '-' . $i;
    $i++;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      $pdo->prepare("DELETE FROM site_articles WHERE id=?")->execute([$id]);
      flash_set('success', 'Artigo removido.');
    }
    redirect($base.'/app.php?page=marketing_artigos');
  }

  if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = $_POST['status'] ?? 'published';
    $published_at = $_POST['published_at'] ?? '';

    if ($title === '' || $content === '') {
      flash_set('danger', 'Título e conteúdo são obrigatórios.');
      redirect($base.'/app.php?page=marketing_artigos'.($id ? '&edit='.$id : ''));
    }

    $slug = $slug ? slugify($slug) : slugify($title);
    $slug = unique_slug($pdo, $slug, $id);

    if ($id) {
      $st = $pdo->prepare("UPDATE site_articles SET title=?, slug=?, excerpt=?, content=?, status=?, published_at=? WHERE id=?");
      $st->execute([$title, $slug, $excerpt, $content, $status, $published_at ?: null, $id]);
      flash_set('success', 'Artigo atualizado.');
    } else {
      $st = $pdo->prepare("INSERT INTO site_articles (title, slug, excerpt, content, status, published_at) VALUES (?,?,?,?,?,?)");
      $st->execute([$title, $slug, $excerpt, $content, $status, $published_at ?: null]);
      flash_set('success', 'Artigo criado.');
    }
    redirect($base.'/app.php?page=marketing_artigos');
  }
}

$editing = null;
$edit_id = (int)($_GET['edit'] ?? 0);
if ($edit_id) {
  $st = $pdo->prepare("SELECT * FROM site_articles WHERE id=?");
  $st->execute([$edit_id]);
  $editing = $st->fetch(PDO::FETCH_ASSOC);
}

$articles = $pdo->query("SELECT * FROM site_articles ORDER BY COALESCE(published_at, created_at) DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 style="font-weight:900;">📝 Artigos do Site</h2>
      <p class="text-muted mb-0">Crie, edite e organize conteúdos com URL amigável</p>
    </div>
    <a href="<?= h($base) ?>/site/artigos.php" target="_blank" class="btn btn-outline-primary">👁️ Ver página</a>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card p-3">
        <h5 class="mb-3" style="font-weight:800;">Lista de artigos</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Título</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Data</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($articles as $a): ?>
                <tr>
                  <td><?= h($a['title']) ?></td>
                  <td><?= h($a['slug']) ?></td>
                  <td><?= h($a['status']) ?></td>
                  <td><?= h($a['published_at'] ?: '') ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="<?= h($base) ?>/app.php?page=marketing_artigos&edit=<?= (int)$a['id'] ?>">Editar</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Excluir este artigo?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-3">
        <h5 class="mb-3" style="font-weight:800;"><?= $editing ? 'Editar artigo' : 'Novo artigo' ?></h5>
        <form method="post">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

          <div class="mb-3">
            <label class="form-label">Título *</label>
            <input class="form-control" name="title" value="<?= h($editing['title'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Slug (URL amigável)</label>
            <input class="form-control" name="slug" value="<?= h($editing['slug'] ?? '') ?>" placeholder="deixe em branco para gerar automaticamente">
          </div>

          <div class="mb-3">
            <label class="form-label">Resumo</label>
            <textarea class="form-control" name="excerpt" rows="3"><?= h($editing['excerpt'] ?? '') ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Conteúdo *</label>
            <textarea class="form-control" name="content" rows="10" required><?= h($editing['content'] ?? '') ?></textarea>
          </div>

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publicado</option>
                <option value="draft" <?= ($editing['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Rascunho</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Data</label>
              <input type="date" class="form-control" name="published_at" value="<?= h($editing['published_at'] ?? '') ?>">
            </div>
          </div>

          <div class="mt-3">
            <button class="btn btn-success">Salvar</button>
            <?php if ($editing): ?>
              <a class="btn btn-outline-secondary" href="<?= h($base) ?>/app.php?page=marketing_artigos">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
