-- Adiciona colunas necessárias para o sistema de compras automáticas

-- Adiciona coluna os_id na tabela purchases para vincular com OS
ALTER TABLE purchases 
ADD COLUMN os_id INT DEFAULT NULL AFTER code,
ADD COLUMN total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER notes;

-- Adiciona foreign key
ALTER TABLE purchases 
ADD CONSTRAINT fk_purchases_os 
FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE SET NULL;

-- Adiciona coluna item_id na tabela purchase_lines para vincular com items
ALTER TABLE purchase_lines 
ADD COLUMN item_id INT DEFAULT NULL AFTER purchase_id;

-- Adiciona foreign key
ALTER TABLE purchase_lines 
ADD CONSTRAINT fk_purchase_lines_item 
FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL;
