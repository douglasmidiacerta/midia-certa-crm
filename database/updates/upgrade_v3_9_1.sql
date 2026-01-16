-- Upgrade v3.9.1 - Melhorias no sistema de adquirentes
-- Taxas de 1x até 21x e sistema de pagamento (D+0, D+1, D+30)

-- Remove colunas antigas de taxa
ALTER TABLE card_acquirers 
  DROP COLUMN IF EXISTS tax_instant,
  DROP COLUMN IF EXISTS tax_d1,
  DROP COLUMN IF EXISTS tax_installment;

-- Adiciona sistema de pagamento
ALTER TABLE card_acquirers
  ADD COLUMN IF NOT EXISTS payment_system VARCHAR(10) DEFAULT 'D+30' COMMENT 'D+0|D+1|D+30',
  ADD COLUMN IF NOT EXISTS payment_days INT DEFAULT 30 COMMENT 'Dias para receber (0, 1, ou 30)';

-- Cria tabela para taxas por parcela
CREATE TABLE IF NOT EXISTS card_acquirer_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  acquirer_id INT NOT NULL,
  installments INT NOT NULL COMMENT 'Número de parcelas (1 a 21)',
  fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa em %',
  
  UNIQUE KEY unique_acquirer_installment (acquirer_id, installments),
  INDEX idx_acquirer (acquirer_id),
  
  FOREIGN KEY (acquirer_id) REFERENCES card_acquirers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atualiza dados existentes (converte taxas antigas para nova estrutura)
-- Se houver adquirentes já cadastradas, cria taxas padrão
INSERT INTO card_acquirer_fees (acquirer_id, installments, fee_percent)
SELECT id, 1, 2.99 FROM card_acquirers WHERE active = 1
ON DUPLICATE KEY UPDATE fee_percent = fee_percent;

-- Atualiza sistema de pagamento padrão
UPDATE card_acquirers SET payment_system = 'D+30', payment_days = 30 WHERE payment_system IS NULL;
