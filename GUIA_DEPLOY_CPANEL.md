# 📋 Guia Completo de Deploy no cPanel - Mídia Certa CRM/ERP

## 🎯 Visão Geral do Sistema

Este é um **CRM/ERP para Gráfica** completo desenvolvido em PHP puro (sem frameworks), com as seguintes funcionalidades:

- ✅ Gestão de Clientes e Fornecedores
- ✅ Ordens de Serviço (OS) com Kanban
- ✅ Financeiro (Contas a Receber/Pagar, DRE, Caixa)
- ✅ Compras e Estoque
- ✅ Portal do Cliente (clientes podem acompanhar pedidos)
- ✅ Site Institucional com Carrossel
- ✅ Sistema de Aprovação de Arte
- ✅ Rastreamento Público de Pedidos
- ✅ Upload de Arquivos
- ✅ Relatórios Financeiros e Gerenciais

---

## 📦 Requisitos do Servidor

### ✅ Requisitos Mínimos

| Requisito | Versão/Valor |
|-----------|--------------|
| **PHP** | 7.4 ou superior (recomendado 8.0+) |
| **MySQL/MariaDB** | 5.7 ou superior |
| **Extensões PHP** | PDO, PDO_MySQL, mbstring, json, session, gd (para manipulação de imagens) |
| **Espaço em Disco** | Mínimo 500MB (depende dos uploads) |
| **Memória PHP** | 128MB (recomendado 256MB) |
| **Upload Max** | 15MB (configurado no sistema) |
| **Permissões** | Pasta `uploads/` deve ter permissão de escrita (755 ou 777) |

---

## 🚀 Passo a Passo Completo

### **PASSO 1: Preparar os Arquivos**

#### 1.1 Fazer Upload dos Arquivos

1. **Compactar o projeto localmente** (excluindo `config/config.local.php` se existir) "Feito"
2. **Acessar o cPanel → Gerenciador de Arquivos**
3. Navegar até `public_html` (ou subpasta se preferir, ex: `public_html/sistema`)
4. **Upload do arquivo .zip**
5. **Extrair o arquivo**

#### 1.2 Estrutura de Pastas Esperada

```
public_html/
├── app.php
├── index.php
├── client_portal.php
├── public_tracking.php
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── config/
│   ├── config.php ← IMPORTANTE: Editar este arquivo
│   ├── db.php
│   └── ...
├── database/
│   ├── schema.sql
│   └── updates/
├── pages/
├── site/
│   └── .htaccess
└── uploads/ ← IMPORTANTE: Permissão 755
    ├── carousel/
    └── os_*/
```

---

### **PASSO 2: Configurar o Banco de Dados**

#### 2.1 Criar Banco de Dados no cPanel

1. **cPanel → MySQL® Databases**
2. **Criar novo banco de dados:**
   - Nome sugerido: `gmidiace_sistema` (ou qualquer nome)
   - Anotar: `nome_completo_do_banco` (geralmente cpanel_nomebanco)

3. **Criar usuário MySQL:**
   - Nome sugerido: `gmidiace_user`
   - Senha forte (anotar!)
   - Anotar: `nome_completo_usuario` (geralmente cpanel_usuario)

4. **Adicionar usuário ao banco:**
   - Marcar **TODOS OS PRIVILÉGIOS**
   - Clicar em "Fazer alterações"

#### 2.2 Importar Schema do Banco

1. **cPanel → phpMyAdmin**
2. Selecionar o banco criado (lado esquerdo)
3. Aba **"Importar"**
4. **Escolher arquivo:** `database/schema.sql`
5. Clicar em **"Executar"**
6. ✅ Verificar se as tabelas foram criadas (deve ter ~18 tabelas)

#### 2.3 (OPCIONAL) Instalar Carousel para Site

Se quiser usar o site institucional com carrossel:

1. No phpMyAdmin, selecione o banco
2. Aba **"SQL"**
3. Copiar e colar o conteúdo de `INSTALAR_CAROUSEL_PHPMYADMIN.sql`
4. Executar

