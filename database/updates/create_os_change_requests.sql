-- Tabela de solicitações de alteração/exclusão de O.S
CREATE TABLE IF NOT EXISTS os_change_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  request_type ENUM('reopen', 'delete') NOT NULL,
  requested_by INT NOT NULL,
  requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reason TEXT,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  review_notes TEXT NULL,
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
  FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_os_id (os_id),
  INDEX idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de exclusões de O.S
CREATE TABLE IF NOT EXISTS os_deletion_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  os_code VARCHAR(50),
  client_id INT,
  client_name VARCHAR(255),
  total_value DECIMAL(10,2),
  deleted_by INT NOT NULL,
  deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  deletion_reason TEXT,
  os_data JSON,
  FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_deleted_at (deleted_at),
  INDEX idx_os_code (os_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
