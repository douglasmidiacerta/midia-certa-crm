# 📋 Passo a Passo: Migrações de Banco de Dados

**Guia prático e detalhado para fazer alterações no banco de dados automaticamente**

---

## 🎯 O Que São Migrações?

Migrações são **alterações no banco de dados** que acontecem **automaticamente** quando você faz deploy.

**Exemplos:**
- Adicionar um campo em uma tabela
- Criar uma nova tabela
- Adicionar índices
- Atualizar dados existentes

---

## 🚀 Passo 1: Entender o Sistema

### Como Funciona?

```
Você cria arquivo SQL → Faz deploy → Sistema executa automaticamente!
```

**Quando executa?**
- ✅ Quando alguém acessa o sistema
- ✅ Quando o sistema conecta ao banco
- ✅ Automaticamente após o deploy

**Executa várias vezes?**
- ❌ NÃO! Cada migração executa **apenas uma vez**
- ✅ O sistema guarda registro do que já foi executado

---

## 📝 Passo 2: Criar Sua Primeira Migração

### Exemplo Prático: Adicionar Campo WhatsApp

#### 2.1 - Criar o Arquivo

1. Abra a pasta do projeto
2. Navegue até: `database/updates/`
3. Crie um novo arquivo: `add_whatsapp_clientes.sql`

**Estrutura de pastas:**
```
seu-projeto/
  ├── database/
  │   ├── updates/
  │   │   ├── add_whatsapp_clientes.sql  ← CRIAR AQUI
  │   │   ├── create_migrations_table.sql
  │   │   └── ... outros arquivos
```

---

#### 2.2 - Escrever o SQL

Abra o arquivo `add_whatsapp_clientes.sql` e escreva:

```sql
-- Adicionar campo WhatsApp na tabela de clientes
-- Data: 16/01/2026
-- Autor: Douglas

-- Adicionar o campo
ALTER TABLE clientes 
ADD COLUMN whatsapp VARCHAR(20) NULL 
AFTER telefone;

-- Adicionar índice para busca rápida (opcional)
CREATE INDEX idx_whatsapp ON clientes(whatsapp);
```

💡 **Dicas:**
- Use comentários para explicar o que está fazendo
- O campo vai aparecer depois do campo `telefone`
- `VARCHAR(20)` = texto com até 20 caracteres
- `NULL` = campo opcional (pode ficar vazio)

---

#### 2.3 - Salvar o Arquivo

Salve o arquivo (`Ctrl + S`).

✅ Pronto! Migração criada!

---

## 🧪 Passo 3: Testar Localmente (Opcional mas Recomendado)

Antes de fazer deploy, teste no seu banco local:

### 3.1 - Abrir PhpMyAdmin Local

1. Abra o XAMPP/WAMP
2. Acesse: `http://localhost/phpmyadmin`
3. Selecione seu banco de dados

### 3.2 - Executar o SQL Manualmente

1. Clique na aba **SQL**
2. Cole o conteúdo do seu arquivo
3. Clique em **Executar**

### 3.3 - Verificar se Funcionou

1. Clique na tabela `clientes`
2. Veja se o campo `whatsapp` apareceu

✅ Se apareceu, está funcionando!

---

## 🚀 Passo 4: Fazer Deploy

### 4.1 - Verificar Arquivos Alterados

Abra o PowerShell na pasta do projeto:

```powershell
cd "C:\Users\Pc - Acer\Documents\midia-certa-crm-v1\midia-certa-crm-v3_8"

git status
```

Você deve ver:
```
modified:   config/migrate.php
new file:   database/updates/add_whatsapp_clientes.sql
new file:   database/updates/create_migrations_table.sql
```

---

### 4.2 - Fazer Deploy Rápido

**Opção A: Script Rápido** (Recomendado)

```powershell
.\deploy_rapido.ps1 "Adicionado campo WhatsApp nos clientes"
```

**Opção B: Comandos Manuais**

```powershell
git add .
git commit -m "Adicionado campo WhatsApp nos clientes"
git push origin main
```

---

### 4.3 - Acompanhar o Deploy

1. Acesse: https://github.com/douglasmidiacerta/midia-certa-crm/actions
2. Veja o deploy rodando
3. Aguarde o ✅ verde (1-2 minutos)

---

## ✅ Passo 5: Verificar se Funcionou

### 5.1 - Verificar na Tabela migrations

Acesse o PhpMyAdmin do cPanel:

```sql
SELECT * FROM migrations 
WHERE migration_file = 'add_whatsapp_clientes.sql';
```

**Deve aparecer:**
```
migration_file              | executed_at         | status
add_whatsapp_clientes.sql   | 2026-01-16 15:30:00 | success
```

✅ Se aparecer, a migração foi executada!

---

### 5.2 - Verificar na Tabela clientes

```sql
SHOW COLUMNS FROM clientes;
```

