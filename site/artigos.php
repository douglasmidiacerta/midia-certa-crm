<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';

$base = $config['base_path'] ?? '';
$branding = require_once __DIR__ . '/../config/branding.php';

$slug = trim($_GET['slug'] ?? '');
$article = null;
$articles = [];
$articles_error = '';

try {
    if ($slug) {
        $st = $pdo->prepare("SELECT * FROM site_articles WHERE slug=? AND status='published' LIMIT 1");
        $st->execute([$slug]);
        $article = $st->fetch(PDO::FETCH_ASSOC);
    }

    if (!$article) {
        $articles = $pdo->query("SELECT * FROM site_articles WHERE status='published' ORDER BY COALESCE(published_at, created_at) DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $articles_error = 'Tabela de artigos não encontrada. Rode o SQL de criação.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artigos - <?= h($branding['nome_empresa']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
    <style>
        body { background: #f5f7fb; }
        .article-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
            height: 100%;
        }
        .article-meta { color: #64748b; font-size: 0.9rem; }
        .article-title { font-weight: 800; }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<section class="py-5">
    <div class="container">
        <?php if ($articles_error): ?>
            <div class="alert alert-warning">
                <?= h($articles_error) ?>
            </div>
        <?php elseif ($article): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= h($base) ?>/site/index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= h($base) ?>/site/artigos.php">Artigos</a></li>
                    <li class="breadcrumb-item active"><?= h($article['title']) ?></li>
                </ol>
            </nav>
            <h1 class="display-5 fw-bold mb-3"><?= h($article['title']) ?></h1>
            <div class="article-meta mb-4"><?= h($article['published_at'] ?? '') ?></div>
            <div class="article-card">
                <?= nl2br(h($article['content'])) ?>
            </div>
        <?php else: ?>
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold">Artigos</h1>
                <p class="text-muted">Conteúdos úteis para ajudar você a vender mais com materiais gráficos.</p>
            </div>
            <div class="row g-4">
                <?php foreach ($articles as $a): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="article-card">
                            <div class="article-meta mb-2"><?= h($a['published_at'] ?? '') ?></div>
                            <h3 class="h5 article-title"><?= h($a['title']) ?></h3>
                            <p class="text-muted"><?= h($a['excerpt']) ?></p>
                            <a href="<?= h($base) ?>/site/artigos.php?slug=<?= h($a['slug']) ?>" class="btn btn-outline-primary btn-sm">Ler artigo</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
