-- upgrade v3.3 - Entrega (motoboy) + ajustes de status
SET sql_mode = 'STRICT_ALL_TABLES';

ALTER TABLE os
  ADD COLUMN IF NOT EXISTS delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER delivery_fee,
  ADD COLUMN IF NOT EXISTS delivery_pay_to ENUM('empresa','motoboy') NOT NULL DEFAULT 'empresa' AFTER delivery_cost,
  ADD COLUMN IF NOT EXISTS delivery_pay_mode ENUM('imediato','semanal') NOT NULL DEFAULT 'imediato' AFTER delivery_pay_to;

-- Garante o enum de status esperado
-- (se seu banco já estiver ok, ignore)
-- Observação: dependendo do MySQL/MariaDB, alterar ENUM exige recriar a coluna.
-- Ajuste manual se necessário:
-- ALTER TABLE os MODIFY status ENUM('atendimento','arte','conferencia','producao','disponivel','finalizada','cancelada') NOT NULL DEFAULT 'atendimento';
