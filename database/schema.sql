-- Mídia Certa CRM/ERP Gráfica - schema v2
SET sql_mode = 'STRICT_ALL_TABLES';
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY,
  next_os_number INT NOT NULL DEFAULT 17000,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  job_title VARCHAR(120),
  status ENUM('ativo','ferias','desligado') NOT NULL DEFAULT 'ativo',
  email VARCHAR(160) NOT NULL UNIQUE,
  email_personal VARCHAR(160),
  whatsapp VARCHAR(40),
  phone_fixed VARCHAR(40),
  cpf VARCHAR(40),
  rg VARCHAR(40),
  birth_date DATE,
  gender VARCHAR(40),
  cep VARCHAR(20),
  address_street VARCHAR(160),
  address_number VARCHAR(40),
  address_neighborhood VARCHAR(120),
  address_city VARCHAR(120),
  address_state VARCHAR(40),
  address_complement VARCHAR(120),
  ctps_number VARCHAR(40),
  ctps_series VARCHAR(40),
  ctps_uf VARCHAR(40),
  pis_pasep VARCHAR(40),
  admission_date DATE,
  contract_type ENUM('clt','pj','estagio','temporario') NOT NULL DEFAULT 'clt',
  department VARCHAR(120),
  manager_user_id INT,
  access_level ENUM('admin','operacional','visualizacao') NOT NULL DEFAULT 'operacional',
  photo_path VARCHAR(255),
  contract_file_path VARCHAR(255),
  role ENUM('admin','vendas','financeiro') NOT NULL DEFAULT 'vendas',
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  contact_name VARCHAR(120),
  whatsapp VARCHAR(40) NOT NULL,
  phone_fixed VARCHAR(40),
  email VARCHAR(160),
  cpf VARCHAR(40),
  cnpj VARCHAR(40),
  cep VARCHAR(20),
  address_street VARCHAR(160),
  address_number VARCHAR(40),
  address_neighborhood VARCHAR(120),
  address_city VARCHAR(120),
  address_state VARCHAR(40),
  address_complement VARCHAR(120),
  notes TEXT,
  -- compat (legado):
  phone VARCHAR(40),
  doc VARCHAR(40),
  address VARCHAR(255),
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  contact_name VARCHAR(120),
  whatsapp VARCHAR(40) NOT NULL,
  phone_fixed VARCHAR(40),
  email VARCHAR(160),
  cnpj VARCHAR(40),
  cep VARCHAR(20),
  address_street VARCHAR(160),
  address_number VARCHAR(40),
  address_neighborhood VARCHAR(120),
  address_city VARCHAR(120),
  address_state VARCHAR(40),
  address_complement VARCHAR(120),
  notes TEXT,
  -- compat (legado):
  phone VARCHAR(40),
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  kind ENUM('produto','servico','ambos') NOT NULL DEFAULT 'ambos',
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  format VARCHAR(120),
  vias VARCHAR(120),
  colors VARCHAR(120),
  type ENUM('produto','servico') NOT NULL,
  category_id INT NOT NULL,
  cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_supplier_costs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  supplier_id INT NOT NULL,
  cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255),
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_item_supplier (item_id, supplier_id),
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS os (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_number INT NOT NULL UNIQUE,
  code VARCHAR(40) NOT NULL UNIQUE, -- ex: 017000
  client_id INT NOT NULL,
  seller_user_id INT NOT NULL,
  os_type ENUM('produto','servico','mista') NOT NULL DEFAULT 'mista',
  status ENUM('atendimento','arte','conferencia','producao','disponivel','finalizada','cancelada') NOT NULL DEFAULT 'atendimento',
  delivery_method ENUM('retirada','motoboy','correios') NOT NULL DEFAULT 'retirada',
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0, -- quanto será cobrado do cliente (entra no saldo)
  delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0, -- custo real (motoboy/empresa)
  delivery_pay_to ENUM('empresa','motoboy') NOT NULL DEFAULT 'empresa', -- quem recebe o custo
  delivery_pay_mode ENUM('imediato','semanal') NOT NULL DEFAULT 'imediato', -- pagamento do motoboy
  delivery_motoboy VARCHAR(160),
  delivery_notes VARCHAR(255),
  due_date DATE,
  notes TEXT,
  approved_at DATETIME,
  approved_by_user_id INT,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (seller_user_id) REFERENCES users(id),
  FOREIGN KEY (approved_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS os_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  item_id INT NOT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255),
  created_at DATETIME NOT NULL,
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS os_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  kind ENUM('arte_pdf','comprovante','outro') NOT NULL DEFAULT 'outro',
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255),
  mime VARCHAR(120),
  size INT,
  created_by_user_id INT,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compras / O.C (despesas e compras)
CREATE TABLE IF NOT EXISTS purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  supplier_id INT,
  created_by_user_id INT NOT NULL,
  status ENUM('aberta','aprovada','paga','cancelada') NOT NULL DEFAULT 'aberta',
  notes TEXT,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  category VARCHAR(120),
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Caixas/Contas
CREATE TABLE IF NOT EXISTS cash_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('dinheiro','pix','cartao','banco','outro') NOT NULL DEFAULT 'outro',
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contas a receber / pagar (títulos)
CREATE TABLE IF NOT EXISTS ar_titles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  os_id INT NOT NULL,
  kind ENUM('entrada','saldo','cartao_parcela') NOT NULL DEFAULT 'entrada',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  method ENUM('pix','dinheiro','cartao','boleto','na_retirada') NOT NULL DEFAULT 'pix',
  due_date DATE,
  status ENUM('aberto','recebido','cancelado') NOT NULL DEFAULT 'aberto',
  received_at DATETIME,
  received_by_user_id INT,
  cash_account_id INT,
  proof_file_id INT,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (os_id) REFERENCES os(id) ON DELETE CASCADE,
  FOREIGN KEY (received_by_user_id) REFERENCES users(id),
  FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
  FOREIGN KEY (proof_file_id) REFERENCES os_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ap_titles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT,
  supplier_id INT,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  due_date DATE,
  status ENUM('aberto','pago','cancelado') NOT NULL DEFAULT 'aberto',
  paid_at DATETIME,
  paid_by_user_id INT,
  cash_account_id INT,
  proof_file_id INT,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE SET NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  FOREIGN KEY (paid_by_user_id) REFERENCES users(id),
  FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
  FOREIGN KEY (proof_file_id) REFERENCES os_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_moves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cash_account_id INT NOT NULL,
  direction ENUM('entrada','saida','transferencia') NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  description VARCHAR(255),
  related_ar_id INT,
  related_ap_id INT,
  created_by_user_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id),
  FOREIGN KEY (related_ar_id) REFERENCES ar_titles(id),
  FOREIGN KEY (related_ap_id) REFERENCES ap_titles(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Solicitações para admin (cancelamento/alterações críticas)
CREATE TABLE IF NOT EXISTS admin_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('cancel_os','cancel_oc','edit_os','edit_oc','estorno') NOT NULL,
  ref_table ENUM('os','purchases','ar_titles','ap_titles') NOT NULL,
  ref_id INT NOT NULL,
  requested_by_user_id INT NOT NULL,
  status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
  reason TEXT,
  decided_by_user_id INT,
  decided_at DATETIME,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (requested_by_user_id) REFERENCES users(id),
  FOREIGN KEY (decided_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(60) NOT NULL,
  entity_id INT,
  user_id INT,
  payload JSON,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
