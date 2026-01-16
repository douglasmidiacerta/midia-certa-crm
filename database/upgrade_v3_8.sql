-- v3.8 - Ajustes de fluxo Vendas x Financeiro + Caixa Bancos/Contas + AR manual
SET sql_mode = 'STRICT_ALL_TABLES';

-- 1) O.S: travar edição do vendedor após "Gerar venda"
ALTER TABLE os
  ADD COLUMN sales_locked TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN sales_locked_at DATETIME NULL,
  ADD COLUMN sales_locked_by_user_id INT NULL,
  ADD COLUMN ship_post_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN ship_freight_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN ship_service VARCHAR(120) NULL;

ALTER TABLE os
  ADD CONSTRAINT fk_os_sales_locked_by FOREIGN KEY (sales_locked_by_user_id) REFERENCES users(id);

-- 2) AR: permitir conta a receber manual (extra)
ALTER TABLE ar_titles
  MODIFY COLUMN os_id INT NULL,
  ADD COLUMN client_id INT NULL AFTER os_id,
  ADD COLUMN description VARCHAR(255) NULL AFTER method,
  MODIFY COLUMN kind ENUM('entrada','saldo','cartao_parcela','extra') NOT NULL DEFAULT 'entrada';

ALTER TABLE ar_titles
  ADD CONSTRAINT fk_ar_titles_client FOREIGN KEY (client_id) REFERENCES clients(id);

-- 3) Caixa: bancos e contas
CREATE TABLE IF NOT EXISTS cash_banks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE cash_accounts
  ADD COLUMN bank_id INT NULL AFTER id,
  ADD COLUMN pix_key VARCHAR(255) NULL AFTER name;

ALTER TABLE cash_accounts
  ADD CONSTRAINT fk_cash_accounts_bank FOREIGN KEY (bank_id) REFERENCES cash_banks(id);

-- 4) Solicitação de retirada (Financeiro solicita, Admin aprova, Financeiro executa)
CREATE TABLE IF NOT EXISTS cash_withdraw_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cash_account_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NOT NULL,
  status ENUM('pendente','aprovada','rejeitada','executada') NOT NULL DEFAULT 'pendente',
  requested_by_user_id INT NOT NULL,
  approved_by_user_id INT NULL,
  approved_at DATETIME NULL,
  executed_by_user_id INT NULL,
  executed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
  FOREIGN KEY (requested_by_user_id) REFERENCES users(id),
  FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
  FOREIGN KEY (executed_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
