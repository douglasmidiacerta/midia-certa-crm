-- Migração: Corrigir tabelas e colunas faltantes
-- Data: 16/01/2026
-- Autor: Sistema Automático

-- 1. Criar tabela site_config (para marketing_site.php)
CREATE TABLE IF NOT EXISTS site_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hero_title VARCHAR(255) DEFAULT 'Bem-vindo',
    hero_subtitle TEXT NULL,
    hero_cta_text VARCHAR(100) DEFAULT 'Saiba Mais',
    hero_cta_link VARCHAR(255) DEFAULT '#',
    about_title VARCHAR(255) DEFAULT 'Sobre Nós',
    about_text TEXT NULL,
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(20) NULL,
    contact_address TEXT NULL,
    footer_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configuração padrão se não existir
INSERT IGNORE INTO site_config (id, hero_title, hero_subtitle) VALUES 
(1, 'Mídia Certa Gráfica', 'Soluções gráficas de qualidade para seu negócio');

-- 2. Criar tabela site_featured_products (para marketing_produtos.php)
CREATE TABLE IF NOT EXISTS site_featured_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Criar tabela os_change_requests (para os_requests.php)
CREATE TABLE IF NOT EXISTS os_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT NOT NULL,
    requested_by INT NULL,
    request_type ENUM('change', 'delete') NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    notes TEXT NULL,
    FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Adicionar coluna client_id na tabela ar_titles (A Receber)
ALTER TABLE ar_titles 
ADD COLUMN IF NOT EXISTS client_id INT NULL AFTER id,
ADD INDEX IF NOT EXISTS idx_client_id (client_id);

-- 5. Adicionar coluna account_id na tabela ap_titles (A Pagar)
ALTER TABLE ap_titles 
ADD COLUMN IF NOT EXISTS account_id INT NULL AFTER id,
ADD INDEX IF NOT EXISTS idx_account_id (account_id);

-- 6. Adicionar coluna category na tabela ap_titles
ALTER TABLE ap_titles 
ADD COLUMN IF NOT EXISTS category VARCHAR(100) NULL AFTER account_id;

-- 7. Adicionar coluna type na tabela accounts (para relatórios)
ALTER TABLE accounts 
ADD COLUMN IF NOT EXISTS type ENUM('bank', 'cash', 'other') DEFAULT 'bank' AFTER name;

-- 8. Adicionar coluna doc_kind na tabela clients (para portal do cliente)
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS doc_kind ENUM('cpf', 'cnpj') DEFAULT 'cpf' AFTER doc;

-- 9. Atualizar doc_kind baseado no tamanho do documento
UPDATE clients 
SET doc_kind = CASE 
    WHEN LENGTH(REPLACE(REPLACE(REPLACE(doc, '.', ''), '-', ''), '/', '')) = 14 THEN 'cnpj'
    ELSE 'cpf'
END
WHERE doc IS NOT NULL AND doc_kind IS NULL;

-- 10. Adicionar colunas na tabela os para gestão de produção (se não existirem)
ALTER TABLE os 
ADD COLUMN IF NOT EXISTS production_status VARCHAR(50) NULL AFTER status,
ADD COLUMN IF NOT EXISTS production_notes TEXT NULL AFTER production_status;
