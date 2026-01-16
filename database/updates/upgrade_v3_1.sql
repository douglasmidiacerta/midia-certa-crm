-- upgrade_v3_1.sql
-- Executar uma vez no phpMyAdmin (banco gmidiace_sistema)

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  role_title VARCHAR(120) NOT NULL DEFAULT '',
  status ENUM('ativo','ferias','desligado') NOT NULL DEFAULT 'ativo',

  cpf VARCHAR(30) DEFAULT '',
  rg VARCHAR(30) DEFAULT '',
  birth_date DATE NULL,
  gender VARCHAR(40) DEFAULT '',

  email_work VARCHAR(160) DEFAULT '',
  email_personal VARCHAR(160) DEFAULT '',
  whatsapp VARCHAR(40) NOT NULL DEFAULT '',

  cep VARCHAR(20) DEFAULT '',
  street VARCHAR(160) DEFAULT '',
  number VARCHAR(30) DEFAULT '',
  neighborhood VARCHAR(120) DEFAULT '',
  city VARCHAR(120) DEFAULT '',
  state VARCHAR(8) DEFAULT '',
  complement VARCHAR(120) DEFAULT '',

  ctps_number VARCHAR(40) DEFAULT '',
  ctps_series VARCHAR(40) DEFAULT '',
  ctps_uf VARCHAR(8) DEFAULT '',
  pis VARCHAR(40) DEFAULT '',
  admission_date DATE NULL,
  contract_type ENUM('clt','pj','estagio','temporario') NOT NULL DEFAULT 'clt',

  department VARCHAR(120) DEFAULT '',
  manager_id INT NULL,
  access_level ENUM('admin','operacional','visualizacao') NOT NULL DEFAULT 'operacional',

  photo_path VARCHAR(255) DEFAULT '',
  doc_path VARCHAR(255) DEFAULT '',

  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,

  INDEX (manager_id),
  CONSTRAINT fk_emp_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