---

### **PASSO 3: Configurar o Sistema**

#### 3.1 Editar `config/config.php`

**Via Gerenciador de Arquivos do cPanel:**

1. Navegar até `config/config.php`
2. Botão direito → **"Editar"** ou **"Code Editor"**
3. **MODIFICAR as seguintes linhas:**

```php
<?php
return [
  'db' => [
    'host' => 'localhost',           // Normalmente localhost
    'name' => 'SEU_BANCO_AQUI',      // Nome completo do banco (ex: cpanel_sistema)
    'user' => 'SEU_USUARIO_AQUI',    // Nome completo do usuário (ex: cpanel_user)
    'pass' => 'SUA_SENHA_AQUI',      // Senha do MySQL
    'charset' => 'utf8mb4',
  ],
  // ⚠️ AJUSTE CONFORME SEU CAMINHO:
  'base_path' => '',                  // Vazio se na raiz do domínio
                                      // OU '/nome-pasta' se em subpasta
  'app_name' => 'Mídia Certa',
  'upload_dir' => __DIR__ . '/../uploads',
  'upload_max_mb' => 15,
];
```

**Exemplos de `base_path`:**

- Se o sistema está em: `https://seusite.com/` → `'base_path' => ''`
- Se está em: `https://seusite.com/sistema/` → `'base_path' => '/sistema'`
- Se está em: `https://seusite.com/crm/` → `'base_path' => '/crm'`

4. **Salvar alterações** (Ctrl+S ou botão Salvar)

#### 3.2 ⚠️ IMPORTANTE: Remover `config.local.php`

Se existe o arquivo `config/config.local.php` no servidor, **DELETE-O**!

- Este arquivo é apenas para desenvolvimento local
- No servidor, o sistema deve usar `config.php`

---

### **PASSO 4: Configurar Permissões**

#### 4.1 Pasta de Uploads

A pasta `uploads/` precisa ter permissão de **escrita**:

