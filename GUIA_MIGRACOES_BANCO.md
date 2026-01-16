# 🗄️ Guia de Migrações de Banco de Dados

**Sistema automático de migrações do Mídia Certa CRM**

---

## 🎯 Como Funciona

O sistema **executa automaticamente** todas as migrações quando:
- ✅ Alguém acessa o sistema
- ✅ Você faz deploy de novos arquivos
- ✅ O sistema conecta ao banco de dados

**Não precisa fazer nada manualmente!** 🎉

---

## 📝 Como Adicionar Alterações no Banco

### Método 1: Criar Arquivo SQL (Recomendado) ⭐

1. **Crie um arquivo na pasta `database/updates/`**

   Exemplo: `database/updates/add_campo_telefone.sql`

2. **Escreva seu SQL normalmente:**

```sql
-- Adicionar campo telefone na tabela clientes
ALTER TABLE clientes 
ADD COLUMN telefone_adicional VARCHAR(20) NULL 
AFTER telefone;

-- Adicionar índice
CREATE INDEX idx_telefone_adicional ON clientes(telefone_adicional);
```

3. **Faça o deploy (commit + push)**

```powershell
.\deploy_rapido.ps1 "Adicionado campo telefone adicional"
```

4. **Pronto!** A migração será executada automaticamente! ✅

---

### Método 2: Adicionar Coluna via Código

Se preferir usar código PHP, edite `config/migrate.php`:

```php
// Na função mc_migrate(), adicione:
mc_ensure_column($pdo, 'nome_tabela', 'nome_coluna', "VARCHAR(100) NULL");
```

**Exemplo:**
```php
mc_ensure_column($pdo, 'clientes', 'telefone_adicional', "VARCHAR(20) NULL");
```

---

## 🏷️ Nomenclatura de Arquivos

Use nomes descritivos para os arquivos SQL:

### ✅ Bons exemplos:
- `add_campo_email_clientes.sql`
- `create_table_vendedores.sql`
- `fix_status_pedidos.sql`
- `upgrade_v4_1.sql`
- `add_indice_data_criacao.sql`

### ❌ Evite:
- `fix.sql` (muito genérico)
- `update.sql` (não diz o que faz)
- `teste.sql` (não é descritivo)

💡 **Dica:** Use prefixos para organizar:
- `add_` - Adicionar campos/tabelas
- `create_` - Criar novas tabelas
- `fix_` - Correções
- `upgrade_` - Atualizações de versão
- `seed_` - Dados iniciais

---

## 📊 Controle de Migrações

### Tabela `migrations`

O sistema mantém um registro de todas as migrações executadas:

```sql
SELECT * FROM migrations ORDER BY executed_at DESC;
```

**Colunas:**
- `migration_file` - Nome do arquivo executado
- `executed_at` - Data/hora da execução
- `status` - `success` ou `failed`
- `error_message` - Mensagem de erro (se houver)

---

## 🔍 Como Verificar se uma Migração Foi Executada

### Via SQL:
```sql
SELECT * FROM migrations WHERE migration_file = 'add_campo_telefone.sql';
```

### Via PHP:
```php
if (mc_migration_executed($pdo, 'add_campo_telefone.sql')) {
    echo "Migração já executada!";
}
```

---

## ⚠️ Importante: Migrações São Executadas Uma Única Vez

- ✅ Cada arquivo SQL é executado **apenas uma vez**
- ✅ O sistema verifica automaticamente se já foi executado
- ✅ Arquivos já executados são **ignorados**
- ✅ Não há risco de executar a mesma migração 2x

**Isso significa:**
- Você pode fazer deploy quantas vezes quiser
- Apenas as migrações novas serão executadas
- Não precisa se preocupar com duplicação

---

## 🔄 Ordem de Execução

As migrações são executadas em **ordem alfabética**:

```
add_campo_email.sql          ← Executada primeiro
add_campo_telefone.sql       ← Executada depois
create_table_vendedores.sql  ← Executada por último
```

💡 **Dica:** Use prefixos numéricos se precisar controlar a ordem:

```
001_create_table_vendedores.sql
002_add_campo_email.sql
003_add_indice_email.sql
```

---

## 🚨 Tratamento de Erros

### O que acontece se uma migração falhar?

1. ❌ A migração é marcada como `failed`
2. 📝 O erro é registrado na tabela `migrations`
3. 📋 O erro aparece no log do PHP (`error_log`)
4. ⏭️ O sistema **continua** executando as próximas migrações

### Como ver migrações com erro:

```sql
SELECT * FROM migrations WHERE status = 'failed';
```

### Como reexecutar uma migração com erro:

```sql
-- 1. Corrija o arquivo SQL primeiro
-- 2. Apague o registro da migração
DELETE FROM migrations WHERE migration_file = 'nome_do_arquivo.sql';
-- 3. Faça deploy novamente ou recarregue a página
```

---

## 📚 Exemplos Práticos

### Exemplo 1: Adicionar Campo

**Arquivo:** `database/updates/add_data_nascimento_clientes.sql`

```sql
-- Adicionar campo data de nascimento
ALTER TABLE clientes 
ADD COLUMN data_nascimento DATE NULL 
AFTER email;
```

### Exemplo 2: Criar Nova Tabela

**Arquivo:** `database/updates/create_table_vendedores.sql`

