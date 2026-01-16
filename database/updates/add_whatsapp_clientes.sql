sql
-- Adicionar campo WhatsApp na tabela de clientes
-- Data: 16/01/2026
-- Autor: Douglas

-- Adicionar o campo
ALTER TABLE clientes 
ADD COLUMN whatsapp VARCHAR(20) NULL 
AFTER telefone;

-- Adicionar índice para busca rápida (opcional)
CREATE INDEX idx_whatsapp ON clientes(whatsapp);