1. **Gerenciador de Arquivos → uploads/**
2. Botão direito → **"Permissões"** (ou "Change Permissions")
3. Configurar: **755** (rwxr-xr-x)
   - ✅ Marcar "Recurse into subdirectories"
4. Aplicar

**Se 755 não funcionar, tente 777 (menos seguro, mas às vezes necessário):**
- 777 = Leitura, escrita e execução para todos

#### 4.2 Verificar Outras Permissões

- Pasta raiz: **755**
- Arquivos PHP: **644**
- Pastas em geral: **755**

---

### **PASSO 5: Primeiro Acesso e Configuração Inicial**

#### 5.1 Acessar o Sistema

1. Abrir navegador
2. Acessar: `https://seudominio.com/` (ou `https://seudominio.com/sistema/`)

#### 5.2 Verificação Automática

O sistema possui **migração automática** (`config/migrate.php`):
- Na primeira execução, ele tentará criar/atualizar tabelas automaticamente
- Se houver erro, verifique as credenciais do banco em `config.php`

#### 5.3 Criar Primeiro Usuário

**Se não houver usuários no banco**, o sistema redirecionará para `/install.php`.

⚠️ **NOTA:** Não vi o arquivo `install.php` no projeto. Se ele não existir, você precisará criar o primeiro usuário manualmente:

**Via phpMyAdmin:**

```sql
INSERT INTO users (name, email, role, password_hash, active, created_at)
VALUES (
  'Administrador',
  'admin@midiacerta.com',
  'admin',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
  1,
  NOW()
);
```

**Credenciais padrão:**
- Email: `admin@midiacerta.com`
- Senha: `password`

⚠️ **ALTERE A SENHA IMEDIATAMENTE APÓS O LOGIN!**

---

### **PASSO 6: Configurações Pós-Instalação**

#### 6.1 Verificar Funcionamento

Após login, teste:

1. ✅ Dashboard carrega corretamente
2. ✅ Menu lateral funciona
3. ✅ Cadastro de clientes
4. ✅ Criar uma OS de teste
5. ✅ Upload de arquivo em uma OS

#### 6.2 Configurar PHP (se necessário)

Se houver problemas com upload ou sessões:

**cPanel → "Select PHP Version" (ou "MultiPHP INI Editor"):**

```ini
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
session.gc_maxlifetime = 7200
```

#### 6.3 Configurar Email (Opcional)

Se o sistema enviar emails, configure SMTP no cPanel:
- **cPanel → Email Accounts**
- Criar conta para o sistema (ex: `sistema@seudominio.com`)

---

### **PASSO 7: Configurar Módulos Opcionais**

#### 7.1 Site Institucional

O sistema inclui um site público em `/site/`:

**Páginas disponíveis:**
- `site/index.php` - Home com carrossel
- `site/produtos.php` - Catálogo de produtos
- `site/artigos.php` - Blog/artigos
- `site/contato.php` - Formulário de contato

**Configuração:**
1. Importar `INSTALAR_CAROUSEL_PHPMYADMIN.sql` (se não fez no Passo 2.3)
2. Adicionar slides em: Sistema → Marketing → Carrossel
3. Acessar: `https://seudominio.com/site/`

#### 7.2 Portal do Cliente

Permite clientes acompanharem pedidos:

**Acesso:** `https://seudominio.com/client_portal.php`

**Como funciona:**
1. Cadastrar cliente no sistema
2. Cliente faz registro no portal
3. Administrador aprova acesso
4. Cliente consegue ver suas OS

#### 7.3 Rastreamento Público

Link público para cliente acompanhar pedido (sem login):

**URL:** `https://seudominio.com/public_tracking.php?token=XXXXXX`

- Token gerado automaticamente ao criar OS
- Pode ser enviado por email/WhatsApp para cliente

---

## 🔒 Segurança

### ✅ Checklist de Segurança

- [ ] Alterar senha padrão do admin
- [ ] Remover `config.local.php` do servidor
- [ ] Configurar permissões corretas (755/644)
- [ ] Usar senhas fortes no MySQL
- [ ] Ativar SSL/HTTPS no cPanel (Let's Encrypt gratuito)
- [ ] Fazer backup regular do banco de dados
- [ ] Manter PHP atualizado
- [ ] Revisar usuários cadastrados periodicamente

### 🚨 Arquivos Sensíveis

**Nunca compartilhar/expor:**
- `config/config.php` - Contém credenciais do banco
- `/uploads/` - Arquivos dos clientes
- Logs de erro do servidor

---

## 🔧 Troubleshooting (Resolução de Problemas)

### ❌ Erro: "Tela Branca" (White Screen)

**Causa:** Erro de PHP não exibido

**Solução:**
1. Ativar exibição de erros temporariamente
2. Adicionar no início do `index.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```
3. Recarregar página e ver erro
4. **Remover após identificar problema**

### ❌ Erro: "Connection refused" ou "Access denied"

**Causa:** Credenciais incorretas em `config.php`

**Solução:**
1. Verificar `config.php`:
   - Nome completo do banco (com prefixo cPanel)
   - Nome completo do usuário (com prefixo cPanel)
   - Senha correta
2. Testar credenciais no phpMyAdmin

### ❌ Erro: "Table doesn't exist"

**Causa:** Schema não foi importado corretamente

**Solução:**
1. phpMyAdmin → Selecionar banco
2. Verificar se existem tabelas (users, clients, os, etc.)
3. Se não, reimportar `database/schema.sql`
4. Verificar mensagens de erro na importação

### ❌ Upload não funciona

**Causa:** Permissões incorretas ou limite PHP

**Solução:**
1. Verificar permissões da pasta `uploads/` (755 ou 777)
2. Verificar `upload_max_filesize` no PHP
3. Criar subpasta manualmente se necessário (ex: `uploads/os_1/`)

### ❌ CSS/JS não carregam

**Causa:** `base_path` incorreto

**Solução:**
1. Abrir `config/config.php`
2. Ajustar `base_path` conforme localização:
   - Raiz: `''` (vazio)
   - Subpasta: `'/nome-pasta'`
3. Limpar cache do navegador (Ctrl+Shift+R)

### ❌ Sessão expira muito rápido

**Causa:** Configuração PHP

**Solução:**
1. cPanel → MultiPHP INI Editor
2. Aumentar `session.gc_maxlifetime` (ex: 7200 = 2 horas)

---

## 📊 Estrutura do Banco de Dados

O sistema cria automaticamente 18+ tabelas:

| Tabela | Função |
|--------|--------|
| `users` | Usuários do sistema |
| `clients` | Clientes da gráfica |
| `suppliers` | Fornecedores |
| `items` | Produtos/serviços |
| `categories` | Categorias de produtos |
| `os` | Ordens de Serviço |
| `os_lines` | Itens das OS |
| `os_files` | Arquivos anexados às OS |
| `purchases` | Ordens de Compra |
| `ar_titles` | Contas a Receber |
| `ap_titles` | Contas a Pagar |
| `cash_accounts` | Contas Bancárias |
| `cash_moves` | Movimentações de Caixa |
| `carousel_slides` | Slides do site |
| `client_portal_users` | Usuários do portal |
| `os_tracking_tokens` | Tokens de rastreamento |
| `audit_logs` | Logs de auditoria |

---

## 🔄 Atualizações e Manutenção

### Fazer Backup

**Recomendação: Backup semanal**

**Via cPanel:**
1. **Backup Wizard** → Download completo
2. OU **phpMyAdmin** → Exportar banco

**Via script automático:**
```bash
# Criar backup do banco
mysqldump -u usuario -p nome_banco > backup_$(date +%Y%m%d).sql
```

### Aplicar Atualizações do Sistema

Se houver novas versões:

1. Fazer backup completo
2. Fazer upload dos novos arquivos
3. Executar scripts em `database/updates/` (se houver)
4. Testar funcionalidades

---

## 📞 Suporte e Recursos

### Arquivos Importantes

- `database/schema.sql` - Schema completo do banco
- `database/updates/` - Scripts de atualização
- `config/migrate.php` - Migração automática
- `INSTALAR_CAROUSEL_PHPMYADMIN.sql` - Instalação do carrossel
- `LIMPAR_DUPLICADOS_CAROUSEL.sql` - Limpeza de dados

### Tecnologias Utilizadas

- **Backend:** PHP 7.4+ (puro, sem frameworks)
- **Banco de Dados:** MySQL/MariaDB
- **Frontend:** Bootstrap 5.3, JavaScript vanilla
- **Arquitetura:** MVC simplificado

---

## ✅ Checklist Final de Deploy

Antes de considerar o deploy concluído:

- [ ] Banco de dados criado e schema importado
- [ ] `config/config.php` editado com credenciais corretas
- [ ] `base_path` configurado corretamente
- [ ] `config.local.php` removido (se existir)
- [ ] Permissões da pasta `uploads/` configuradas (755)
- [ ] Primeiro usuário admin criado
- [ ] Login funcionando corretamente
- [ ] Dashboard carrega sem erros
- [ ] Teste de criação de cliente/OS realizado
- [ ] Upload de arquivo testado
- [ ] SSL/HTTPS ativado (recomendado)
- [ ] Backup inicial criado
- [ ] Senha do admin alterada
- [ ] Portal do cliente testado (se usar)
- [ ] Site institucional configurado (se usar)

---

## 🎉 Deploy Concluído!

Parabéns! Seu sistema **Mídia Certa CRM/ERP** está pronto para uso.

**Próximos passos:**
1. Cadastrar usuários da equipe
2. Importar base de clientes (se houver)
3. Configurar produtos/serviços
4. Treinar equipe no uso do sistema
5. Configurar rotina de backup

---

**Desenvolvido para:** Gráfica Mídia Certa  
**Versão:** 3.8+  
**Última atualização deste guia:** Janeiro 2026
