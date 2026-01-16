<?php
/**
 * Home do Site - VERSÃO LIMPA E FUNCIONAL
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';

$base = $config['base_path'] ?? '';
$branding = require_once __DIR__ . '/../config/branding.php';

// Configurações do site
$site_config = [];
$configs = $pdo->query("SELECT config_key, config_value FROM site_config")->fetchAll();
foreach($configs as $cfg) {
    $site_config[$cfg['config_key']] = $cfg['config_value'];
}

function get_config($key, $default = '') {
    global $site_config;
    return $site_config[$key] ?? $default;
}

function set_config_value($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO site_config (config_key, config_value, config_type, description, category, updated_at)
        VALUES (?, ?, 'text', ?, 'hidden', NOW())
        ON DUPLICATE KEY UPDATE
            config_value = VALUES(config_value),
            updated_at = NOW()
    ");
    $stmt->execute([$key, $value, $key]);
}

function fetch_google_reviews($api_key, $place_id, $min_rating, $limit) {
    if (!$api_key || !$place_id) {
        return [];
    }

    $url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id='
        . urlencode($place_id)
        . '&fields=name,rating,reviews,user_ratings_total&language=pt-BR&key='
        . urlencode($api_key);

    $response = null;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $response = @file_get_contents($url);
    }

    if (!$response) {
        return [];
    }

    $data = json_decode($response, true);
    if (!isset($data['result']['reviews'])) {
        return [];
    }

    $reviews = [];
    foreach ($data['result']['reviews'] as $review) {
        $rating = (int)($review['rating'] ?? 0);
        if ($rating <= 1 || $rating < $min_rating) {
            continue;
        }
        $reviews[] = [
            'author' => $review['author_name'] ?? '',
            'rating' => $rating,
            'text' => $review['text'] ?? '',
            'time' => $review['time'] ?? 0,
            'relative' => $review['relative_time_description'] ?? '',
        ];
    }

    usort($reviews, fn($a, $b) => $b['time'] <=> $a['time']);
    return array_slice($reviews, 0, max(1, $limit));
}

function render_stars($rating) {
    $rating = max(0, min(5, (int)$rating));
    $stars = '';
    for ($i = 0; $i < 5; $i++) {
        $stars .= $i < $rating ? '★' : '☆';
    }
    return $stars;
}

// Buscar slides do carousel
$carousel_slides = [];
try {
    $carousel_slides = $pdo->query("SELECT * FROM carousel_slides WHERE active = 1 ORDER BY order_num, id")->fetchAll();
} catch (Exception $e) {
    // Se a tabela não existir ainda, usar slides padrão
    $carousel_slides = [
        [
            'title' => 'Impressão Profissional de Alta Qualidade',
            'subtitle' => 'Transforme suas ideias em realidade com nossos serviços gráficos',
            'button_text' => 'Ver Produtos',
            'button_link' => '/site/produtos.php',
            'image_path' => '',
            'background_color' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'text_color' => '#ffffff'
        ],
        [
            'title' => 'Entrega Rápida e Confiável',
            'subtitle' => 'Prazo padrão de 4 dias úteis. Entraremos em contato para confirmar.',
            'button_text' => 'Fale Conosco',
            'button_link' => '/site/contato.php',
            'image_path' => '',
            'background_color' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'text_color' => '#ffffff'
        ],
        [
            'title' => 'Qualidade Premium, Preço Justo',
            'subtitle' => 'Equipamentos modernos e materiais de primeira linha',
            'button_text' => 'Portal do Cliente',
            'button_link' => '/client_portal.php',
            'image_path' => '',
            'background_color' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'text_color' => '#ffffff'
        ]
    ];
}

// Grupos de produtos em destaque COM seus produtos
$featured_groups = [];
$groups_query = $pdo->query("SELECT * FROM site_featured_products WHERE active = 1 AND id IN (1, 2) ORDER BY id")->fetchAll();

foreach($groups_query as $group) {
    $produtos = $pdo->query("
        SELECT i.*, c.name as categoria_nome
        FROM site_featured_products_items fpi
        JOIN items i ON i.id = fpi.item_id
        JOIN categories c ON c.id = i.category_id
        WHERE fpi.featured_id = {$group['id']} AND i.active = 1
        ORDER BY fpi.order_num, i.name
        LIMIT 6
    ")->fetchAll();
    
    $group['produtos'] = $produtos;
    $featured_groups[] = $group;
}

$total_clientes = $pdo->query("SELECT COUNT(*) FROM clients WHERE active = 1")->fetchColumn();

$seo_title = get_config('seo_title', $branding['nome_empresa'].' - '.$branding['slogan']);
$seo_description = get_config('seo_description', $branding['slogan']);
$seo_keywords = get_config('seo_keywords', '');
$seo_og_title = get_config('seo_og_title', $seo_title);
$seo_og_description = get_config('seo_og_description', $seo_description);
$seo_og_image = get_config('seo_og_image', '');
$google_ads_tag = get_config('google_ads_tag', '');
$meta_pixel_tag = get_config('meta_pixel_tag', '');
$whatsapp_number_raw = get_config('whatsapp_number', '');
$whatsapp_message = get_config('whatsapp_message', 'Olá! Quero um orçamento rápido para materiais gráficos.');
$whatsapp_number = preg_replace('/\D+/', '', $whatsapp_number_raw);
$whatsapp_link = $whatsapp_number ? 'https://wa.me/'.$whatsapp_number.'?text='.urlencode($whatsapp_message) : '';
$canonical_url = rtrim($base, '/') . '/site/';
$company_legal_name = get_config('company_legal_name', $branding['nome_empresa']);
$company_cnpj = get_config('footer_cnpj', '');
$company_address = get_config('footer_endereco', '');

$hero_eyebrow = get_config('hero_eyebrow', '');
$hero_eyebrow = trim($hero_eyebrow) === 'Gráfica para pequenos negócios' ? '' : $hero_eyebrow;
$hero_title = get_config('hero_title', 'Impressão urgente? Produção rápida até 24h!');
$hero_subtitle = get_config('hero_subtitle', 'Soluções gráficas para qualquer negócio com qualidade premium e prazos rápidos.');
$hero_cta_primary = trim(get_config('hero_cta_primary_text', '')) ?: 'Pedir orçamento no WhatsApp';
$hero_cta_secondary = trim(get_config('hero_cta_secondary_text', '')) ?: 'Ver produtos';
$hero_bullets_defaults = [
    '✅ Atendimento rápido e direto com você',
    '✅ Fazemos sua arte para vender mais',
    '✅ Frete grátis para BH (consulte condições)',
    '✅ Produção rápida com prazos combinados',
    '✅ Parcele em até 3x sem juros',
];
$hero_bullets = [];
$hero_bullets[] = trim(get_config('hero_bullet_1', '')) ?: $hero_bullets_defaults[0];
$hero_bullets[] = trim(get_config('hero_bullet_2', '')) ?: $hero_bullets_defaults[1];
$hero_bullets[] = trim(get_config('hero_bullet_3', '')) ?: $hero_bullets_defaults[2];
$hero_bullets[] = trim(get_config('hero_bullet_4', '')) ?: $hero_bullets_defaults[3];
$hero_bullets[] = trim(get_config('hero_bullet_5', '')) ?: $hero_bullets_defaults[4];
$hero_bullets = array_filter($hero_bullets);

$hero_promises_defaults = [
    'Impressão urgente? Produção rápida até 24h.',
    'Parcele em até 3x sem juros.',
    'Atendimento 100% humanizado.',
    'Melhor preço do Brasil.',
];
$hero_promises = [];
$hero_promises[] = trim(get_config('hero_promise_1', '')) ?: $hero_promises_defaults[0];
$hero_promises[] = trim(get_config('hero_promise_2', '')) ?: $hero_promises_defaults[1];
$hero_promises[] = trim(get_config('hero_promise_3', '')) ?: $hero_promises_defaults[2];
$hero_promises[] = trim(get_config('hero_promise_4', '')) ?: $hero_promises_defaults[3];
$hero_promises = array_filter($hero_promises);

$google_maps_api_key = get_config('google_maps_api_key', '');
$google_place_id = get_config('google_place_id', '');
$google_reviews_min_rating = (int)get_config('google_reviews_min_rating', 2);
$google_reviews_limit = (int)get_config('google_reviews_limit', 6);
$articles_button_text = get_config('articles_button_text', 'Ver artigos');
$articles_url = rtrim($base, '/') . '/site/artigos.php';

$google_reviews = [];
$cache_json = get_config('google_reviews_cache', '');
$cache_at = get_config('google_reviews_cache_at', '');
$cache_time = $cache_at ? strtotime($cache_at) : 0;
$cache_ttl = 12 * 60 * 60;
if ($cache_json && $cache_time && (time() - $cache_time) < $cache_ttl) {
    $google_reviews = json_decode($cache_json, true) ?: [];
} elseif ($google_maps_api_key && $google_place_id) {
    $google_reviews = fetch_google_reviews($google_maps_api_key, $google_place_id, $google_reviews_min_rating, $google_reviews_limit);
    if (!empty($google_reviews)) {
        set_config_value('google_reviews_cache', json_encode($google_reviews));
        set_config_value('google_reviews_cache_at', date('Y-m-d H:i:s'));
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($seo_title) ?></title>
    <meta name="description" content="<?= h($seo_description) ?>">
    <?php if ($seo_keywords): ?>
        <meta name="keywords" content="<?= h($seo_keywords) ?>">
    <?php endif; ?>
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= h($canonical_url) ?>">
    <meta property="og:title" content="<?= h($seo_og_title) ?>">
    <meta property="og:description" content="<?= h($seo_og_description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($canonical_url) ?>">
    <?php if ($seo_og_image): ?>
        <meta property="og:image" content="<?= h($seo_og_image) ?>">
    <?php endif; ?>
    <?php if (!empty($google_ads_tag)): ?>
        <?= $google_ads_tag ?>
    <?php endif; ?>
    <?php if (!empty($meta_pixel_tag)): ?>
        <?= $meta_pixel_tag ?>
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&display=swap');

        :root {
            --cor-primaria: <?= $branding['cores']['primaria'] ?>;
            --cor-secundaria: <?= $branding['cores']['secundaria'] ?>;
            --dark: #0f172a;
        }
        
        body {
            font-family: 'Sora', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f5f7fb;
            color: var(--dark);
        }

        main, .navbar, footer, .whatsapp-float {
            position: relative;
            z-index: 1;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(10px);
        }

        .navbar .nav-link {
            font-weight: 600;
            color: var(--dark);
        }

        .navbar .btn-primary {
            border-radius: 999px;
            padding: 0.5rem 1.3rem;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.15);
        }

        .banner-wrap {
            padding: 0;
            margin: 0;
        }

        .banner-container {
            max-width: 1350px;
            margin: 0 auto;
            padding: 0 12px;
        }

        .banner-card {
            background: #ffffff;
            border-radius: 0;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            margin-top: 0;
        }

        .hero-content {
            padding: 18px 0 44px;
            background: <?= $branding['gradiente_principal'] ?>;
            color: #ffffff;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.08);
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .hero-title {
            font-size: 3.1rem;
            font-weight: 800;
            margin: 18px 0 12px;
            letter-spacing: -0.02em;
        }

        .hero-sub {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 24px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .btn-primary {
            background: var(--cor-primaria);
            border-color: var(--cor-primaria);
            border-radius: 999px;
            font-weight: 700;
            padding: 0.7rem 1.6rem;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.18);
        }

        .btn-outline {
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.7);
            color: #ffffff;
            padding: 0.65rem 1.4rem;
            font-weight: 700;
            background: transparent;
        }

        .hero-bullets {
            display: grid;
            gap: 12px;
        }

        .hero-bullet {
            background: rgba(255, 255, 255, 0.95);
            color: #0f172a;
            border-radius: 14px;
            padding: 12px 16px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
            font-weight: 700;
        }

        .hero-carousel {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        .hero-carousel .carousel-inner {
            border-radius: 20px;
        }

        .hero-carousel .carousel-item {
            position: relative;
            height: auto;
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
        }

        .hero-carousel .carousel-item.has-image {
            height: auto;
        }

        .hero-carousel .carousel-img {
            display: block;
            width: 100%;
            height: auto;
        }

        .hero-carousel .carousel-item.has-image .carousel-caption {
            display: none;
        }

        .hero-carousel .carousel-item.no-image .carousel-caption {
            position: static;
            padding: 36px;
            text-align: left;
        }

        .hero-carousel .carousel-caption h2 {
            font-size: 2.4rem;
            font-weight: 800;
        }

        .hero-carousel .carousel-caption p {
            font-size: 1.1rem;
        }

        .hero-carousel .carousel-indicators button {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .hero-carousel .carousel-indicators {
            display: none;
        }

        .hero-carousel .carousel-link {
            display: block;
            width: 100%;
            height: 100%;
            color: inherit;
            text-decoration: none;
        }

        .proof-strip {
            padding: 20px 0 70px;
        }

        .proof-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 18px 20px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 14px 26px rgba(15, 23, 42, 0.08);
            height: 100%;
        }

        .proof-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
        }

        .proof-label {
            color: #64748b;
            font-weight: 600;
        }

        .reviews-section {
            padding: 70px 0;
            background: #ffffff;
        }

        .review-card {
            background: #f9fafb;
            border-radius: 18px;
            padding: 22px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
            height: 100%;
        }

        .review-stars {
            color: #f59e0b;
            font-size: 1rem;
            letter-spacing: 0.08em;
        }

        .review-name {
            font-weight: 700;
        }

        .review-text {
            color: #475569;
        }

        .articles-cta {
            padding: 70px 0;
        }

        .section-title {
            font-weight: 800;
            color: var(--dark);
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            color: #64748b;
            max-width: 680px;
            margin: 0 auto;
        }

        .produtos-destaque {
            padding: 80px 0;
            background: #f9fafb;
        }

        .produto-card {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
            transition: all 0.3s;
            overflow: hidden;
            height: 100%;
        }

        .produto-card .card-body {
            padding: 22px 24px;
        }

        .produto-card .card-title {
            font-weight: 800;
        }

        .produto-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 18px 46px rgba(15, 23, 42, 0.18);
        }

        .produto-icone {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.92), rgba(37, 99, 235, 0.7));
            color: #ffffff;
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

        .produto-preco { font-size: 1.8rem; font-weight: 900; color: var(--cor-primaria); }

        .diferenciais {
            padding: 80px 0;
            background: #ffffff;
        }

        .diferencial-item {
            text-align: center;
            padding: 36px 28px;
            border-radius: 18px;
            background: #f9fafb;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
            height: 100%;
        }

        .diferencial-icone { font-size: 3rem; margin-bottom: 20px; color: var(--cor-primaria); }

        .whatsapp-float {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1000;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #25d366;
            color: #ffffff;
            padding: 12px 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(37, 211, 102, 0.35);
        }

        .whatsapp-float:hover {
            color: #ffffff;
            filter: brightness(0.95);
        }

        .reveal {
            opacity: 0;
            transform: translateY(12px);
            animation: reveal 0.6s ease forwards;
        }

        .delay-1 { animation-delay: 0.15s; }
        .delay-2 { animation-delay: 0.3s; }

        @keyframes reveal {
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            .hero-title {
                font-size: 2.4rem;
            }
            .hero-content {
                padding: 24px 0 48px;
            }
        }

        @media (max-width: 768px) {
            .hero-carousel .carousel-item {
                height: auto;
            }
        }

        .navbar {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<main>

<!-- Navbar -->
<?php include __DIR__ . '/partials/navbar.php'; ?>

<section class="banner-wrap">
    <div class="banner-container">
        <div class="banner-card reveal" style="margin-top:0;">
            <?php if (!empty($carousel_slides)): ?>
            <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-interval="5000">
                <?php if (count($carousel_slides) > 1): ?>
                <div class="carousel-indicators">
                    <?php foreach ($carousel_slides as $index => $slide): ?>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $index ?>" 
                                class="<?= $index === 0 ? 'active' : '' ?>" 
                                aria-current="<?= $index === 0 ? 'true' : 'false' ?>" 
                                aria-label="Slide <?= $index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="carousel-inner">
                    <?php foreach ($carousel_slides as $index => $slide): ?>
                        <?php
                        $style = '';
                        $has_image = !empty($slide['image_path']);
                        if (!$has_image) {
                            $style = "background: " . h($slide['background_color']) . ";";
                        }
                        ?>
                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?> <?= $has_image ? 'has-image' : 'no-image' ?>" style="<?= $style ?>">
                            <?php if (!empty($slide['button_link'])): ?>
                                <a href="<?= h($slide['button_link']) ?>" class="carousel-link">
                            <?php endif; ?>
                                    <?php if ($has_image): ?>
                                        <img src="<?= h($base . '/' . $slide['image_path']) ?>" class="carousel-img" alt="<?= h($slide['title']) ?>">
                                    <?php endif; ?>
                                    <div class="carousel-caption" style="color: <?= h($slide['text_color']) ?>;">
                                        <h2><?= h($slide['title']) ?></h2>
                                        <?php if (!empty($slide['subtitle'])): ?>
                                            <p><?= h($slide['subtitle']) ?></p>
                                        <?php endif; ?>
                                    </div>
                            <?php if (!empty($slide['button_link'])): ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($carousel_slides) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Anterior</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Próximo</span>
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <div class="p-4">
                    <h3 class="mb-2">Seu banner aqui</h3>
                    <p class="text-muted mb-0">Adicione imagens no painel de marketing para exibir no site.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<section class="hero-content">
    <div class="container">
        <div class="row align-items-start g-4">
            <div class="col-lg-7 reveal">
                <?php if (!empty($hero_eyebrow)): ?>
                    <span class="hero-eyebrow"><?= h($hero_eyebrow) ?></span>
                <?php endif; ?>
                <h1 class="hero-title"><?= h($hero_title) ?></h1>
                <p class="hero-sub"><?= h($hero_subtitle) ?></p>
                <div class="hero-actions">
                    <?php if ($whatsapp_link): ?>
                        <a href="<?= h($whatsapp_link) ?>" class="btn btn-primary btn-lg"><?= h($hero_cta_primary) ?></a>
                    <?php endif; ?>
                    <a href="produtos.php" class="btn btn-outline btn-lg"><?= h($hero_cta_secondary) ?></a>
                </div>
            </div>
            <div class="col-lg-5 reveal delay-1">
                <div class="hero-bullets">
                    <?php foreach ($hero_bullets as $bullet): ?>
                        <div class="hero-bullet"><?= h($bullet) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="proof-strip">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <div class="proof-card reveal">
                    <div class="proof-title"><?= h(get_config('stats_clientes', $total_clientes.'+')) ?></div>
                    <div class="proof-label">Clientes satisfeitos</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="proof-card reveal delay-1">
                    <div class="proof-title"><?= h(get_config('stats_prazo', '48h')) ?></div>
                    <div class="proof-label">Entrega rápida</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="proof-card reveal delay-2">
                    <div class="proof-title"><?= h(get_config('stats_experiencia', '15')) ?></div>
                    <div class="proof-label">Anos de experiência</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="proof-card reveal delay-2">
                    <div class="proof-title"><?= h($company_legal_name) ?></div>
                    <div class="proof-label">
                        <?= $company_cnpj ? 'CNPJ '.h($company_cnpj) : 'Empresa registrada' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($google_reviews)): ?>
<section class="reviews-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold section-title">Avaliações no Google</h2>
            <p class="section-subtitle">Veja o que nossos clientes dizem sobre a experiência conosco.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($google_reviews as $review): ?>
                <?php
                    $text = trim($review['text'] ?? '');
                    if (strlen($text) > 220) {
                        $text = substr($text, 0, 220) . '...';
                    }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="review-card">
                        <div class="review-stars"><?= h(render_stars($review['rating'])) ?></div>
                        <div class="review-name mt-2"><?= h($review['author']) ?></div>
                        <div class="text-muted small"><?= h($review['relative'] ?? '') ?></div>
                        <p class="review-text mt-3 mb-0"><?= h($text ?: 'Cliente satisfeito com nosso atendimento e qualidade.') ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <button type="button" class="btn btn-outline btn-lg" data-bs-toggle="modal" data-bs-target="#reviewsModal">
                Ver mais avaliações
            </button>
        </div>
    </div>
</section>

<div class="modal fade" id="reviewsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Avaliações do Google</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <?php foreach ($google_reviews as $review): ?>
                        <?php
                            $text = trim($review['text'] ?? '');
                            if (strlen($text) > 320) {
                                $text = substr($text, 0, 320) . '...';
                            }
                        ?>
                        <div class="col-12">
                            <div class="review-card">
                                <div class="review-stars"><?= h(render_stars($review['rating'])) ?></div>
                                <div class="review-name mt-2"><?= h($review['author']) ?></div>
                                <div class="text-muted small"><?= h($review['relative'] ?? '') ?></div>
                                <p class="review-text mt-3 mb-0"><?= h($text ?: 'Cliente satisfeito com nosso atendimento e qualidade.') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($articles_url): ?>
<section class="articles-cta">
    <div class="container">
        <div class="cta-panel">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <h3>Conteúdo útil para vender mais</h3>
                    <p class="text-muted mb-0">Dicas rápidas de marketing e impressão para o seu negócio crescer.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?= h($articles_url) ?>" class="btn btn-primary btn-lg"><?= h($articles_button_text) ?></a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Produtos Destaque -->
<section class="produtos-destaque">
    <div class="container">
        <?php foreach($featured_groups as $group): ?>
            <?php if(!empty($group['produtos'])): ?>
                <div class="mb-5 <?= $group !== $featured_groups[0] ? 'mt-5' : '' ?>">
                    <div class="text-center mb-4">
                        <h2 class="display-5 fw-bold section-title"><?= h($group['category_name']) ?></h2>
                        <?php if($group['description']): ?>
                            <p class="lead text-muted section-subtitle"><?= h($group['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($group['image_path']): ?>
                        <div class="text-center mb-4">
                            <img src="<?= h($base.'/'.$group['image_path']) ?>" alt="<?= h($group['category_name']) ?>" style="max-height: 200px; border-radius: 15px;">
                        </div>
                    <?php endif; ?>
                    
                    <div class="row g-4">
                        <?php foreach($group['produtos'] as $produto): ?>
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
                                        <h5 class="card-title"><?= h($produto['name']) ?></h5>
                                        <p class="text-muted mb-2"><?= h($produto['categoria_nome']) ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php if($produto['is_sqm_product']): ?>
                                                <div>
                                                    <span class="produto-preco">R$ <?= number_format($produto['price_per_sqm'], 2, ',', '.') ?></span>
                                                    <small class="text-muted d-block">/m²</small>
                                                </div>
                                            <?php else: ?>
                                                <span class="produto-preco">R$ <?= number_format($produto['price'], 2, ',', '.') ?></span>
                                            <?php endif; ?>
                                            <a href="<?= h($base) ?>/site/produto.php?id=<?= (int)$produto['id'] ?>" class="btn btn-primary">Ver Detalhes</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <div class="text-center mt-5">
            <a href="<?= h($base) ?>/produtos.php" class="btn btn-lg btn-primary">Ver Todos os Produtos</a>
        </div>
    </div>
</section>

<!-- Diferenciais -->
<section class="diferenciais">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold section-title">Por que Escolher a <?= h($branding['nome_empresa']) ?>?</h2>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="diferencial-item">
                    <div class="diferencial-icone">⚡</div>
                    <h4><?= h(get_config('diferencial_1_titulo', 'Entrega Rápida')) ?></h4>
                    <p><?= h(get_config('diferencial_1_texto', 'Produção e entrega em até 48 horas.')) ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="diferencial-item">
                    <div class="diferencial-icone">💎</div>
                    <h4><?= h(get_config('diferencial_2_titulo', 'Qualidade Premium')) ?></h4>
                    <p><?= h(get_config('diferencial_2_texto', 'Equipamentos modernos e materiais de primeira linha.')) ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="diferencial-item">
                    <div class="diferencial-icone">💰</div>
                    <h4><?= h(get_config('diferencial_3_titulo', 'Melhor Preço')) ?></h4>
                    <p><?= h(get_config('diferencial_3_texto', 'Preços competitivos sem comprometer a qualidade.')) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<?php include __DIR__ . '/partials/footer.php'; ?>

<?php if ($whatsapp_link): ?>
<a class="whatsapp-float" href="<?= h($whatsapp_link) ?>" aria-label="Falar no WhatsApp">
    💬 Orçamento no WhatsApp
</a>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</main>
</body>
</html>
