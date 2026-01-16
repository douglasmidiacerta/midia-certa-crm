-- Adiciona status 'refugada' para OS devolvidas ou recusadas pelo cliente ou financeiro
-- Modifica o ENUM da coluna status na tabela os
ALTER TABLE os 
MODIFY COLUMN status ENUM('atendimento','arte','conferencia','producao','disponivel','finalizada','refugada','cancelada') 
NOT NULL DEFAULT 'atendimento';