Procure o campo `whatsapp` na lista.

✅ Se aparecer, está funcionando perfeitamente!

---

### 5.3 - Testar no Sistema

1. Acesse: https://graficamidiacerta.com.br
2. Vá em Clientes → Editar Cliente
3. Veja se o campo WhatsApp aparece (se tiver no formulário)

---

## 📚 Mais Exemplos Práticos

### Exemplo 2: Criar Nova Tabela

**Arquivo:** `database/updates/create_table_categorias.sql`

```sql
-- Criar tabela de categorias de produtos
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Deploy:**
```powershell
.\deploy_rapido.ps1 "Criada tabela de categorias"
```

---

### Exemplo 3: Adicionar Múltiplos Campos

**Arquivo:** `database/updates/add_campos_endereco.sql`

```sql
-- Adicionar campos de endereço completo
ALTER TABLE clientes 
ADD COLUMN complemento VARCHAR(100) NULL AFTER numero,
ADD COLUMN ponto_referencia VARCHAR(200) NULL AFTER complemento;

-- Adicionar índice de CEP para busca rápida
CREATE INDEX idx_cep ON clientes(cep);
```

**Deploy:**
```powershell
.\deploy_rapido.ps1 "Adicionados campos de endereço"
```

---

### Exemplo 4: Atualizar Dados Existentes

**Arquivo:** `database/updates/update_status_padrao.sql`

```sql
-- Atualizar clientes sem status para 'ativo'
UPDATE clientes 
SET status = 'ativo' 
WHERE status IS NULL OR status = '';

-- Tornar campo obrigatório
ALTER TABLE clientes 
MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ativo';
```

**Deploy:**
```powershell
.\deploy_rapido.ps1 "Atualizado status padrão dos clientes"
```

---

### Exemplo 5: Inserir Dados Iniciais

**Arquivo:** `database/updates/seed_tipos_pagamento.sql`

```sql
-- Inserir tipos de pagamento padrão
INSERT IGNORE INTO tipos_pagamento (id, nome, descricao) VALUES
(1, 'Dinheiro', 'Pagamento em dinheiro'),
(2, 'Cartão de Crédito', 'Pagamento com cartão de crédito'),
(3, 'Cartão de Débito', 'Pagamento com cartão de débito'),
(4, 'PIX', 'Pagamento via PIX'),
(5, 'Boleto', 'Pagamento via boleto bancário');
```

**Deploy:**
```powershell
.\deploy_rapido.ps1 "Adicionados tipos de pagamento padrão"
```

---

## 🔍 Passo 6: Resolver Problemas

### Problema 1: Migração Não Foi Executada

**Verificar:**

1. O arquivo está em `database/updates/`?
2. O arquivo tem extensão `.sql`?
3. O deploy foi concluído com sucesso?

**Solução:**

```powershell
# Ver arquivos na pasta
ls database/updates/

# Se o arquivo estiver lá, fazer deploy novamente
.\deploy_rapido.ps1 "Reexecutar migrações"
```

---

### Problema 2: Erro na Migração

**Verificar erros:**

```sql
SELECT * FROM migrations WHERE status = 'failed';
```

**Como corrigir:**

1. Veja qual foi o erro na coluna `error_message`
2. Corrija o arquivo SQL
3. Delete o registro da migração:

```sql
DELETE FROM migrations 
WHERE migration_file = 'seu_arquivo.sql';
```

4. Faça deploy novamente

---

### Problema 3: Campo Já Existe

**Erro:**
```
Duplicate column name 'whatsapp'
```

**Causa:** O campo já existe no banco

**Solução:** Use `IF NOT EXISTS` (MySQL 8.0+) ou verifique antes:

```sql
-- Método seguro
ALTER TABLE clientes 
ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) NULL;

-- Ou use o sistema do migrate.php:
-- mc_ensure_column($pdo, 'clientes', 'whatsapp', "VARCHAR(20) NULL");
```

---

### Problema 4: Tabela Não Existe

**Erro:**
```
Table 'clientes' doesn't exist
```

**Causa:** Tentando alterar tabela que não existe

**Solução:** Sempre use verificações:

```sql
-- Para criar tabelas
CREATE TABLE IF NOT EXISTS nova_tabela (...);

