-- Tabela para solicitações de alteração de movimentações

CREATE TABLE IF NOT EXISTS cash_movement_change_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  movement_id INT NOT NULL,
  requested_by_user_id INT NOT NULL,
  request_type VARCHAR(20) NOT NULL COMMENT 'edit|delete',
  reason TEXT NOT NULL,
  new_amount DECIMAL(10,2) DEFAULT NULL,
  new_description VARCHAR(255) DEFAULT NULL,
  new_category VARCHAR(100) DEFAULT NULL,
  status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending|approved|rejected',
  reviewed_by_user_id INT DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  review_notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  
  INDEX idx_movement (movement_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