```sql
-- Criar tabela de vendedores
CREATE TABLE IF NOT EXISTS vendedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    comissao_percentual DECIMAL(5,2) DEFAULT 0.00,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Exemplo 3: Múltiplas Alterações

**Arquivo:** `database/updates/upgrade_v4_2.sql`

```sql
-- Atualização v4.2 - Melhorias no sistema de pedidos

-- 1. Adicionar campo de observações
ALTER TABLE pedidos 
ADD COLUMN observacoes TEXT NULL;

-- 2. Adicionar campo de desconto
ALTER TABLE pedidos 
ADD COLUMN desconto_percentual DECIMAL(5,2) DEFAULT 0.00;

-- 3. Criar índice para busca rápida
CREATE INDEX idx_data_pedido ON pedidos(data_pedido);

-- 4. Atualizar status existentes
UPDATE pedidos SET status = 'pendente' WHERE status IS NULL;
```

### Exemplo 4: Inserir Dados Iniciais

**Arquivo:** `database/updates/seed_categorias_produtos.sql`

```sql
-- Inserir categorias padrão de produtos
INSERT IGNORE INTO categorias_produtos (id, nome, descricao) VALUES
(1, 'Impressão Digital', 'Impressões em alta qualidade'),
(2, 'Offset', 'Impressão offset para grandes volumes'),
(3, 'Acabamento', 'Serviços de acabamento gráfico'),
(4, 'Design', 'Serviços de design e criação');
```

---

## 🛠️ Ferramentas Úteis

### Ver Histórico de Migrações

**Arquivo:** `pages/migrations_history.php` (criar se necessário)

```php
<?php
require_once '../config/auth.php';
require_once '../config/db.php';

$migrations = $pdo->query("
    SELECT * FROM migrations 
    ORDER BY executed_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Histórico de Migrações</h2>
<table>
    <thead>
        <tr>
            <th>Arquivo</th>
            <th>Status</th>
            <th>Executado em</th>
            <th>Erro</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($migrations as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['migration_file']) ?></td>
            <td><?= $m['status'] === 'success' ? '✅' : '❌' ?></td>
            <td><?= $m['executed_at'] ?></td>
            <td><?= htmlspecialchars($m['error_message'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
```

---

## 🔒 Segurança e Boas Práticas

### ✅ SEMPRE faça:

1. **Teste localmente primeiro**
   - Teste a migração no seu banco local
   - Verifique se não há erros

2. **Use `IF NOT EXISTS` e `IF EXISTS`**
   ```sql
   CREATE TABLE IF NOT EXISTS nova_tabela (...);
   ALTER TABLE tabela DROP COLUMN IF EXISTS coluna_antiga;
   ```

3. **Faça backup antes de alterações grandes**
   - No cPanel: PhpMyAdmin → Exportar
   - Guarde o backup antes do deploy

4. **Use transações para múltiplas operações**
   ```sql
   START TRANSACTION;
   -- suas alterações aqui
   COMMIT;
   ```

### ❌ NUNCA faça:

1. ❌ **DROP TABLE** sem `IF EXISTS`
2. ❌ Alterar dados de produção sem backup
3. ❌ Executar SQL não testado em produção
4. ❌ Modificar a tabela `migrations` manualmente (exceto para reexecutar)

---

## 🎯 Fluxo de Trabalho Ideal

```
1. 📝 Criar arquivo SQL em database/updates/
   ↓
2. 🧪 Testar localmente
   ↓
3. ✅ Verificar se funciona
   ↓
4. 💾 Commit e Push
   ↓
5. 🚀 Deploy automático
   ↓
6. ✨ Migração executada automaticamente!
```

---

## 📊 Resumo Rápido

| Ação | Como Fazer |
|------|------------|
| Adicionar campo | Criar arquivo SQL em `database/updates/` |
| Criar tabela | Criar arquivo SQL em `database/updates/` |
| Ver histórico | `SELECT * FROM migrations` |
| Reexecutar migração | Deletar registro da tabela `migrations` |
| Verificar erros | `SELECT * FROM migrations WHERE status='failed'` |

---

## 🆘 Problemas Comuns

### Problema: Migração não foi executada

**Causa:** Arquivo não está em `database/updates/` ou não tem extensão `.sql`

**Solução:**
- Verifique se o arquivo está na pasta correta
- Verifique a extensão do arquivo (.sql)

### Problema: Migração executou 2 vezes

**Causa:** Isso não deve acontecer! Sistema impede execução dupla.

**Solução:**
- Verifique a tabela `migrations`
- Se realmente aconteceu, reporte o bug

### Problema: Erro "Table doesn't exist"

**Causa:** Tentando alterar tabela que não existe

**Solução:**
- Use `IF EXISTS` nas queries
- Verifique se a tabela existe antes

---

## 🎉 Conclusão

Com este sistema, você pode:

✅ Adicionar campos/tabelas automaticamente  
✅ Manter histórico de todas as alterações  
✅ Evitar execução duplicada de migrações  
✅ Fazer deploy sem preocupação com o banco  
✅ Trabalhar em equipe sem conflitos de schema  

**Basta criar o arquivo SQL e fazer deploy!** 🚀

---

**Criado em:** 16/01/2026  
**Versão:** 1.0  
**Sistema:** Mídia Certa CRM v3.8
