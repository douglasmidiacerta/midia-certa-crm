-- v3.7 - Fluxo novo de O.S + solicitações ao MASTER + comprovante de entrada

-- 1) Adiciona status "refugado" (arquivo devolvido pelo financeiro)
-- ATENÇÃO: ajuste conforme seu MySQL/MariaDB.
ALTER TABLE os
  MODIFY COLUMN status ENUM('atendimento','conferencia','producao','disponivel','finalizada','refugado','cancelada')
  NOT NULL DEFAULT 'atendimento';

-- 2) Novos tipos de arquivos
ALTER TABLE os_files
  MODIFY COLUMN kind ENUM('arte_pdf','comprovante','entrada_comprovante','outro')
  NOT NULL DEFAULT 'outro';

-- 3) Títulos a receber: adiciona status "cancelado" (caso não exista)
ALTER TABLE ar_titles
  MODIFY COLUMN status ENUM('rascunho','aberto','recebido','cancelado')
  NOT NULL DEFAULT 'aberto';

-- 4) Tabela de solicitações ao MASTER (reabertura/cancelamento)
CREATE TABLE IF NOT EXISTS os_master_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  req_type ENUM('reabrir','cancelar') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
  requested_by_user_id INT NOT NULL,
  decided_by_user_id INT NULL,
  created_at DATETIME NOT NULL,
  decided_at DATETIME NULL,
  INDEX idx_os_master_requests_status (status),
  INDEX idx_os_master_requests_os (os_id),
  CONSTRAINT fk_os_master_requests_os FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
  CONSTRAINT fk_os_master_requests_req_user FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_os_master_requests_dec_user FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
