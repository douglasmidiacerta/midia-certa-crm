-- =====================================================
-- PERMISSÕES GRANULARES POR USUÁRIO
-- Permite controle individual de acesso a cada funcionalidade
-- =====================================================

-- Adiciona campos de permissões específicas na tabela users
ALTER TABLE users 
  -- Permissões de Vendas/OS
  ADD COLUMN IF NOT EXISTS perm_os_view TINYINT(1) DEFAULT 1 COMMENT 'Ver O.S',
  ADD COLUMN IF NOT EXISTS perm_os_create TINYINT(1) DEFAULT 1 COMMENT 'Criar O.S',
  ADD COLUMN IF NOT EXISTS perm_os_edit TINYINT(1) DEFAULT 0 COMMENT 'Editar O.S',
  ADD COLUMN IF NOT EXISTS perm_os_delete TINYINT(1) DEFAULT 0 COMMENT 'Excluir O.S',
  
  -- Permissões de Clientes
  ADD COLUMN IF NOT EXISTS perm_clients_view TINYINT(1) DEFAULT 1 COMMENT 'Ver clientes',
  ADD COLUMN IF NOT EXISTS perm_clients_create TINYINT(1) DEFAULT 1 COMMENT 'Criar clientes',
  ADD COLUMN IF NOT EXISTS perm_clients_edit TINYINT(1) DEFAULT 0 COMMENT 'Editar clientes',
  ADD COLUMN IF NOT EXISTS perm_clients_delete TINYINT(1) DEFAULT 0 COMMENT 'Excluir clientes',
  
  -- Permissões Financeiras
  ADD COLUMN IF NOT EXISTS perm_finance_view TINYINT(1) DEFAULT 0 COMMENT 'Ver financeiro',
  ADD COLUMN IF NOT EXISTS perm_finance_receive TINYINT(1) DEFAULT 0 COMMENT 'Dar baixa em recebimentos',
  ADD COLUMN IF NOT EXISTS perm_finance_pay TINYINT(1) DEFAULT 0 COMMENT 'Dar baixa em pagamentos',
  ADD COLUMN IF NOT EXISTS perm_cash_view TINYINT(1) DEFAULT 0 COMMENT 'Ver caixa',
  ADD COLUMN IF NOT EXISTS perm_cash_move TINYINT(1) DEFAULT 0 COMMENT 'Movimentar caixa',
  
  -- Permissões de Produção
  ADD COLUMN IF NOT EXISTS perm_production_view TINYINT(1) DEFAULT 0 COMMENT 'Ver produção',
  ADD COLUMN IF NOT EXISTS perm_production_manage TINYINT(1) DEFAULT 0 COMMENT 'Gerenciar produção',
  
  -- Permissões de Compras
  ADD COLUMN IF NOT EXISTS perm_purchases_view TINYINT(1) DEFAULT 0 COMMENT 'Ver compras',
  ADD COLUMN IF NOT EXISTS perm_purchases_create TINYINT(1) DEFAULT 0 COMMENT 'Criar compras',
  
  -- Permissões de Relatórios
  ADD COLUMN IF NOT EXISTS perm_reports_sales TINYINT(1) DEFAULT 1 COMMENT 'Ver relatórios de vendas',
  ADD COLUMN IF NOT EXISTS perm_reports_finance TINYINT(1) DEFAULT 0 COMMENT 'Ver relatórios financeiros',
  ADD COLUMN IF NOT EXISTS perm_dre_view TINYINT(1) DEFAULT 0 COMMENT 'Ver DRE',
  
  -- Permissões de Cadastros
  ADD COLUMN IF NOT EXISTS perm_items_manage TINYINT(1) DEFAULT 0 COMMENT 'Gerenciar produtos/serviços',
  ADD COLUMN IF NOT EXISTS perm_suppliers_manage TINYINT(1) DEFAULT 0 COMMENT 'Gerenciar fornecedores',
  ADD COLUMN IF NOT EXISTS perm_users_manage TINYINT(1) DEFAULT 0 COMMENT 'Gerenciar usuários';

-- Atualiza permissões baseadas no role atual
-- Admin: tudo liberado
UPDATE users SET 
  perm_os_view = 1, perm_os_create = 1, perm_os_edit = 1, perm_os_delete = 1,
  perm_clients_view = 1, perm_clients_create = 1, perm_clients_edit = 1, perm_clients_delete = 1,
  perm_finance_view = 1, perm_finance_receive = 1, perm_finance_pay = 1,
  perm_cash_view = 1, perm_cash_move = 1,
  perm_production_view = 1, perm_production_manage = 1,
  perm_purchases_view = 1, perm_purchases_create = 1,
  perm_reports_sales = 1, perm_reports_finance = 1, perm_dre_view = 1,
  perm_items_manage = 1, perm_suppliers_manage = 1, perm_users_manage = 1
WHERE role = 'admin';

-- Vendas: acesso a vendas, clientes e relatórios de vendas
UPDATE users SET 
  perm_os_view = 1, perm_os_create = 1, perm_os_edit = 0, perm_os_delete = 0,
  perm_clients_view = 1, perm_clients_create = 1, perm_clients_edit = 1, perm_clients_delete = 0,
  perm_finance_view = 0, perm_finance_receive = 0, perm_finance_pay = 0,
  perm_cash_view = 0, perm_cash_move = 0,
  perm_production_view = 0, perm_production_manage = 0,
  perm_purchases_view = 0, perm_purchases_create = 0,
  perm_reports_sales = 1, perm_reports_finance = 0, perm_dre_view = 0,
  perm_items_manage = 0, perm_suppliers_manage = 0, perm_users_manage = 0
WHERE role = 'vendas';

-- Financeiro: acesso a financeiro, caixa, compras, relatórios financeiros
UPDATE users SET 
  perm_os_view = 1, perm_os_create = 0, perm_os_edit = 0, perm_os_delete = 0,
  perm_clients_view = 1, perm_clients_create = 0, perm_clients_edit = 0, perm_clients_delete = 0,
  perm_finance_view = 1, perm_finance_receive = 1, perm_finance_pay = 1,
  perm_cash_view = 1, perm_cash_move = 1,
  perm_production_view = 1, perm_production_manage = 1,
  perm_purchases_view = 1, perm_purchases_create = 1,
  perm_reports_sales = 1, perm_reports_finance = 1, perm_dre_view = 1,
  perm_items_manage = 1, perm_suppliers_manage = 1, perm_users_manage = 0
WHERE role = 'financeiro';
