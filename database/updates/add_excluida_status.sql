-- Adiciona status 'excluida' ao ENUM de status da tabela os
ALTER TABLE os MODIFY COLUMN status ENUM('atendimento','arte','conferencia','producao','disponivel','finalizada','cancelada','excluida','pedido_pendente','aguardando_aprovacao','refugado') NOT NULL DEFAULT 'atendimento';
