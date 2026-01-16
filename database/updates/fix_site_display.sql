-- Corrigir exibição do site

-- 1. Remover grupos duplicados (manter apenas Cartão de Visita e Panfletos)
DELETE FROM site_featured_products WHERE id > 2;

-- 2. Adicionar imagem ao Hero se não existir
INSERT INTO site_hero_images (image_path, title, subtitle, button_text, button_link, order_num, active)
SELECT 'assets/images/midia-certa-432x107.png', 'Impressão Profissional com Qualidade', 'Transforme suas ideias em realidade', 'Ver Produtos', '/produtos.php', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM site_hero_images LIMIT 1);

-- 3. Garantir que grupo Panfletos está ativo
UPDATE site_featured_products SET active = 1 WHERE id IN (1,2);

-- 4. Adicionar produtos de Panfletos se não tiver
INSERT IGNORE INTO site_featured_products_items (featured_id, item_id, order_num)
SELECT 2, i.id, 0
FROM items i
JOIN categories c ON c.id = i.category_id
WHERE c.name LIKE 'Panfletos%' AND i.active = 1
LIMIT 6;

SELECT 'Site corrigido!' as status;
