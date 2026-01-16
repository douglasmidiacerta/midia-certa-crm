-- Upgrade v3.9 - Melhorias solicitadas
-- Sistema de aprovação de arte online, adquirentes de cartão, taxas e frete

-- Tabela para adquirentes de cartão (maquininhas)
CREATE TABLE IF NOT EXISTS card_acquirers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT 'Nome da adquirente (ex: Stone, PagSeguro, Mercado Pago)',
  active TINYINT(1) DEFAULT 1,
  
  -- Taxas por modalidade
  tax_instant DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Taxa % para pagamento instantâneo (na hora)',
  tax_d1 DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Taxa % para D+1 (dia seguinte)',
  tax_installment DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Taxa % para parcelado (vencimento)',
  
  notes TEXT,
  created_at DATETIME,
  updated_at DATETIME,
  
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona campos de adquirente e modalidade nas contas a receber
ALTER TABLE ar_titles 
  ADD COLUMN IF NOT EXISTS card_acquirer_id INT DEFAULT NULL COMMENT 'ID da adquirente (se cartão)',
  ADD COLUMN IF NOT EXISTS card_mode VARCHAR(20) DEFAULT NULL COMMENT 'instant|d1|installment',
  ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor da taxa cobrada',
  ADD COLUMN IF NOT EXISTS net_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor líquido (amount - tax_amount)',
  ADD INDEX idx_card_acquirer (card_acquirer_id);

-- Adiciona campo de taxa nas contas a pagar (para registrar pagamento da taxa)
ALTER TABLE ap_titles
  ADD COLUMN IF NOT EXISTS is_card_tax TINYINT(1) DEFAULT 0 COMMENT 'Se é uma conta de taxa de cartão',
  ADD COLUMN IF NOT EXISTS card_acquirer_id INT DEFAULT NULL COMMENT 'Adquirente relacionada',
  ADD COLUMN IF NOT EXISTS related_ar_title_id INT DEFAULT NULL COMMENT 'Título a receber relacionado',
  ADD INDEX idx_card_tax (is_card_tax),
  ADD INDEX idx_card_acquirer_ap (card_acquirer_id);

-- Adiciona campos de frete na tabela OS
ALTER TABLE os
  ADD COLUMN IF NOT EXISTS delivery_fee_charged DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Valor do frete cobrado do cliente',
  ADD COLUMN IF NOT EXISTS delivery_cost_real DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Custo real do frete';

-- Tabela para tokens de aprovação de arte pelo cliente
CREATE TABLE IF NOT EXISTS os_approval_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token único para link de aprovação',
  expires_at DATETIME NOT NULL COMMENT 'Data de expiração do token',
  used_at DATETIME DEFAULT NULL COMMENT 'Quando foi usado',
  client_ip VARCHAR(45) DEFAULT NULL,
  client_signature TEXT DEFAULT NULL COMMENT 'Assinatura digital do cliente',
  client_name VARCHAR(200) DEFAULT NULL COMMENT 'Nome digitado pelo cliente',
  approved TINYINT(1) DEFAULT 0 COMMENT 'Se o cliente aprovou',
  rejected TINYINT(1) DEFAULT 0 COMMENT 'Se o cliente rejeitou',
  rejection_reason TEXT DEFAULT NULL,
  created_at DATETIME,
  
  INDEX idx_os (os_id),
  INDEX idx_token (token),
  INDEX idx_expires (expires_at),
  
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para tracking/acompanhamento do pedido pelo cliente
CREATE TABLE IF NOT EXISTS os_tracking_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token permanente para acompanhamento',
  created_at DATETIME,
  last_accessed_at DATETIME DEFAULT NULL,
  access_count INT DEFAULT 0,
  
  INDEX idx_os (os_id),
  INDEX idx_token (token),
  
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere algumas adquirentes padrão (exemplo)
INSERT INTO card_acquirers (name, tax_instant, tax_d1, tax_installment, created_at) VALUES
('Stone', 2.99, 2.49, 3.99, NOW()),
('PagSeguro', 3.19, 2.79, 4.19, NOW()),
('Mercado Pago', 2.89, 2.39, 3.89, NOW()),
('GetNet', 2.99, 2.49, 3.99, NOW())
ON DUPLICATE KEY UPDATE name=name;

-- Adiciona configuração de URL base para links públicos
ALTER TABLE settings
  ADD COLUMN IF NOT EXISTS public_base_url VARCHAR(255) DEFAULT NULL COMMENT 'URL base para links públicos (ex: https://seudominio.com.br/crm)';
