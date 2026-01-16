-- DRE Enhanced - Novas métricas e centros de custo

-- Adiciona categoria em ap_titles se não existir
ALTER TABLE ap_titles
  ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT NULL COMMENT 'operating_expenses|payroll|taxes|marketing|product_costs';

-- Adiciona campos de folha de pagamento em users
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS salary DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Salário mensal',
  ADD COLUMN IF NOT EXISTS vr_monthly DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Vale refeição mensal',
  ADD COLUMN IF NOT EXISTS va_monthly DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Vale alimentação mensal',
  ADD COLUMN IF NOT EXISTS vt_monthly DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Vale transporte mensal',
  ADD COLUMN IF NOT EXISTS other_benefits DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Outros benefícios mensais';

-- Adiciona campo de motivo de prejuízo em OS
ALTER TABLE os
  ADD COLUMN IF NOT EXISTS loss_reason TEXT DEFAULT NULL COMMENT 'Motivo do prejuízo quando custo > receita';
