<?php
/**
 * Marketing - Gerenciamento Geral do Site
 * Edição de textos, estatísticas e diferenciais
 */
require_login();
if(!can_admin()) { flash_set('danger', 'Sem permissão.'); redirect($base.'/app.php'); }

$user = user();
$user_id = $user['id'];

$config_definitions = [
    'hero_eyebrow' => [
        'category' => 'hero',
        'type' => 'text',
        'description' => 'Texto curto acima do título (opcional)'
    ],
    'hero_cta_primary_text' => [
        'category' => 'hero',
        'type' => 'text',
        'description' => 'Texto do botão principal (WhatsApp)'
    ],
    'hero_cta_secondary_text' => [
        'category' => 'hero',
        'type' => 'text',
        'description' => 'Texto do botão secundário'
    ],
    'hero_bullet_1' => [
        'category' => 'hero_bullets',
        'type' => 'text',
        'description' => 'Benefício 1 (lista)'
    ],
    'hero_bullet_2' => [
        'category' => 'hero_bullets',
        'type' => 'text',
        'description' => 'Benefício 2 (lista)'
    ],
    'hero_bullet_3' => [
        'category' => 'hero_bullets',
        'type' => 'text',
        'description' => 'Benefício 3 (lista)'
    ],
    'hero_bullet_4' => [
        'category' => 'hero_bullets',
        'type' => 'text',
        'description' => 'Benefício 4 (lista)'
    ],
    'hero_bullet_5' => [
        'category' => 'hero_bullets',
        'type' => 'text',
        'description' => 'Benefício 5 (lista)'
    ],
    'hero_promise_1' => [
        'category' => 'hero_promises',
        'type' => 'text',
        'description' => 'Frase de impacto 1'
    ],
    'hero_promise_2' => [
        'category' => 'hero_promises',
        'type' => 'text',
        'description' => 'Frase de impacto 2'
    ],
    'hero_promise_3' => [
        'category' => 'hero_promises',
        'type' => 'text',
        'description' => 'Frase de impacto 3'
    ],
    'hero_promise_4' => [
        'category' => 'hero_promises',
        'type' => 'text',
        'description' => 'Frase de impacto 4'
    ],
    'articles_button_text' => [
        'category' => 'articles',
        'type' => 'text',
        'description' => 'Texto do botão de artigos'
    ],
    'google_maps_api_key' => [
        'category' => 'reviews',
        'type' => 'text',
        'description' => 'Google Maps API Key'
    ],
    'google_place_id' => [
        'category' => 'reviews',
        'type' => 'text',
        'description' => 'Google Place ID'
    ],
    'google_reviews_min_rating' => [
        'category' => 'reviews',
        'type' => 'number',
        'description' => 'Nota mínima dos comentários (ex: 2)'
    ],
    'google_reviews_limit' => [
        'category' => 'reviews',
        'type' => 'number',
        'description' => 'Quantidade máxima de comentários'
    ],
    'whatsapp_number' => [
        'category' => 'contato',
        'type' => 'text',
        'description' => 'WhatsApp (com DDD)'
    ],
    'whatsapp_message' => [
        'category' => 'contato',
        'type' => 'textarea',
        'description' => 'Mensagem padrão do WhatsApp'
    ],
    'footer_cnpj' => [
        'category' => 'contato',
        'type' => 'text',
        'description' => 'CNPJ'
    ],
    'company_legal_name' => [
        'category' => 'contato',
        'type' => 'text',
        'description' => 'Razão social'
    ],
    'seo_title' => [
        'category' => 'marketinfo',
        'type' => 'text',
        'description' => 'Título SEO (title da página)'
    ],
    'seo_description' => [
        'category' => 'marketinfo',
        'type' => 'textarea',
        'description' => 'Descrição SEO (meta description)'
    ],
    'seo_keywords' => [
        'category' => 'marketinfo',
        'type' => 'text',
        'description' => 'Palavras-chave SEO (meta keywords)'
    ],
    'seo_og_title' => [
        'category' => 'marketinfo',
        'type' => 'text',
        'description' => 'Título Open Graph'
    ],
    'seo_og_description' => [
        'category' => 'marketinfo',
        'type' => 'textarea',
        'description' => 'Descrição Open Graph'
    ],
    'seo_og_image' => [
        'category' => 'marketinfo',
        'type' => 'text',
        'description' => 'Imagem Open Graph (URL ou caminho)'
    ],
    'google_ads_tag' => [
        'category' => 'marketinfo',
        'type' => 'textarea',
        'description' => 'Tag do Google Ads/Google Tag'
    ],
    'meta_pixel_tag' => [
        'category' => 'marketinfo',
        'type' => 'textarea',
        'description' => 'Tag do Meta Pixel'
    ],
    'google_reviews_cache' => [
        'category' => 'hidden',
        'type' => 'textarea',
        'description' => 'Cache interno de reviews'
    ],
    'google_reviews_cache_at' => [
        'category' => 'hidden',
        'type' => 'text',
        'description' => 'Data do cache de reviews'
    ],
    'privacy_policy' => [
        'category' => 'legal',
        'type' => 'textarea',
        'description' => 'Política de Privacidade'
    ],
    'terms_policy' => [
        'category' => 'legal',
        'type' => 'textarea',
        'description' => 'Termos de Uso'
    ],
    'cookies_policy' => [
        'category' => 'legal',
        'type' => 'textarea',
        'description' => 'Política de Cookies'
    ],
];