-- Para alterar
-- Primeiro verifique se existe no código PHP
```

---

## 📐 Boas Práticas

### ✅ SEMPRE:

1. **Use nomes descritivos** para arquivos
   ```
   ✅ add_campo_whatsapp_clientes.sql
   ❌ update.sql
   ```

2. **Adicione comentários** explicando o que faz
   ```sql
   -- Adicionar campo WhatsApp para contato
   -- Requisito: Issue #123
   ```

3. **Teste localmente** antes do deploy
   - Execute no seu PhpMyAdmin local
   - Verifique se não há erros

4. **Use `IF NOT EXISTS` e `IF EXISTS`**
   ```sql
   CREATE TABLE IF NOT EXISTS ...
   DROP TABLE IF EXISTS ...
   ALTER TABLE ... DROP COLUMN IF EXISTS ...
   ```

5. **Faça backup** antes de alterações grandes
   - No cPanel: PhpMyAdmin → Exportar

---

### ❌ NUNCA:

1. ❌ **Não delete** arquivos de migração já executados
   - Isso bagunça o histórico
   
2. ❌ **Não modifique** migrações já executadas
   - Crie uma nova migração para corrigir

3. ❌ **Não use DROP TABLE** sem backup
   - Você pode perder dados!

4. ❌ **Não teste SQL desconhecido** em produção
   - Sempre teste local primeiro

---

## 🎯 Checklist de Migração

Antes de fazer deploy, verifique:

- [ ] Arquivo criado em `database/updates/`
- [ ] Nome descritivo e com extensão `.sql`
- [ ] SQL comentado e explicado
- [ ] Testado localmente (se possível)
- [ ] Usa `IF NOT EXISTS` quando aplicável
- [ ] Não vai deletar dados importantes
- [ ] Backup feito (se for alteração grande)

---

## 📊 Fluxo Completo Visual

```
┌─────────────────────────────────────────────────┐
│  1. Criar arquivo SQL em database/updates/      │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  2. Escrever SQL (ALTER TABLE, CREATE, etc)     │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  3. Testar localmente (opcional)                │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  4. Fazer deploy (.\deploy_rapido.ps1)          │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  5. GitHub Actions faz upload para servidor     │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  6. Usuário acessa o sistema                    │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  7. Sistema executa mc_migrate()                │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│  8. Sistema verifica: migração já foi feita?    │
└─────────────────┬───────────────────────────────┘
                  │
        ┌─────────┴─────────┐
        │                   │
        ▼                   ▼
      ┌───┐               ┌───┐
      │NÃO│               │SIM│
      └─┬─┘               └─┬─┘
        │                   │
        ▼                   ▼
┌─────────────┐     ┌─────────────┐
│ Executa SQL │     │ Pula (skip) │
└──────┬──────┘     └─────────────┘
       │
       ▼
┌─────────────────────────────────────────────────┐
│  9. Registra na tabela migrations               │
└─────────────────────────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────────────────┐
│  10. ✅ Pronto! Campo/Tabela criado(a)!         │
└─────────────────────────────────────────────────┘
```

---

## 💡 Dicas Extras

### Organização por Prefixo

Use prefixos numéricos para controlar ordem:

```
database/updates/
  ├── 001_create_base_tables.sql
  ├── 002_add_user_fields.sql
  ├── 003_add_indexes.sql
  ├── 004_seed_initial_data.sql
```

### Organização por Versão

```
database/updates/
  ├── upgrade_v4_0.sql
  ├── upgrade_v4_1.sql
  ├── upgrade_v4_2.sql
```

### Organização por Funcionalidade

```
database/updates/
  ├── clientes_add_whatsapp.sql
  ├── clientes_add_observacoes.sql
  ├── pedidos_add_desconto.sql
  ├── produtos_create_categorias.sql
```

---

## 🆘 Comandos Úteis

### Ver histórico de migrações:
```sql
SELECT * FROM migrations ORDER BY executed_at DESC;
```

### Ver migrações pendentes (não existe ainda, mas seria útil):
```sql
-- Todas as migrações executadas
SELECT migration_file FROM migrations WHERE status = 'success';
```

### Ver migrações com erro:
```sql
SELECT * FROM migrations WHERE status = 'failed';
```

### Reexecutar migração:
```sql
-- 1. Corrija o arquivo SQL primeiro
-- 2. Delete o registro
DELETE FROM migrations WHERE migration_file = 'seu_arquivo.sql';
-- 3. Recarregue a página ou faça novo deploy
```

---

## 🎉 Resumo Final

**Para fazer alteração no banco:**

1. ✅ Criar arquivo SQL em `database/updates/`
2. ✅ Escrever SQL
3. ✅ Fazer deploy: `.\deploy_rapido.ps1 "Sua mensagem"`
4. ✅ Pronto! Automático!

**Não precisa:**
- ❌ Acessar PhpMyAdmin do servidor
- ❌ Executar SQL manualmente
- ❌ Se preocupar com execução duplicada

**É tudo automático!** 🚀

---

## 📞 Precisa de Ajuda?

1. 📖 Leia o `GUIA_MIGRACOES_BANCO.md` (documentação completa)
2. 👀 Veja exemplos em `database/updates/exemplo_adicionar_campo.sql`
3. 🔍 Consulte a tabela `migrations` para ver histórico

---

**Criado em:** 16/01/2026  
**Versão:** 1.0  
**Sistema:** Mídia Certa CRM v3.8  
**Autor:** Rovo Dev + Douglas
