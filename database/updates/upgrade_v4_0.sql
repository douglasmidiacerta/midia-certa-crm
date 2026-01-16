-- Upgrade v4.0 - Sistema completo de gestão
-- Melhorias em parcelamento, categorias, fluxo produtivo, financeiro e DRE

-- ========================================
-- PARTE 1: MELHORIAS EM BOLETO E PARCELAS
-- ========================================

-- Adiciona campos de parcelamento em ar_titles
ALTER TABLE ar_titles
  ADD COLUMN IF NOT EXISTS total_installments INT DEFAULT 1 COMMENT 'Total de parcelas',
  ADD COLUMN IF NOT EXISTS custom_due_date DATE DEFAULT NULL COMMENT 'Vencimento personalizado';

-- ========================================
-- PARTE 2: CATEGORIAS E PRODUTOS
-- ========================================

-- Garante que a tabela categories existe e tem os campos necessários
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS icon VARCHAR(50) DEFAULT NULL COMMENT 'Ícone da categoria',
  ADD COLUMN IF NOT EXISTS color VARCHAR(20) DEFAULT NULL COMMENT 'Cor da categoria',
  ADD COLUMN IF NOT EXISTS display_order INT DEFAULT 0;

-- ========================================
-- PARTE 3: FLUXO PRODUTIVO
-- ========================================

-- Adiciona campos de prazo e fluxo na OS
ALTER TABLE os
  ADD COLUMN IF NOT EXISTS delivery_deadline DATE DEFAULT NULL COMMENT 'Prazo de entrega prometido',
  ADD COLUMN IF NOT EXISTS production_purchase_date DATE DEFAULT NULL COMMENT 'Data que foi feita a compra do item',
  ADD COLUMN IF NOT EXISTS production_expected_arrival DATE DEFAULT NULL COMMENT 'Previsão de chegada do fornecedor',
  ADD COLUMN IF NOT EXISTS locked TINYINT(1) DEFAULT 0 COMMENT 'Se está bloqueada para edição',
  ADD COLUMN IF NOT EXISTS locked_by_user_id INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS locked_at DATETIME DEFAULT NULL;

-- ========================================
-- PARTE 4: CHAT INTERNO
-- ========================================

CREATE TABLE IF NOT EXISTS os_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT NOT NULL,
  is_internal TINYINT(1) DEFAULT 1 COMMENT '1=interno (chat), 0=observação da OS',
  created_at DATETIME NOT NULL,
  
  INDEX idx_os (os_id),
  INDEX idx_user (user_id),
  INDEX idx_created (created_at),
  
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- PARTE 5: CONFIRMAÇÃO DE RECEBIMENTO
-- ========================================

CREATE TABLE IF NOT EXISTS os_delivery_confirmations (
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
  INDEX idx_token (token),
  
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- PARTE 6: GESTÃO DE CAIXAS (ACCOUNTS)
-- ========================================

-- Adiciona campos necessários em accounts
ALTER TABLE accounts
  ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) DEFAULT NULL COMMENT 'Nome do banco',
  ADD COLUMN IF NOT EXISTS account_number VARCHAR(50) DEFAULT NULL COMMENT 'Número da conta',
  ADD COLUMN IF NOT EXISTS initial_balance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Saldo inicial',
  ADD COLUMN IF NOT EXISTS current_balance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Saldo atual (calculado)';

-- Tabela de movimentações de caixa (cash_movements)
CREATE TABLE IF NOT EXISTS cash_movements (
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
  INDEX idx_reference (reference_type, reference_id),
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- PARTE 7: CONTAS A PAGAR MELHORADA
-- ========================================

-- Adiciona campos em ap_titles
ALTER TABLE ap_titles
  ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT NULL COMMENT 'aluguel|salario|fornecedor|taxa|marketing|outros',
  ADD COLUMN IF NOT EXISTS supplier_id INT DEFAULT NULL COMMENT 'ID do fornecedor',
  ADD COLUMN IF NOT EXISTS employee_id INT DEFAULT NULL COMMENT 'ID do funcionário (se for salário)',
  ADD COLUMN IF NOT EXISTS account_id INT DEFAULT NULL COMMENT 'Conta onde foi pago',
  ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(10,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS paid_by_user_id INT DEFAULT NULL;

-- ========================================
-- PARTE 8: CATEGORIAS DE DESPESAS (DRE)
-- ========================================

CREATE TABLE IF NOT EXISTS expense_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type VARCHAR(50) NOT NULL COMMENT 'fixo|variavel|investimento',
  description TEXT,
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME,
  
  UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere categorias padrão de despesas
INSERT INTO expense_categories (name, type, description, created_at) VALUES
('Aluguel', 'fixo', 'Aluguel do imóvel/espaço', NOW()),
('Salários', 'fixo', 'Salários dos colaboradores', NOW()),
('Encargos Trabalhistas', 'fixo', 'INSS, FGTS, etc', NOW()),
('Vale Refeição', 'fixo', 'VR dos colaboradores', NOW()),
('Vale Alimentação', 'fixo', 'VA dos colaboradores', NOW()),
('Vale Transporte', 'fixo', 'VT dos colaboradores', NOW()),
('Impostos e Tributos', 'fixo', 'Impostos sobre vendas', NOW()),
('Marketing', 'variavel', 'Gastos com marketing e publicidade', NOW()),
('Material de Escritório', 'variavel', 'Papelaria, consumíveis', NOW()),
('Energia Elétrica', 'fixo', 'Conta de luz', NOW()),
('Água', 'fixo', 'Conta de água', NOW()),
('Internet', 'fixo', 'Internet e telefone', NOW()),
('Manutenção', 'variavel', 'Manutenções diversas', NOW()),
('Fornecedores', 'variavel', 'Compras de produtos para revenda', NOW())
ON DUPLICATE KEY UPDATE name=name;

-- ========================================
-- PARTE 9: LOG E AUDITORIA
-- ========================================

-- Melhora a tabela de logs
ALTER TABLE logs
  ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS user_agent TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS request_data TEXT DEFAULT NULL COMMENT 'Dados da requisição (JSON)';

CREATE INDEX IF NOT EXISTS idx_logs_action ON logs(action);
CREATE INDEX IF NOT EXISTS idx_logs_date ON logs(created_at);

-- ========================================
-- PARTE 10: CAMPOS OBRIGATÓRIOS
-- ========================================

-- Garante que campos essenciais não sejam NULL
ALTER TABLE clients
  MODIFY COLUMN name VARCHAR(200) NOT NULL,
  MODIFY COLUMN phone VARCHAR(20) NOT NULL;

ALTER TABLE suppliers
  MODIFY COLUMN name VARCHAR(200) NOT NULL;

ALTER TABLE items
  MODIFY COLUMN name VARCHAR(200) NOT NULL,
  MODIFY COLUMN category_id INT NOT NULL;

-- ========================================
-- FINALIZAÇÃO
-- ========================================

-- Atualiza versão
UPDATE settings SET value = '4.0' WHERE name = 'version' LIMIT 1;