// POST: Salvar configurações
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    try {
        $configs = $_POST['config'] ?? [];
        
        foreach($configs as $key => $value) {
            $definition = $config_definitions[$key] ?? null;
            $category = $definition['category'] ?? 'geral';
            $type = $definition['type'] ?? 'text';
            $description = $definition['description'] ?? $key;

            $stmt = $pdo->prepare("
                INSERT INTO site_config (config_key, config_value, config_type, description, category, updated_by, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    config_value = VALUES(config_value),
                    config_type = VALUES(config_type),
                    description = VALUES(description),
                    category = VALUES(category),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");
            $stmt->execute([$key, trim($value), $type, $description, $category, $user_id]);
        }
        
        flash_set('success', 'Configurações atualizadas com sucesso!');
        redirect($base.'/app.php?page=marketing_site');
    } catch(Exception $e) {
        flash_set('danger', 'Erro ao salvar: '.$e->getMessage());
    }
}

// Buscar todas as configurações
$configs = $pdo->query("SELECT * FROM site_config ORDER BY category, config_key")->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por categoria
$grouped = [];
foreach($configs as $cfg) {
    $cat = $cfg['category'] ?? 'geral';
    if(!isset($grouped[$cat])) $grouped[$cat] = [];
    $grouped[$cat][] = $cfg;
}

foreach ($config_definitions as $key => $definition) {
    $found = false;
    foreach ($configs as $cfg) {
        if ($cfg['config_key'] === $key) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $cat = $definition['category'];
        if (!isset($grouped[$cat])) $grouped[$cat] = [];
        $grouped[$cat][] = [
            'config_key' => $key,
            'config_value' => '',
            'config_type' => $definition['type'],
            'description' => $definition['description'],
            'category' => $definition['category'],
        ];
    }
}
?>

<style>
.config-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.config-section h3 {
    color: #f59e0b;
    font-weight: 900;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 3px solid #f59e0b;
}

.form-label {
    font-weight: 600;
    color: #374151;
}

.btn-preview {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-weight: 900;">🎨 Gerenciar Site</h2>
            <p class="text-muted mb-0">Edite os textos e informações do site público</p>
        </div>
        <a href="<?= h($base) ?>/" target="_blank" class="btn btn-outline-primary">
            👁️ Ver Site Público
        </a>
    </div>

    <form method="post">
        <input type="hidden" name="action" value="save_config">

        <!-- Hero Section -->
        <div class="config-section" id="articles">
            <h3>🖼️ Hero / Banner Principal</h3>
            <p class="text-muted mb-4">Textos que aparecem na primeira seção do site</p>
            
            <?php foreach($grouped['hero'] ?? [] as $cfg): ?>
                <div class="mb-3">
                    <label class="form-label"><?= h($cfg['description']) ?></label>
                    <?php if($cfg['config_type'] === 'textarea'): ?>
                        <textarea class="form-control" name="config[<?= h($cfg['config_key']) ?>]" rows="3"><?= h($cfg['config_value']) ?></textarea>
                    <?php else: ?>
                        <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                    <?php endif; ?>
                    <small class="text-muted">Chave: <?= h($cfg['config_key']) ?></small>
                </div>
            <?php endforeach; ?>
            
            <div class="alert alert-info mt-3">
                <strong>💡 Dica:</strong> Para adicionar imagens ao Hero, acesse <a href="<?= h($base) ?>/app.php?page=marketing_hero">🖼️ Hero / Banner</a>
            </div>
        </div>

        <!-- Benefícios do Hero -->
        <div class="config-section">
            <h3>✅ Benefícios e Frases de Impacto</h3>
            <p class="text-muted mb-4">Frases curtas que resolvem dores e aumentam conversão</p>

            <div class="row g-3">
                <?php foreach($grouped['hero_promises'] ?? [] as $cfg): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= h($cfg['description']) ?></label>
                        <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mt-1">
                <?php foreach($grouped['hero_bullets'] ?? [] as $cfg): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= h($cfg['description']) ?></label>
                        <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reviews Google -->
        <div class="config-section">
            <h3>⭐ Reviews do Google Maps</h3>
            <p class="text-muted mb-4">Configuração para puxar automaticamente as avaliações</p>

            <div class="row g-3">
                <?php foreach($grouped['reviews'] ?? [] as $cfg): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= h($cfg['description']) ?></label>
                        <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="alert alert-warning mt-3">
                Use um Place ID válido e uma API Key com acesso ao Google Places. Comentários com 1 estrela são automaticamente ignorados.
            </div>
        </div>

        <!-- Artigos -->
        <div class="config-section">
            <h3>📰 Artigos</h3>
            <p class="text-muted mb-4">Botão e acesso aos artigos do site</p>

            <?php foreach($grouped['articles'] ?? [] as $cfg): ?>
                <?php if ($cfg['config_key'] === 'articles_url') continue; ?>
                <div class="mb-3">
                    <label class="form-label"><?= h($cfg['description']) ?></label>
                    <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Estatísticas -->
        <div class="config-section">
            <h3>📊 Estatísticas</h3>
            <p class="text-muted mb-4">Números que aparecem logo abaixo do Hero</p>
            
            <div class="row g-3">
                <?php foreach($grouped['estatisticas'] ?? [] as $cfg): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= h($cfg['description']) ?></label>
                        <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Diferenciais -->
        <div class="config-section">
            <h3>⭐ Diferenciais</h3>
            <p class="text-muted mb-4">Seção "Por que escolher a empresa"</p>
            
            <?php
            $diferenciais = [];
            foreach($grouped['diferenciais'] ?? [] as $cfg) {
                preg_match('/diferencial_(\d+)_(titulo|texto)/', $cfg['config_key'], $matches);
                if($matches) {
                    $num = $matches[1];
                    $tipo = $matches[2];
                    if(!isset($diferenciais[$num])) $diferenciais[$num] = [];
                    $diferenciais[$num][$tipo] = $cfg;
                }
            }
            ?>
            
            <div class="row g-4">
                <?php foreach($diferenciais as $num => $dif): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Diferencial <?= $num ?></h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Título</label>
                                    <input type="text" class="form-control" name="config[<?= h($dif['titulo']['config_key']) ?>]" value="<?= h($dif['titulo']['config_value']) ?>">
                                </div>
                                
                                <div class="mb-0">
                                    <label class="form-label">Texto</label>
                                    <textarea class="form-control" name="config[<?= h($dif['texto']['config_key']) ?>]" rows="3"><?= h($dif['texto']['config_value']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Contato -->
        <div class="config-section">
            <h3>📞 Informações de Contato</h3>
            <p class="text-muted mb-4">Aparecem no rodapé e página de contato</p>
            
            <div class="row g-3">
                <?php foreach($grouped['contato'] ?? [] as $cfg): ?>
                    <div class="col-md-4">
                        <label class="form-label"><?= h($cfg['description']) ?></label>
                        <?php if($cfg['config_type'] === 'textarea'): ?>
                            <textarea class="form-control" name="config[<?= h($cfg['config_key']) ?>]" rows="3"><?= h($cfg['config_value']) ?></textarea>
                        <?php else: ?>
                            <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SEO / Ads -->
        <div class="config-section">
            <h3>📣 MarketInfo (SEO & Tags)</h3>
            <p class="text-muted mb-4">SEO do site e tags de conversão (Google Ads / Meta)</p>

            <?php foreach($grouped['marketinfo'] ?? [] as $cfg): ?>
                <div class="mb-3">
                    <label class="form-label"><?= h($cfg['description']) ?></label>
                    <?php if($cfg['config_type'] === 'textarea'): ?>
                        <textarea class="form-control" name="config[<?= h($cfg['config_key']) ?>]" rows="4"><?= h($cfg['config_value']) ?></textarea>
                    <?php else: ?>
                        <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                    <?php endif; ?>
                    <small class="text-muted">Chave: <?= h($cfg['config_key']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Políticas -->
        <div class="config-section">
            <h3>🧾 Políticas e Confiabilidade</h3>
            <p class="text-muted mb-4">Textos para privacidade, termos e cookies</p>

            <?php foreach($grouped['legal'] ?? [] as $cfg): ?>
                <div class="mb-3">
                    <label class="form-label"><?= h($cfg['description']) ?></label>
                    <?php if($cfg['config_type'] === 'textarea'): ?>
                        <textarea class="form-control" name="config[<?= h($cfg['config_key']) ?>]" rows="5"><?= h($cfg['config_value']) ?></textarea>
                    <?php else: ?>
                        <input type="text" class="form-control" name="config[<?= h($cfg['config_key']) ?>]" value="<?= h($cfg['config_value']) ?>">
                    <?php endif; ?>
                    <small class="text-muted">Chave: <?= h($cfg['config_key']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Botões de Ação -->
        <div class="d-flex gap-2 justify-content-end mb-5">
            <a href="<?= h($base) ?>/" target="_blank" class="btn btn-outline-secondary">
                👁️ Pré-visualizar
            </a>
            <button type="submit" class="btn btn-success btn-lg">
                💾 Salvar Alterações
            </button>
        </div>
    </form>
</div>

<!-- Botão flutuante de preview -->
<a href="<?= h($base) ?>/site/" target="_blank" class="btn btn-primary btn-lg btn-preview" title="Ver site público">
    👁️ Preview
</a>
