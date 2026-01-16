<?php
/**
 * Catálogo de Produtos - Mídia Certa Gráfica
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';

$base = $config['base_path'] ?? '';
$branding = require_once __DIR__ . '/../config/branding.php';

// Filtros
$search = trim($_GET['search'] ?? '');
$filter_category = (int)($_GET['category'] ?? 0);
$sort_by = $_GET['sort'] ?? 'name';

// Buscar categorias para filtro
$categorias = $pdo->query("
    SELECT id, name, 
    (SELECT COUNT(*) FROM items WHERE category_id = categories.id AND active = 1) as total_produtos
    FROM categories 
    WHERE active = 1 
    ORDER BY sort_order, name
")->fetchAll();

// Montar query de produtos
$sql = "SELECT i.*, c.name as categoria_nome
        FROM items i 
        JOIN categories c ON c.id = i.category_id
        WHERE i.active = 1";

$params = [];

if($search) {
    $sql .= " AND (i.name LIKE ? OR i.format LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if($filter_category > 0) {
    $sql .= " AND i.category_id = ?";
    $params[] = $filter_category;
}

// Ordenação
$order = match($sort_by) {
    'price_asc' => 'i.price ASC',
    'price_desc' => 'i.price DESC',
    'name' => 'i.name ASC',
    default => 'c.sort_order ASC, i.name ASC'
};

$sql .= " ORDER BY $order LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - <?= h($branding['nome_empresa']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
    <style>
        :root {
            --cor-primaria: <?= $branding['cores']['primaria'] ?>;
            --cor-secundaria: <?= $branding['cores']['secundaria'] ?>;
        }
        
        .page-header {
            background: <?= $branding['gradiente_principal'] ?>;
            color: white;
            padding: 60px 0 40px;
        }
        
        .filtros-sidebar {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            position: sticky;
            top: 80px;
        }
        
        .categoria-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .categoria-item:hover {
            background: white;
            transform: translateX(5px);
        }
        
        .categoria-item.active {
            background: var(--cor-primaria);
            color: white;
            font-weight: bold;
        }
        
        .produto-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            overflow: hidden;
            height: 100%;
        }
        
        .produto-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .produto-icone {
            background: <?= $branding['gradiente_secundario'] ?>;
            color: white;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }

        .produto-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }
        
        .produto-preco {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--cor-primaria);
        }
        
        .badge-categoria {
            background: var(--cor-secundaria);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-right: 45px;
        }
        
        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: var(--cor-primaria);
            color: white;
            border-radius: 5px;
            padding: 8px 15px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php include __DIR__ . '/partials/navbar.php'; ?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="display-4 fw-bold">Nossos Produtos</h1>
        <p class="lead mb-0">Qualidade profissional com preços competitivos</p>
    </div>
</section>

<!-- Catálogo -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Sidebar Filtros -->
            <div class="col-lg-3 mb-4">
                <div class="filtros-sidebar">
                    <h5 class="fw-bold mb-4">Filtrar por</h5>
                    
                    <!-- Busca -->
                    <div class="mb-4">
                        <form method="get" action="produtos.php">
                            <label class="form-label fw-bold">Buscar</label>
                            <div class="search-box">
                                <input type="text" name="search" class="form-control" placeholder="Digite aqui..." value="<?= h($search) ?>">
                                <button type="submit">🔍</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Categorias -->
                    <div class="mb-4">
                        <label class="form-label fw-bold mb-3">Categorias</label>
                        
                        <a href="<?= h($base) ?>/site/produtos.php" class="categoria-item <?= $filter_category == 0 ? 'active' : '' ?> text-decoration-none text-dark">
                            <span>Todas</span>
                            <span class="badge bg-secondary"><?= count($produtos) ?></span>
                        </a>
                        
                        <?php foreach($categorias as $cat): ?>
                            <?php if($cat['total_produtos'] > 0): ?>
                                <a href="<?= h($base) ?>/site/produtos.php?category=<?= (int)$cat['id'] ?>" class="categoria-item <?= $filter_category == $cat['id'] ? 'active' : '' ?> text-decoration-none text-dark">
                                    <span><?= h($cat['name']) ?></span>
                                    <span class="badge bg-secondary"><?= (int)$cat['total_produtos'] ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Ordenação -->
                    <div>
                        <label class="form-label fw-bold mb-3">Ordenar por</label>
                        <select class="form-select" onchange="window.location.href='produtos.php?category=<?= $filter_category ?>&sort='+this.value<?= $search ? '&search='.urlencode($search) : '' ?>">
                            <option value="default" <?= $sort_by == 'default' ? 'selected' : '' ?>>Padrão</option>
                            <option value="name" <?= $sort_by == 'name' ? 'selected' : '' ?>>Nome (A-Z)</option>
                            <option value="price_asc" <?= $sort_by == 'price_asc' ? 'selected' : '' ?>>Menor Preço</option>
                            <option value="price_desc" <?= $sort_by == 'price_desc' ? 'selected' : '' ?>>Maior Preço</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Grid de Produtos -->
            <div class="col-lg-9">
                <?php if(empty($produtos)): ?>
                    <div class="alert alert-info">
                        <h4>Nenhum produto encontrado</h4>
                        <p class="mb-0">Tente ajustar seus filtros ou <a href="<?= h($base) ?>/site/produtos.php">ver todos os produtos</a>.</p>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <p class="text-muted">Mostrando <?= count($produtos) ?> produto(s)</p>
                    </div>
                    
                    <div class="row g-4">
                        <?php foreach($produtos as $produto): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card produto-card">
                                    <?php if (!empty($produto['image_path'])): ?>
                                        <img src="<?= h($base . '/' . $produto['image_path']) ?>" alt="<?= h($produto['name']) ?>" class="produto-img">
                                    <?php else: ?>
                                        <div class="produto-icone">
                                            <?php
                                            $icone = match(true) {
                                                str_contains(strtolower($produto['categoria_nome']), 'cartão') => '🎴',
                                                str_contains(strtolower($produto['categoria_nome']), 'panfleto') => '📄',
                                                str_contains(strtolower($produto['categoria_nome']), 'adesivo') => '🏷️',
                                                str_contains(strtolower($produto['categoria_nome']), 'banner') => '🎯',
                                                default => '🖨️'
                                            };
                                            echo $icone;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <span class="badge-categoria mb-2"><?= h($produto['categoria_nome']) ?></span>
                                        <h5 class="card-title"><?= h($produto['name']) ?></h5>
                                        
                                        <?php if($produto['format']): ?>
                                            <p class="text-muted small mb-2">
                                                📏 <?= h($produto['format']) ?>
                                                <?php if($produto['colors']): ?>
                                                    | 🎨 <?= h($produto['colors']) ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <?php if($produto['is_sqm_product']): ?>
                                                <div>
                                                    <span class="produto-preco">R$ <?= number_format($produto['price_per_sqm'], 2, ',', '.') ?></span>
                                                    <small class="text-muted d-block">/m²</small>
                                                </div>
                                            <?php else: ?>
                                                <span class="produto-preco">R$ <?= number_format($produto['price'], 2, ',', '.') ?></span>
                                            <?php endif; ?>
                                            <a href="<?= h($base) ?>/site/produto.php?id=<?= (int)$produto['id'] ?>" class="btn btn-primary">Ver Mais</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
