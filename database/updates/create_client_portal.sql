-- =====================================================
-- PORTAL DO CLIENTE - Autenticação e Acesso
-- =====================================================

-- Tabela de autenticação de clientes
CREATE TABLE IF NOT EXISTS client_auth (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) DEFAULT 1,
  email_verified TINYINT(1) DEFAULT 0,
  verification_token VARCHAR(100) DEFAULT NULL,
  reset_token VARCHAR(100) DEFAULT NULL,
  reset_token_expires DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  INDEX idx_email (email),
  INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de sessões de clientes
CREATE TABLE IF NOT EXISTS client_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  session_token VARCHAR(100) NOT NULL UNIQUE,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  INDEX idx_token (session_token),
  INDEX idx_client (client_id),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de acesso do portal
CREATE TABLE IF NOT EXISTS client_portal_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  details TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  INDEX idx_client (client_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona campo de aceite de termos na tabela clients (se não existir)
ALTER TABLE clients 
  ADD COLUMN IF NOT EXISTS terms_accepted TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS terms_accepted_at DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS portal_enabled TINYINT(1) DEFAULT 1;

-- Limpa sessões expiradas (pode ser executado periodicamente)
-- DELETE FROM client_sessions WHERE expires_at < NOW();
