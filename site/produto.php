<?php
/**
 * Detalhes do Produto + Sistema de Pedido
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';
require_once __DIR__ . '/../config/client_auth.php';

$base = $config['base_path'] ?? '';
$branding = require_once __DIR__ . '/../config/branding.php';

// Buscar produto
$id = (int)($_GET['id'] ?? 0);

if($id <= 0) {
    header('Location: produtos.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT i.*, c.name as categoria_nome, c.id as categoria_id
    FROM items i 
    JOIN categories c ON c.id = i.category_id
    WHERE i.id = ? AND i.active = 1
");
$stmt->execute([$id]);
$produto = $stmt->fetch();

if(!$produto) {
    header('Location: produtos.php');
    exit;
}

// Produtos relacionados (mesma categoria)
$relacionados = $pdo->prepare("
    SELECT i.*, c.name as categoria_nome
    FROM items i
    JOIN categories c ON c.id = i.category_id
    WHERE i.category_id = ? AND i.id != ? AND i.active = 1
    ORDER BY RAND()
    LIMIT 3
");
$relacionados->execute([$produto['categoria_id'], $id]);
$produtos_relacionados = $relacionados->fetchAll();

// Cliente logado?
$cliente_logado = is_client_logged_in();
$cliente_id = $cliente_logado ? get_client_id() : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($produto['name']) ?> - <?= h($branding['nome_empresa']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
    <style>
        :root {
            --cor-primaria: <?= $branding['cores']['primaria'] ?>;
            --cor-secundaria: <?= $branding['cores']['secundaria'] ?>;
        }
        
        .produto-hero {
            background: <?= $branding['gradiente_secundario'] ?>;
            color: white;
            padding: 80px 0;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8rem;
        }
        
        .produto-info {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-top: -60px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
        }
        
        .preco-destaque {
            font-size: 3rem;
            font-weight: 900;
            color: var(--cor-primaria);
        }
        
        .btn-fazer-pedido {
            padding: 15px 50px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 50px;
            background: var(--cor-primaria);
            border: none;
            transition: all 0.3s;
        }
        
        .btn-fazer-pedido:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(141, 198, 63, 0.4);
        }
        
        .especificacao-item {
            padding: 15px;
            border-left: 4px solid var(--cor-primaria);
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .calculadora-m2 {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php include __DIR__ . '/partials/navbar.php'; ?>

<!-- Produto Hero -->
<section class="produto-hero">
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
</section>

<!-- Produto Info -->
<section class="pb-5">
    <div class="container">
        <div class="produto-info">
            <div class="row">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?= h($base) ?>/site/index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?= h($base) ?>/site/produtos.php">Produtos</a></li>
                            <li class="breadcrumb-item"><a href="<?= h($base) ?>/site/produtos.php?category=<?= (int)$produto['categoria_id'] ?>"><?= h($produto['categoria_nome']) ?></a></li>
                            <li class="breadcrumb-item active"><?= h($produto['name']) ?></li>
                        </ol>
                    </nav>
                    
                    <h1 class="display-5 fw-bold mb-3"><?= h($produto['name']) ?></h1>
                    <p class="lead text-muted mb-4"><?= h($produto['categoria_nome']) ?></p>
                    
                    <div class="mb-4">
                        <h3 class="h5 fw-bold mb-3">Especificações Técnicas</h3>
                        
                        <?php if($produto['format']): ?>
                            <div class="especificacao-item">
                                <strong>📏 Formato:</strong> <?= h($produto['format']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($produto['vias']): ?>
                            <div class="especificacao-item">
                                <strong>📋 Vias:</strong> <?= h($produto['vias']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($produto['colors']): ?>
                            <div class="especificacao-item">
                                <strong>🎨 Cores:</strong> <?= h($produto['colors']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="especificacao-item">
                            <strong>📦 Tipo:</strong> <?= ucfirst(h($produto['type'])) ?>
                        </div>
                    </div>
                    
                    <?php if($produto['is_sqm_product']): ?>
                        <!-- Calculadora para produtos por m² -->
                        <div class="calculadora-m2">
                            <h3 class="h5 fw-bold mb-3">📐 Calculadora de Preço</h3>
                            <p class="text-muted">Este produto é vendido por metro quadrado (m²)</p>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Largura (metros)</label>
                                    <input type="number" class="form-control" id="calc_width" step="0.01" min="0.1" value="1.00" onchange="calcularPreco()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Altura (metros)</label>
                                    <input type="number" class="form-control" id="calc_height" step="0.01" min="0.1" value="1.00" onchange="calcularPreco()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Área Total</label>
                                    <input type="text" class="form-control" id="calc_area" readonly>
                                </div>
                            </div>
                            
                            <div class="mt-3 p-3 bg-white rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted d-block">Preço por m²</small>
                                        <strong class="h5">R$ <?= number_format($produto['price_per_sqm'], 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Valor Total</small>
                                        <strong class="h3 text-primary" id="calc_total">R$ <?= number_format($produto['price_per_sqm'], 2, ',', '.') ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                const preco_m2 = <?= $produto['price_per_sqm'] ?>;
                                
                                function calcularPreco() {
                                    const width = parseFloat(document.getElementById('calc_width').value) || 0;
                                    const height = parseFloat(document.getElementById('calc_height').value) || 0;
                                    const area = width * height;
                                    const total = area * preco_m2;
                                    
                                    document.getElementById('calc_area').value = area.toFixed(2) + ' m²';
                                    document.getElementById('calc_total').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
                                }
                                
                                calcularPreco();
                            </script>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-4">
                    <div class="sticky-top" style="top: 100px;">
                        <div class="card border-0 shadow-lg">
                            <div class="card-body p-4">
                                <?php if($produto['is_sqm_product']): ?>
                                    <p class="text-muted mb-2">A partir de</p>
                                    <div class="preco-destaque">R$ <?= number_format($produto['price_per_sqm'], 2, ',', '.') ?></div>
                                    <p class="text-muted">/m²</p>
                                <?php else: ?>
                                    <div class="preco-destaque mb-3">R$ <?= number_format($produto['price'], 2, ',', '.') ?></div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <?php if($cliente_logado): ?>
                                        <button class="btn btn-primary btn-fazer-pedido" data-bs-toggle="modal" data-bs-target="#modalPedido">
                                            🛒 Fazer Pedido
                                        </button>
                                    <?php else: ?>
                                        <a href="../client_login.php?redirect=<?= urlencode('/site/produto.php?id='.$id) ?>" class="btn btn-primary btn-fazer-pedido">
                                            🔐 Entrar para Fazer Pedido
                                        </a>
                                        <p class="text-center text-muted small mt-2">
                                            Não tem conta? <a href="../client_register.php">Cadastre-se grátis</a>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <a href="https://wa.me/5511999999999?text=Olá! Gostaria de um orçamento para: <?= urlencode($produto['name']) ?>" class="btn btn-outline-success" target="_blank">
                                        💬 WhatsApp
                                    </a>
                                </div>
                                
                                <div class="mt-4 pt-4 border-top">
                                    <h6 class="fw-bold mb-3">✅ Vantagens</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">⚡ Entrega rápida</li>
                                        <li class="mb-2">💎 Qualidade premium</li>
                                        <li class="mb-2">📄 Aprovação de arte</li>
                                        <li class="mb-2">🔄 Acompanhamento online</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Produtos Relacionados -->
        <?php if(!empty($produtos_relacionados)): ?>
            <div class="mt-5">
                <h3 class="h4 fw-bold mb-4">Produtos Relacionados</h3>
                <div class="row g-4">
                    <?php foreach($produtos_relacionados as $rel): ?>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><?= h($rel['name']) ?></h5>
                                    <p class="text-primary fw-bold">R$ <?= number_format($rel['price'], 2, ',', '.') ?></p>
                                    <a href="<?= h($base) ?>/site/produto.php?id=<?= (int)$rel['id'] ?>" class="btn btn-sm btn-outline-primary">Ver Produto</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal de Pedido -->
<?php if($cliente_logado): ?>
    <div class="modal fade" id="modalPedido" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="processar_pedido.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="produto_id" value="<?= (int)$produto['id'] ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">🛒 Fazer Pedido</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Produto:</strong> <?= h($produto['name']) ?><br>
                            <strong>Preço:</strong> R$ <?= $produto['is_sqm_product'] ? number_format($produto['price_per_sqm'], 2, ',', '.') . '/m²' : number_format($produto['price'], 2, ',', '.') ?>
                        </div>
                        
                        <?php if($produto['is_sqm_product']): ?>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Largura (metros) *</label>
                                    <input type="number" class="form-control" name="width" step="0.01" min="0.1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Altura (metros) *</label>
                                    <input type="number" class="form-control" name="height" step="0.01" min="0.1" required>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Quantidade *</label>
                                <input type="number" class="form-control" name="quantidade" min="1" value="1" required>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Prazo Desejado</label>
                            <input type="date" class="form-control" name="prazo_desejado" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            <small class="text-muted">Opcional - entraremos em contato para confirmar</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Forma de Pagamento Preferencial</label>
                            <select class="form-select" name="pagamento_preferencial">
                                <option value="">A definir</option>
                                <option value="Pix">Pix</option>
                                <option value="Boleto">Boleto</option>
                                <option value="Cartão">Cartão</option>
                                <option value="Dinheiro">Dinheiro</option>
                            </select>
                            <small class="text-muted">Vamos confirmar com você antes de processar</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Arquivo (Arte/Referência)</label>
                            <input type="file" class="form-control" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.ai,.psd">
                            <small class="text-muted">Opcional - você pode enviar depois no portal</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="3" placeholder="Detalhes adicionais sobre seu pedido..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>ℹ️ Importante:</strong> Seu pedido será enviado para análise. Nossa equipe entrará em contato para confirmar detalhes de pagamento e prazo antes de iniciar a produção.
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-lg">Enviar Pedido</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Footer -->
<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
