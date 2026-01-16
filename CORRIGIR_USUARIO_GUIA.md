# 🔧 Guia: Corrigir Perfil do Usuário

## 🎯 Problema Identificado

O usuário existe no banco, mas **não tem perfil (role) definido** ou está incorreto.

**Resultado:** Menu lateral aparece vazio, sem conteúdo.

---

## ✅ Solução: Definir o Usuário como ADMIN

### **PASSO 1: Abrir phpMyAdmin**

1. **cPanel → phpMyAdmin**
2. Selecionar o banco `gmidiace_sistema` (lado esquerdo)

---

### **PASSO 2: Ver os Usuários Atuais**

1. Clicar na tabela **`users`** (lado esquerdo)
2. Clicar na aba **"Browse"** (ou "Pesquisar")
3. Verificar os usuários existentes

**Você deve ver algo como:**

| id | name | email | role | active |
|----|------|-------|------|--------|
| 1  | João | joao@email.com | (vazio) | 1 |

**Problema:** O campo `role` está **vazio** ou **NULL**!

---

### **PASSO 3: Corrigir o Usuário**

#### **Método A: Via Interface (Mais Fácil)**

1. Na tabela `users`, clique no **ícone de editar** (lápis) ao lado do usuário
2. No campo **`role`**, digite: `admin`
3. No campo **`active`**, coloque: `1`
4. Clicar em **"Go"** ou **"Executar"**

#### **Método B: Via SQL (Mais Rápido)**

1. phpMyAdmin → Aba **"SQL"**
2. Cole este código:

```sql
-- Tornar o primeiro usuário ADMIN
UPDATE users 
SET role = 'admin', 
    active = 1 
WHERE id = 1;
```

3. Clicar em **"Executar"** ou **"Go"**

**OU, se souber o email:**

```sql
-- Tornar usuário específico ADMIN
UPDATE users 
SET role = 'admin', 
    active = 1 
WHERE email = 'seu_email@dominio.com';
```

---

### **PASSO 4: Confirmar a Alteração**

1. Voltar na aba **"Browse"** da tabela `users`
2. Verificar se o campo `role` agora mostra: **`admin`**
3. Verificar se o campo `active` está: **`1`**

---

### **PASSO 5: Fazer LOGIN Novamente**

1. **Faça LOGOUT** do sistema (ou feche o navegador)
2. Limpe o cache: `Ctrl + Shift + R`
3. **Faça LOGIN** novamente
4. ✅ **Agora deve aparecer TODO o conteúdo do Dashboard!**

---

## 📊 Perfis Disponíveis no Sistema

O sistema tem 3 perfis (roles):

| Perfil | Valor no Banco | Permissões |
|--------|----------------|------------|
| **Administrador** | `admin` | ✅ Acesso total (Dashboard, Vendas, Financeiro, Compras, Configurações, Marketing) |
| **Vendas** | `vendas` | ✅ Dashboard, Vendas, O.S, Clientes, Produtos |
| **Financeiro** | `financeiro` | ✅ Dashboard, Vendas, Financeiro, Compras, Clientes |

---

## 🔍 Verificar Permissões

Para saber qual perfil tem quais permissões, veja no arquivo `config/auth.php`:

```php
function can_admin() {
  return (current_user()['role'] ?? '') === 'admin';
}

function can_sales() {
  $role = current_user()['role'] ?? '';
  return in_array($role, ['admin','vendas','financeiro']);
}

function can_finance() {
  $role = current_user()['role'] ?? '';
  return in_array($role, ['admin','financeiro']);
}
```

---

## 🚨 Possíveis Problemas e Soluções

### **1. Usuário não existe**

Se não tem nenhum usuário na tabela `users`:

```sql
INSERT INTO users (name, email, role, password_hash, active, created_at)
VALUES (
  'Administrador',
  'admin@midiacerta.com',
  'admin',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  1,
  NOW()
);
```

**Credenciais:**
- Email: `admin@midiacerta.com`
- Senha: `password`

⚠️ **TROCAR A SENHA APÓS LOGIN!**

---

### **2. Campo `role` não existe**

Se aparecer erro que a coluna `role` não existe:

```sql
-- Adicionar coluna role
ALTER TABLE users 
ADD COLUMN role VARCHAR(50) DEFAULT 'vendas' AFTER email;
```

---

### **3. Ainda não aparece conteúdo**

Verifique:

1. ✅ Campo `role` = `'admin'` (com aspas)
2. ✅ Campo `active` = `1`
3. ✅ Fez logout e login novamente
4. ✅ Limpou cache do navegador (`Ctrl + Shift + R`)

Se ainda não funcionar:

```sql
-- Ver dados do usuário logado
SELECT id, name, email, role, active, created_at 
FROM users 
WHERE email = 'seu_email@dominio.com';
```

Copie o resultado e me envie.

---

### **4. Erro de sessão**

Se após alterar o `role` ainda não funciona:

1. **Limpar sessões antigas:**
   - Feche TODAS as abas do navegador
   - Abra uma aba anônima/privativa
   - Acesse o sistema novamente

2. **Ou via phpMyAdmin:**
```sql
-- Limpar todas as sessões (força todos a fazer login novamente)
TRUNCATE TABLE sessions;
```
(Só funciona se houver tabela `sessions`)

---

## ✅ Checklist de Verificação

Antes de testar novamente:

- [ ] Usuário tem `role = 'admin'` no banco
- [ ] Usuário tem `active = 1` no banco
- [ ] Fez logout do sistema
- [ ] Limpou cache do navegador
- [ ] Fez login novamente
- [ ] Arquivo `partials/layout_bottom.php` está correto (com `</main>`)

---

## 🎉 Após Resolver

1. ✅ Dashboard deve mostrar:
   - Gráficos de vendas
   - Métricas principais
   - Top clientes
   - Estatísticas

2. ✅ Menu lateral deve mostrar:
   - Dashboard
   - Vendas (O.S, Nova venda, Relatórios)
   - Cadastros (Clientes, Produtos, Fornecedores)
   - Produção
   - Financeiro
   - Compras
   - Marketing
   - Administração

---

## 🆘 Se Ainda Não Funcionar

Me envie estas informações:

1. **Dados do usuário (phpMyAdmin):**
```sql
SELECT id, name, email, role, active 
FROM users 
WHERE id = 1;
```

2. **Screenshot** do que aparece na tela

3. **HTML da página** (Ctrl+U ou botão direito → Ver código fonte)

---

**Criado:** Janeiro 2026  
**Sistema:** Mídia Certa CRM v3.8+
