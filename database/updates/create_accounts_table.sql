-- Cria tabela accounts se não existir

CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT 'Nome da conta/caixa',
  bank_name VARCHAR(100) DEFAULT NULL COMMENT 'Nome do banco',
  account_number VARCHAR(50) DEFAULT NULL COMMENT 'Número da conta',
  initial_balance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Saldo inicial',
  current_balance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Saldo atual (calculado)',
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME,
  
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere algumas contas padrão se a tabela estiver vazia
INSERT INTO accounts (name, bank_name, initial_balance, current_balance, active, created_at)
SELECT 'Caixa Geral', 'Dinheiro', 0.00, 0.00, 1, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM accounts LIMIT 1);

SELECT 'Tabela accounts criada com sucesso!' as status;
