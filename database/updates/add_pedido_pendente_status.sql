-- Adicionar status "Pedido Pendente" para pedidos vindos do site
-- que aguardam aprovação comercial do vendedor

-- Inserir novo status
INSERT INTO os_status (name, color, description, order_num, active) 
VALUES ('pedido_pendente', 'warning', 'Pedido aguardando aprovação comercial', 0, 1)
ON DUPLICATE KEY UPDATE 
  color = 'warning',
  description = 'Pedido aguardando aprovação comercial',
  order_num = 0;

-- Ajustar order_num dos outros status para dar espaço
UPDATE os_status SET order_num = order_num + 1 WHERE name != 'pedido_pendente' AND order_num >= 0;

-- Adicionar campos extras na tabela os para pedidos do site
ALTER TABLE os 
  ADD COLUMN IF NOT EXISTS origem VARCHAR(20) DEFAULT 'interno' COMMENT 'interno, site, api',
  ADD COLUMN IF NOT EXISTS pagamento_preferencial VARCHAR(50) DEFAULT NULL COMMENT 'Forma de pagamento preferencial do cliente',
  ADD COLUMN IF NOT EXISTS prazo_desejado DATE DEFAULT NULL COMMENT 'Prazo desejado pelo cliente';

SELECT 'Status "Pedido Pendente" criado com sucesso!' as resultado;
