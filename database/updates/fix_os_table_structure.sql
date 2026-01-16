-- Adiciona colunas faltantes na tabela os
-- doc_kind: tipo do documento (sale=venda, budget=orçamento, reaberta=reabertura)
ALTER TABLE os ADD COLUMN IF NOT EXISTS doc_kind ENUM('sale','budget','reaberta') NOT NULL DEFAULT 'sale' AFTER os_type;

-- delivery_fee_charged: valor do frete cobrado do cliente
ALTER TABLE os ADD COLUMN IF NOT EXISTS delivery_fee_charged DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER delivery_fee;

-- delivery_deadline: prazo de entrega informado ao cliente
ALTER TABLE os ADD COLUMN IF NOT EXISTS delivery_deadline DATE AFTER due_date;

-- arte_file e entrada_comprovante: nomes dos arquivos (agora usamos os_files, mas mantemos para compatibilidade)
ALTER TABLE os ADD COLUMN IF NOT EXISTS arte_file VARCHAR(255) AFTER notes;
ALTER TABLE os ADD COLUMN IF NOT EXISTS entrada_comprovante VARCHAR(255) AFTER arte_file;

-- client_name: cache do nome do cliente (para evitar JOIN em relatórios)
ALTER TABLE os ADD COLUMN IF NOT EXISTS client_name VARCHAR(180) AFTER client_id;

-- Atualiza client_name dos registros existentes
UPDATE os o 
INNER JOIN clients c ON c.id = o.client_id 
SET o.client_name = c.name 
WHERE o.client_name IS NULL OR o.client_name = '';
