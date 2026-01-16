-- Upgrade v4.0 FIX - Corrige problemas de foreign keys

-- Cria cash_movements SEM foreign keys (adiciona depois)
DROP TABLE IF EXISTS cash_movements;
CREATE TABLE cash_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  movement_type VARCHAR(20) NOT NULL COMMENT 'entrada|saida|transferencia',
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL COMMENT 'Categoria do gasto/receita',
  reference_type VARCHAR(50) DEFAULT NULL COMMENT 'ar_title|ap_title|manual',
  reference_id INT DEFAULT NULL,
  created_by_user_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  
  INDEX idx_account (account_id),
  INDEX idx_type (movement_type),
  INDEX idx_date (created_at),
  INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cria os_comments SEM foreign keys
DROP TABLE IF EXISTS os_comments;
CREATE TABLE os_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT NOT NULL,
  is_internal TINYINT(1) DEFAULT 1 COMMENT '1=interno (chat), 0=observação da OS',
  created_at DATETIME NOT NULL,
  
  INDEX idx_os (os_id),
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cria os_delivery_confirmations SEM foreign keys
DROP TABLE IF EXISTS os_delivery_confirmations;
CREATE TABLE os_delivery_confirmations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  confirmed_at DATETIME DEFAULT NULL,
  client_name VARCHAR(200) DEFAULT NULL,
  client_signature TEXT DEFAULT NULL,
  client_ip VARCHAR(45) DEFAULT NULL,
  items_ok TINYINT(1) DEFAULT 0 COMMENT 'Cliente confirmou que itens estão OK',
  created_at DATETIME NOT NULL,
  
  INDEX idx_os (os_id),
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verifica se as tabelas foram criadas
SELECT 'Tabelas criadas com sucesso!' as status;
