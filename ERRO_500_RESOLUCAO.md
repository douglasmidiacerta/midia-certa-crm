# 🚨 Resolução de Erro HTTP 500 - Mídia Certa CRM

## 📊 Status Atual

- ✅ Banco de dados funcionando
- ❌ Erro HTTP 500 ao acessar http://graficamidiacerta.com.br/

---

## 🔍 PASSO 1: EXECUTAR DIAGNÓSTICO

Criei um script de diagnóstico completo que vai identificar o problema exato.

### **Como Usar:**

1. **Faça upload do arquivo** `tmp_rovodev_diagnose.php` na **RAIZ** do seu site (onde está o `index.php`)
   - Via cPanel → Gerenciador de Arquivos → Upload

2. **Acesse no navegador:**
   ```
   http://graficamidiacerta.com.br/tmp_rovodev_diagnose.php
   ```

3. **O script vai verificar:**
   - ✅ Versão PHP compatível
   - ✅ Extensões PHP necessárias
   - ✅ Estrutura de arquivos
   - ✅ Permissões de pastas
   - ✅ Arquivo config.php
   - ✅ Conexão com banco de dados
   - ✅ Sessões PHP
   - ✅ Erros no index.php
   - ✅ Configuração .htaccess
   - ✅ Informações do servidor

4. **Anote os erros** (itens com ❌) e me envie

5. **⚠️ DEPOIS DE USAR, DELETE O ARQUIVO** por segurança!

---

## 🔧 CAUSAS COMUNS DE ERRO 500

### **1. Erro no config.php** (MAIS COMUM)

**Problema:** Credenciais incorretas ou sintaxe errada

**Como verificar:**
- O diagnóstico vai mostrar se o arquivo carrega corretamente
- Testa conexão com banco de dados

**Possíveis erros:**
```php
// ❌ ERRADO - falta vírgula
'db' => [
  'host' => 'localhost'
  'name' => 'banco',  // FALTOU vírgula acima
]

// ❌ ERRADO - aspas mal fechadas
'name' => 'meu_banco",  // Mistura aspas simples e duplas

// ❌ ERRADO - nome do banco sem prefixo cPanel
'name' => 'sistema',  // Faltou prefixo: gmidiace_sistema

// ✅ CORRETO
'db' => [
  'host' => 'localhost',
  'name' => 'gmidiace_sistema',
  'user' => 'gmidiace_user',
  'pass' => '@3x51ELC00',
  'charset' => 'utf8mb4',
],
```

---

### **2. Arquivo config.local.php no Servidor**

**Problema:** Arquivo de desenvolvimento local ainda presente

**Solução:**
```bash
# Via cPanel → Gerenciador de Arquivos
# DELETE o arquivo: config/config.local.php
```

O sistema prioriza `config.local.php` sobre `config.php`!

---

### **3. Versão PHP Incompatível**

**Problema:** PHP abaixo de 7.4

**Solução:**
1. cPanel → **"Select PHP Version"** (ou "MultiPHP Manager")
2. Selecionar **PHP 7.4** ou **8.0** (recomendado 8.1)
3. Aplicar alterações

---

### **4. Extensões PHP Faltando**

**Problema:** PDO ou PDO_MySQL não instalados

**Solução:**
1. cPanel → **"Select PHP Version"**
2. Aba **"Extensions"**
3. Marcar:
   - ✅ pdo
   - ✅ pdo_mysql
   - ✅ mbstring
   - ✅ json
4. Salvar

---

### **5. Erro de Sintaxe em Algum Arquivo**

**Problema:** Algum arquivo PHP com erro de código

**Como identificar:**
- O script de diagnóstico vai mostrar o erro EXATO
- Linha e arquivo do problema

**Solução:**
- Corrigir o arquivo indicado
- Ou restaurar do backup

---

### **6. Permissões Incorretas**

**Problema:** Arquivos sem permissão de leitura

**Solução:**
```
Pastas:   755 (rwxr-xr-x)
Arquivos: 644 (rw-r--r--)
uploads/: 755 ou 777
```

**Como ajustar no cPanel:**
1. Gerenciador de Arquivos
2. Selecionar pasta raiz
3. Botão direito → Change Permissions
4. Configurar 755
5. Marcar "Recurse into subdirectories"

---

### **7. Arquivo .htaccess com Erro**

**Problema:** Regras incorretas no .htaccess

**Locais para verificar:**
- `.htaccess` na raiz (pode nem existir)
- `site/.htaccess` (só afeta o /site/)

**Solução temporária:**
1. Renomear `.htaccess` para `.htaccess.bak`
2. Testar se site funciona
3. Se funcionar, o problema está no .htaccess

**⚠️ NOTA:** O sistema NÃO requer .htaccess na raiz para funcionar!

---

### **8. Erro de Memória PHP**

