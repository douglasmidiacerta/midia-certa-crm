-- upgrade_v3_2.sql
-- Executar uma vez no phpMyAdmin (banco gmidiace_sistema)

ALTER TABLE employees
  ADD COLUMN user_id INT NULL AFTER access_level;

ALTER TABLE employees
  ADD INDEX idx_emp_user (user_id);

ALTER TABLE employees
  ADD CONSTRAINT fk_emp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
