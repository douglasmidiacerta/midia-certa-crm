# 📋 Passo a Passo: Deploy Automático no cPanel

**Guia simplificado e direto ao ponto para configurar o deploy automático.**

---

## 🎯 Escolha seu Método

- **[Método 1: GitHub Actions](#método-1-github-actions-recomendado)** - Deploy automático ao fazer `git push` ⭐
- **[Método 2: Script Local](#método-2-script-local)** - Deploy manual com um comando

---

## Método 1: GitHub Actions (Recomendado)

### ✅ Passo 1: Verificar Credenciais FTP do cPanel

1. Acesse o **cPanel**
2. Vá em **Contas FTP** (ou **FTP Accounts**)
3. Anote suas credenciais:
   - **Servidor FTP**: geralmente `ftp.seudominio.com` ou o IP do servidor
   - **Usuário FTP**: `usuario@seudominio.com` (ou apenas `usuario`)
   - **Senha FTP**: sua senha (se não souber, crie uma nova conta FTP)
   - **Porta**: `21` (FTP normal) ou `990` (FTPS seguro)
   - **Diretório**: `/public_html/` (ou `/public_html/crm/` se estiver em subpasta)

💡 **Dica:** Se possível, use **FTPS** (porta 990) para maior segurança.

---

### ✅ Passo 2: Configurar Secrets no GitHub

1. Acesse seu repositório no **GitHub**
2. Clique em **Settings** (Configurações)
3. No menu lateral, clique em **Secrets and variables** → **Actions**
4. Clique no botão **New repository secret**
5. Adicione cada secret abaixo:

#### Secret 1: FTP_SERVER
- **Name:** `FTP_SERVER`
- **Secret:** `ftp.seudominio.com` (ou IP do servidor)
- Clique em **Add secret**

#### Secret 2: FTP_USERNAME
- **Name:** `FTP_USERNAME`
- **Secret:** `seu_usuario_ftp` (ex: `usuario@seudominio.com`)
- Clique em **Add secret**

#### Secret 3: FTP_PASSWORD
- **Name:** `FTP_PASSWORD`
- **Secret:** `sua_senha_ftp`
- Clique em **Add secret**

#### Secret 4: FTP_SERVER_DIR
- **Name:** `FTP_SERVER_DIR`
- **Secret:** `/public_html/` (ou caminho completo como `/public_html/crm/`)
- Clique em **Add secret**

#### Secret 5: FTP_PORT (Opcional)
- **Name:** `FTP_PORT`
- **Secret:** `21` (para FTP) ou `990` (para FTPS)
- Clique em **Add secret**

#### Secret 6: FTP_PROTOCOL (Opcional)
- **Name:** `FTP_PROTOCOL`
- **Secret:** `ftp` (normal) ou `ftps` (seguro)
- Clique em **Add secret**

✅ **Você deve ter pelo menos 4 secrets configurados** (os 4 primeiros são obrigatórios)

---

### ✅ Passo 3: Fazer o Primeiro Deploy

1. No seu computador, faça qualquer alteração no código (ou apenas commit):
   ```bash
   git add .
   git commit -m "Configurando deploy automático"
   git push origin main
   ```

2. Vá no **GitHub** → aba **Actions**
3. Você verá o deploy sendo executado em tempo real! ⏳
4. Aguarde até aparecer o ✅ verde (sucesso) ou ❌ vermelho (erro)

---

### ✅ Passo 4: Verificar se Funcionou

1. Acesse seu site: `https://seudominio.com`
2. Verifique se as alterações foram aplicadas
3. Se algo der errado, veja os logs na aba **Actions** do GitHub

---

### 🎉 Pronto! Agora todo push faz deploy automático!

De agora em diante:
```bash
git add .
git commit -m "Minha alteração"
git push origin main
```

O deploy acontece automaticamente! 🚀

---

## Método 2: Script Local

Use este método se não quiser usar GitHub Actions ou preferir controlar manualmente quando fazer deploy.

---

### ✅ Passo 1: Instalar Dependências

#### 🐧 Linux (Ubuntu/Debian)
```bash
sudo apt-get update
sudo apt-get install lftp php-cli
```

#### 🍎 macOS
```bash
brew install lftp php
```

#### 🪟 Windows
1. **Instalar WinSCP:**
   - Baixe: https://winscp.net/eng/download.php
   - Instale normalmente
   - Adicione ao PATH do Windows (ou anote o caminho de instalação)

2. **Instalar PHP (opcional, para validação):**
   - Baixe: https://windows.php.net/download/
   - Extraia em `C:\php`
   - Adicione `C:\php` ao PATH do Windows

---

### ✅ Passo 2: Preparar o Script

#### 🐧 Linux/macOS
```bash
# Dar permissão de execução
chmod +x deploy.sh
```

#### 🪟 Windows
```powershell
# Permitir execução de scripts PowerShell (execute como Administrador)
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
```

---

### ✅ Passo 3: Executar o Primeiro Deploy

#### 🐧 Linux/macOS
```bash
./deploy.sh
```

#### 🪟 Windows
```powershell
.\deploy.ps1
```

---

### ✅ Passo 4: Configurar na Primeira Execução

O script vai pedir as seguintes informações:

```
🌐 Servidor FTP (ex: ftp.seudominio.com): 
👤 Usuário FTP: 
🔑 Senha FTP: 
📁 Diretório no servidor (ex: /public_html/): 
🔌 Porta FTP (21 para FTP, 990 para FTPS): 
🔒 Usar FTPS? (s/n): 
```

Preencha com suas credenciais do cPanel (mesmas do Passo 1 do Método 1).

💾 **As configurações são salvas** em `.deploy-config` ou `.deploy-config.json` (não vai pro Git).

---

### ✅ Passo 5: Aguardar o Upload

O script vai:
1. ✅ Validar todos os arquivos PHP
2. 🧹 Limpar arquivos temporários
3. 📤 Fazer upload apenas dos arquivos necessários
4. 🎉 Confirmar sucesso

---

### ✅ Passo 6: Deploys Futuros

Nas próximas vezes, é só executar o comando novamente:

#### 🐧 Linux/macOS
```bash
./deploy.sh
```

#### 🪟 Windows
```powershell
.\deploy.ps1
```

Pronto! Deploy feito em poucos segundos! 🚀

---

## 🔍 Resolução de Problemas Comuns

### ❌ Erro: "Connection refused" ou "Could not connect"

**Problema:** Não consegue conectar no servidor FTP

**Solução:**
1. Verifique se as credenciais estão corretas
2. Teste com FileZilla primeiro para garantir que funciona
3. Verifique se a porta está correta (21 ou 990)
4. Confira se o firewall do hosting não está bloqueando

---

### ❌ Erro: "550 Permission denied"

**Problema:** Sem permissão para escrever no diretório

**Solução:**
1. No cPanel, verifique as permissões da pasta (deve ser 755 ou 775)
2. Certifique-se de que o usuário FTP tem acesso à pasta
3. Verifique se o caminho do diretório está correto

---

### ❌ Erro: "PHP syntax error detected"

**Problema:** Há erro de sintaxe em algum arquivo PHP

**Solução:**
1. Veja qual arquivo tem erro no log
2. Corrija o erro localmente
3. Teste o arquivo: `php -l nome_do_arquivo.php`
4. Tente o deploy novamente

---

### ❌ GitHub Actions não executa

**Problema:** O workflow não roda automaticamente

**Solução:**
1. Vá em **Settings** → **Actions** → **General**
2. Certifique-se de que "Allow all actions" está selecionado
3. Verifique se o nome do branch está correto no arquivo `.github/workflows/deploy-cpanel.yml` (main ou master)

---

### ❌ Arquivos importantes sumiram do servidor

**Problema:** `config.local.php` ou `uploads/` foram apagados

**Solução:**
1. Isso NÃO deve acontecer (está configurado para não apagar)
2. Restaure o backup do cPanel
3. Verifique se não alterou `dangerous-clean-slate` para `true`
4. Os arquivos `uploads/` e `config.local.php` são sempre preservados

---

### ❌ Deploy demora muito tempo

**Problema:** Upload leva muito tempo

**Solução:**
1. Na primeira vez é normal (envia tudo)
2. Nas próximas vezes só envia o que mudou
3. Se continuar lento, verifique sua internet
4. Considere aumentar o limite de timeout se necessário

---

## 📌 Arquivos Importantes

### ✅ Arquivos que SÃO enviados no deploy:
- ✅ Todos os arquivos `.php`
- ✅ Arquivos `.css` e `.js`
- ✅ Imagens do projeto (não de `uploads/`)
- ✅ Arquivos de configuração (exceto `config.local.php`)

### ❌ Arquivos que NÃO são enviados:
- ❌ `.git/` - Arquivos do Git
- ❌ `uploads/` - Já estão no servidor
- ❌ `config/config.local.php` - Configuração local
- ❌ `tmp_rovodev_*` - Arquivos temporários
- ❌ `*.log` - Logs
- ❌ `*.md` - Documentação (opcional)
- ❌ `.vscode/`, `.idea/` - Configurações de IDE

---

## 🔒 Segurança: O que NÃO Fazer

### ⚠️ NUNCA faça isso:

1. ❌ **NUNCA** commite senhas no Git
   - Use GitHub Secrets (Método 1)
   - Use `.deploy-config` local (Método 2)

2. ❌ **NUNCA** compartilhe o arquivo `.deploy-config`
   - Ele contém sua senha FTP
   - Está no `.gitignore` para proteger você

3. ❌ **NUNCA** use `dangerous-clean-slate: true`
   - Isso apaga TUDO do servidor antes do deploy
   - Você vai perder uploads e configurações

4. ❌ **NUNCA** ignore erros de sintaxe PHP
   - O deploy vai validar antes de enviar
   - Se tiver erro, corrija antes de fazer deploy

---

## ✅ Checklist Final

Antes do primeiro deploy, verifique:

- [ ] Credenciais FTP estão corretas
- [ ] Secrets configurados no GitHub (se usar Actions)
- [ ] Script tem permissão de execução (se usar script local)
- [ ] `.gitignore` está protegendo `.deploy-config*`
- [ ] `config/config.local.php` NÃO está no Git
- [ ] Testei localmente antes de fazer deploy
- [ ] Fiz backup do servidor (pelo cPanel)

Após o primeiro deploy:

- [ ] Site está funcionando normalmente
- [ ] Não houve erros no processo
- [ ] Arquivos importantes não foram apagados
- [ ] Deploy automático está ativo (se usar Actions)

---

## 🎯 Resumo Rápido

### GitHub Actions (Automático):
1. Configure 4-6 secrets no GitHub
2. Faça `git push origin main`
3. Pronto! Deploy automático! ✅

### Script Local (Manual):
1. Instale `lftp` (Linux/macOS) ou `WinSCP` (Windows)
2. Execute `./deploy.sh` ou `.\deploy.ps1`
3. Configure na primeira vez
4. Pronto! Deploy com um comando! ✅

---

## 📞 Precisa de Ajuda?

1. ✅ Releia a seção [Resolução de Problemas](#-resolução-de-problemas-comuns)
2. 📖 Consulte o `GUIA_DEPLOY_AUTOMATICO.md` para mais detalhes
3. 📋 Veja os logs no GitHub Actions ou no terminal
4. 🔍 Teste conexão FTP com FileZilla antes
5. 📧 Entre em contato com o suporte do hosting

---

## 🎉 Parabéns!

Você configurou deploy automático! Agora é só fazer `git push` (Método 1) ou rodar o script (Método 2).

**Nunca mais precise fazer upload manual de arquivos!** 🚀

---

**Documentação completa:** Veja `GUIA_DEPLOY_AUTOMATICO.md` para informações detalhadas.

**Criado em:** 16/01/2026  
**Versão:** 1.0
