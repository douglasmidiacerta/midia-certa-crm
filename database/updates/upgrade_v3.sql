-- Upgrade v2.1 -> v3 (cadastros completos)
-- Rode este SQL no phpMyAdmin (selecionando o banco).

-- CLIENTES
ALTER TABLE clients
  ADD COLUMN contact_name VARCHAR(120) NULL,
  ADD COLUMN whatsapp VARCHAR(40) NULL,
  ADD COLUMN phone_fixed VARCHAR(40) NULL,
  ADD COLUMN cpf VARCHAR(40) NULL,
  ADD COLUMN cnpj VARCHAR(40) NULL,
  ADD COLUMN cep VARCHAR(20) NULL,
  ADD COLUMN address_street VARCHAR(160) NULL,
  ADD COLUMN address_number VARCHAR(40) NULL,
  ADD COLUMN address_neighborhood VARCHAR(120) NULL,
  ADD COLUMN address_city VARCHAR(120) NULL,
  ADD COLUMN address_state VARCHAR(40) NULL,
  ADD COLUMN address_complement VARCHAR(120) NULL,
  ADD COLUMN notes TEXT NULL;

UPDATE clients SET whatsapp = COALESCE(whatsapp, phone) WHERE (whatsapp IS NULL OR whatsapp='') AND phone IS NOT NULL;

-- FORNECEDORES
ALTER TABLE suppliers
  ADD COLUMN contact_name VARCHAR(120) NULL,
  ADD COLUMN whatsapp VARCHAR(40) NULL,
  ADD COLUMN phone_fixed VARCHAR(40) NULL,
  ADD COLUMN cnpj VARCHAR(40) NULL,
  ADD COLUMN cep VARCHAR(20) NULL,
  ADD COLUMN address_street VARCHAR(160) NULL,
  ADD COLUMN address_number VARCHAR(40) NULL,
  ADD COLUMN address_neighborhood VARCHAR(120) NULL,
  ADD COLUMN address_city VARCHAR(120) NULL,
  ADD COLUMN address_state VARCHAR(40) NULL,
  ADD COLUMN address_complement VARCHAR(120) NULL,
  ADD COLUMN notes TEXT NULL;

UPDATE suppliers SET whatsapp = COALESCE(whatsapp, phone) WHERE (whatsapp IS NULL OR whatsapp='') AND phone IS NOT NULL;

-- PRODUTOS
ALTER TABLE items
  ADD COLUMN format VARCHAR(120) NULL,
  ADD COLUMN vias VARCHAR(120) NULL,
  ADD COLUMN colors VARCHAR(120) NULL;

-- FUNCIONÁRIOS (users)
ALTER TABLE users
  ADD COLUMN job_title VARCHAR(120) NULL,
  ADD COLUMN status ENUM('ativo','ferias','desligado') NOT NULL DEFAULT 'ativo',
  ADD COLUMN email_personal VARCHAR(160) NULL,
  ADD COLUMN whatsapp VARCHAR(40) NULL,
  ADD COLUMN phone_fixed VARCHAR(40) NULL,
  ADD COLUMN cpf VARCHAR(40) NULL,
  ADD COLUMN rg VARCHAR(40) NULL,
  ADD COLUMN birth_date DATE NULL,
  ADD COLUMN gender VARCHAR(40) NULL,
  ADD COLUMN cep VARCHAR(20) NULL,
  ADD COLUMN address_street VARCHAR(160) NULL,
  ADD COLUMN address_number VARCHAR(40) NULL,
  ADD COLUMN address_neighborhood VARCHAR(120) NULL,
  ADD COLUMN address_city VARCHAR(120) NULL,
  ADD COLUMN address_state VARCHAR(40) NULL,
  ADD COLUMN address_complement VARCHAR(120) NULL,
  ADD COLUMN ctps_number VARCHAR(40) NULL,
  ADD COLUMN ctps_series VARCHAR(40) NULL,
  ADD COLUMN ctps_uf VARCHAR(40) NULL,
  ADD COLUMN pis_pasep VARCHAR(40) NULL,
  ADD COLUMN admission_date DATE NULL,
  ADD COLUMN contract_type ENUM('clt','pj','estagio','temporario') NOT NULL DEFAULT 'clt',
  ADD COLUMN department VARCHAR(120) NULL,
  ADD COLUMN manager_user_id INT NULL,
  ADD COLUMN access_level ENUM('admin','operacional','visualizacao') NOT NULL DEFAULT 'operacional',
  ADD COLUMN photo_path VARCHAR(255) NULL,
  ADD COLUMN contract_file_path VARCHAR(255) NULL;
