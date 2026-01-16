-- ============================================
-- INSTALAÇÃO DO CAROUSEL DE SLIDES
-- Execute este SQL no phpMyAdmin
-- ============================================

-- Criar tabela carousel_slides
CREATE TABLE IF NOT EXISTS `carousel_slides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `subtitle` TEXT,
    `button_text` VARCHAR(100),
    `button_link` VARCHAR(255),
    `image_path` VARCHAR(255),
    `background_color` VARCHAR(50) DEFAULT 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    `text_color` VARCHAR(20) DEFAULT '#ffffff',
    `order_num` INT DEFAULT 0,
    `active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir slides padrão (somente se a tabela estiver vazia)
INSERT INTO `carousel_slides` (`title`, `subtitle`, `button_text`, `button_link`, `background_color`, `order_num`, `active`)
SELECT * FROM (
    SELECT 
        'Impressão Profissional de Alta Qualidade' as title,
        'Transforme suas ideias em realidade com nossos serviços gráficos' as subtitle,
        'Ver Produtos' as button_text,
        '/site/produtos.php' as button_link,
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' as background_color,
        1 as order_num,
        1 as active
    UNION ALL
    SELECT 
        'Entrega Rápida e Confiável',
        'Prazo padrão de 4 dias úteis. Entraremos em contato para confirmar.',
        'Fale Conosco',
        '/site/contato.php',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        2,
        1
    UNION ALL
    SELECT 
        'Qualidade Premium, Preço Justo',
        'Equipamentos modernos e materiais de primeira linha',
        'Portal do Cliente',
        '/client_portal.php',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        3,
        1
) as tmp
WHERE NOT EXISTS (SELECT 1 FROM `carousel_slides` LIMIT 1);

-- Verificar instalação
SELECT 'INSTALAÇÃO CONCLUÍDA! Total de slides:' as Mensagem, COUNT(*) as Total FROM `carousel_slides`;