**Problema:** Limite de memória muito baixo

**Solução:**
1. cPanel → **"MultiPHP INI Editor"**
2. Aumentar:
```ini
memory_limit = 256M
```

---

### **9. Erro no Banco de Dados**

**Problema:** Tabelas não importadas ou corrompidas

**Como verificar:**
1. phpMyAdmin → Selecionar banco
2. Verificar se existem tabelas (deve ter ~18)
3. Se não tem ou tem poucas, reimportar `database/schema.sql`

**Tabelas essenciais:**
- users
- clients
- suppliers
- items
- os
- os_lines
- ar_titles
- ap_titles

---

### **10. Session Path sem Permissão**

**Problema:** PHP não consegue salvar sessões

**Solução:**
1. cPanel → "MultiPHP INI Editor"
2. Verificar/ajustar:
```ini
session.save_path = "/tmp"
```

Ou criar pasta específica:
```bash
# Via Terminal SSH ou File Manager
mkdir -p ~/tmp/sessions
chmod 777 ~/tmp/sessions
```

Depois em php.ini:
```ini
session.save_path = "/home/seu_usuario/tmp/sessions"
```

---

## 📋 CHECKLIST DE VERIFICAÇÃO RÁPIDA

Antes de executar o diagnóstico, verifique:

- [ ] Upload dos arquivos concluído (todos os arquivos estão lá?)
- [ ] `config/config.php` existe e foi editado com credenciais corretas
- [ ] `config/config.local.php` NÃO existe no servidor
- [ ] Nome do banco está com prefixo cPanel correto (ex: `gmidiace_sistema`)
- [ ] Nome do usuário MySQL está com prefixo correto (ex: `gmidiace_user`)
- [ ] Senha do MySQL está correta (sem espaços extras)
- [ ] `base_path` está configurado (vazio `''` para raiz)
- [ ] Schema do banco foi importado no phpMyAdmin
- [ ] Permissões da pasta `uploads/` estão em 755
- [ ] Versão PHP é 7.4+ (verificar no cPanel)

---

## 🔍 COMO VER LOGS DE ERRO DO CPANEL

Os logs vão mostrar EXATAMENTE qual é o erro:

### **Método 1: Via cPanel**

1. **cPanel → Metrics → Errors**
2. Clicar em **"Error Log"** ou **"Logs de Erro"**
3. Procurar pelos erros mais recentes (últimas linhas)
4. Copiar as mensagens de erro

### **Método 2: Via Gerenciador de Arquivos**

1. Gerenciador de Arquivos
2. Procurar arquivo: `error_log` (pode estar na raiz ou em subpastas)
3. Visualizar/baixar o arquivo
4. Procurar erros recentes

### **Método 3: Ativar Display de Erros Temporariamente**

**APENAS PARA DIAGNÓSTICO:**

Adicionar no INÍCIO do `index.php`:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ... resto do código
```

Depois de resolver, **REMOVER estas linhas**!

---

## 🎯 PRÓXIMOS PASSOS

1. **Execute o diagnóstico:**
   ```
   http://graficamidiacerta.com.br/tmp_rovodev_diagnose.php
   ```

2. **Me envie o resultado:**
   - Tire prints dos itens com ❌
   - Ou copie as mensagens de erro

3. **Verifique os logs:**
   - cPanel → Errors
   - Me envie as últimas linhas de erro

4. **Informações úteis para me enviar:**
   - Versão PHP (aparece no diagnóstico)
   - Mensagem de erro exata do log
   - Se conseguiu acessar o diagnóstico ou também deu erro 500

---

## 💡 DICA: TESTE COM ARQUIVO SIMPLES

Se nem o diagnóstico abrir, teste com arquivo super simples:

**Criar arquivo:** `test.php`
```php
<?php
phpinfo();
?>
```

**Acessar:**
```
http://graficamidiacerta.com.br/test.php
```

**Se funcionar:**
- ✅ PHP está funcionando
- ❌ O problema está no código do sistema

**Se NÃO funcionar:**
- ❌ Problema é configuração do servidor/cPanel
- 👉 Contatar suporte da hospedagem

---

## 🆘 SE NADA FUNCIONAR

Entre em contato com o suporte da hospedagem e forneça:

1. Mensagem de erro exata dos logs
2. Versão PHP configurada
3. Informação de que é um sistema PHP puro (sem frameworks)
4. Se o erro acontece em TODOS os arquivos PHP ou só no sistema

---

## ✅ APÓS RESOLVER

1. ✅ DELETE `tmp_rovodev_diagnose.php`
2. ✅ DELETE `test.php` (se criou)
3. ✅ Remover `error_reporting` do `index.php` (se adicionou)
4. ✅ Alterar senha do usuário admin
5. ✅ Fazer backup do banco de dados

---

**Aguardando resultado do diagnóstico para continuar! 🚀**
