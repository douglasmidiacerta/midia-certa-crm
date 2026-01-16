-- ============================================================
-- CORRIGIR COLUNA 'origem' FALTANDO - Mídia Certa CRM
-- ============================================================
-- Execute no phpMyAdmin (aba SQL)
-- ============================================================

-- Adicionar coluna 'origem' na tabela 'os'
ALTER TABLE os 
ADD COLUMN origem VARCHAR(50) DEFAULT 'sistema' AFTER status;

-- Atualizar registros existentes para terem origem 'sistema'
UPDATE os SET origem = 'sistema' WHERE origem IS NULL;

-- ============================================================
-- VERIFICAÇÃO: Ver estrutura da tabela os
-- ============================================================
DESCRIBE os;

-- ============================================================
-- Após executar, o dashboard deve funcionar!
-- ============================================================
