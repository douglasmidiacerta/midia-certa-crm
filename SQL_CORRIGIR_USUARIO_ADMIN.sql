-- ============================================================
-- CORRIGIR USUÁRIO ADMIN - Mídia Certa CRM
-- ============================================================
-- Execute no phpMyAdmin (aba SQL)
-- Isso vai tornar o primeiro usuário um ADMINISTRADOR
-- ============================================================

-- OPÇÃO 1: Tornar o primeiro usuário ADMIN
-- (Use esta se você criou apenas 1 usuário)
UPDATE users 
SET role = 'admin', 
    active = 1 
WHERE id = 1;

-- ============================================================

-- OPÇÃO 2: Tornar um usuário específico ADMIN pelo email
-- (Substitua 'seu_email@exemplo.com' pelo email do usuário)
UPDATE users 
SET role = 'admin', 
    active = 1 
WHERE email = 'seu_email@exemplo.com';

-- ============================================================

-- OPÇÃO 3: Ver todos os usuários (para identificar qual corrigir)
-- Execute esta query primeiro para ver os usuários:
SELECT 
  id,
  name,
  email,
  role,
  active,
  created_at
FROM users
ORDER BY id;

-- ============================================================
-- PERFIS DISPONÍVEIS NO SISTEMA:
-- ============================================================
-- 'admin'      → Acesso total (Administrador Master)
-- 'vendas'     → Apenas área de vendas e O.S
-- 'financeiro' → Vendas + Financeiro + Compras
-- ============================================================

-- APÓS EXECUTAR:
-- 1. Faça LOGOUT do sistema
-- 2. Faça LOGIN novamente
-- 3. Agora deve aparecer todo o conteúdo!
-- ============================================================
