# 🚀 Guia de Deploy Automático - Mídia Certa CRM/ERP

Este guia explica como configurar o deploy automático para o cPanel, eliminando a necessidade de upload manual de arquivos.

## 📋 Índice

1. [Deploy Automático via GitHub Actions](#1-deploy-automático-via-github-actions-recomendado)
2. [Deploy Manual via Script](#2-deploy-manual-via-script)
3. [Git Version Control do cPanel](#3-git-version-control-do-cpanel)
4. [Troubleshooting](#troubleshooting)

---

## 1. Deploy Automático via GitHub Actions (⭐ Recomendado)

### Vantagens
- ✅ **Totalmente automático** - Deploy ao fazer `git push`
- ✅ **Validação automática** - Verifica sintaxe PHP antes do deploy
- ✅ **Histórico completo** - Todos os deploys registrados no GitHub
- ✅ **Seguro** - Não expõe credenciais no código
- ✅ **Gratuito** - Até 2000 minutos/mês no GitHub

### Pré-requisitos
- Repositório no GitHub (público ou privado)
- Acesso FTP/FTPS ao cPanel

### Passo 1: Configurar Secrets no GitHub

1. Acesse seu repositório no GitHub
2. Vá em **Settings** → **Secrets and variables** → **Actions**
3. Clique em **New repository secret**
4. Adicione os seguintes secrets:

| Nome | Descrição | Exemplo |
|------|-----------|---------|
| `FTP_SERVER` | Endereço do servidor FTP | `ftp.seudominio.com` |
| `FTP_USERNAME` | Usuário FTP do cPanel | `usuario@seudominio.com` |
| `FTP_PASSWORD` | Senha FTP | `sua_senha_segura` |
| `FTP_PORT` | Porta FTP (opcional) | `21` (FTP) ou `990` (FTPS) |
| `FTP_PROTOCOL` | Protocolo (opcional) | `ftp` ou `ftps` |
| `FTP_SERVER_DIR` | Diretório no servidor | `/public_html/` ou `/public_html/crm/` |

### Passo 2: Ativar o Workflow

O arquivo `.github/workflows/deploy-cpanel.yml` já está configurado!

### Passo 3: Fazer Deploy

Agora é só fazer push para o branch principal:

```bash
git add .
git commit -m "Minha alteração"
git push origin main
```

O deploy será executado automaticamente! 🎉

### Monitorar o Deploy

1. Acesse a aba **Actions** no GitHub
2. Veja o progresso do deploy em tempo real
3. Se houver erros, eles aparecerão aqui

### Deploy Manual via GitHub Actions

Se quiser fazer deploy sem fazer push:

1. Vá em **Actions** → **Deploy para cPanel**
2. Clique em **Run workflow**
3. Escolha o branch e clique em **Run workflow**

---

## 2. Deploy Manual via Script

Se preferir não usar GitHub Actions, use os scripts de deploy manual.

### 🐧 Linux/macOS - deploy.sh

#### Instalação de Dependências

```bash
# Ubuntu/Debian
sudo apt-get install lftp php-cli

# macOS (Homebrew)
brew install lftp php
```

#### Primeira Execução

```bash
chmod +x deploy.sh
./deploy.sh
```

O script vai pedir:
- Servidor FTP
- Usuário e senha
- Diretório no servidor
- Porta (21 para FTP, 990 para FTPS)
- Se usar FTPS

As configurações são salvas em `.deploy-config` (não é versionado no Git).

#### Execuções Seguintes

```bash
./deploy.sh
```

O script vai:
1. ✅ Validar sintaxe de todos os arquivos PHP
2. 🧹 Limpar arquivos temporários
3. 📤 Fazer upload apenas dos arquivos necessários
4. 🎉 Confirmar sucesso

---

### 🪟 Windows - deploy.ps1

#### Instalação de Dependências

1. **WinSCP** (recomendado):
   - Baixe em: https://winscp.net/
   - Instale e adicione ao PATH

2. **PHP** (para validação):
   - Baixe em: https://www.php.net/downloads
   - Adicione ao PATH

#### Primeira Execução

```powershell
# Permitir execução de scripts (execute como Administrador)
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser

# Executar deploy
.\deploy.ps1
```

O script vai pedir as mesmas informações do Linux.

#### Execuções Seguintes

```powershell
.\deploy.ps1
```

---

## 3. Git Version Control do cPanel

Alguns hostings oferecem integração direta com Git.

### Verificar Disponibilidade

1. Acesse o cPanel
2. Procure por **"Git Version Control"** ou **"Git™ Version Control"**
3. Se disponível, você pode:
   - Clonar seu repositório direto no servidor
   - Fazer pull automático a cada push

### Configuração Básica

1. No cPanel, vá em **Git Version Control**
2. Clique em **Create**
3. Configure:
   - **Clone URL**: `https://github.com/seu-usuario/seu-repo.git`
   - **Repository Path**: caminho onde o código ficará
   - **Repository Name**: nome descritivo
4. Clique em **Create**

### Deploy Automático

1. Configure um webhook no GitHub:
   - Settings → Webhooks → Add webhook
   - Payload URL: `https://seudominio.com:2083/cpsess###/git/pull.live.php`
   - Content type: `application/json`
   - Eventos: `Just the push event`

---

## 📁 Arquivos Excluídos do Deploy

Os seguintes arquivos/pastas **NÃO** são enviados no deploy:

### Sempre Excluídos
- `.git/` e `.github/` - Arquivos do Git
- `.vscode/`, `.idea/` - Configurações de IDEs
- `node_modules/` - Dependências Node.js
- `tmp_rovodev_*` - Arquivos temporários
- `*.log`, `error_log` - Logs
- `.DS_Store`, `Thumbs.db`, `desktop.ini` - Arquivos do sistema
- `config/config.local.php` - Configuração local

### Opcionalmente Excluídos
- `*.md` - Documentação (pode remover da exclusão se desejar)
- `uploads/` - Arquivos já estão no servidor (preserva uploads existentes)
- `database/` - Scripts SQL não são necessários em produção

---

## ⚙️ Configurações Importantes

### Arquivo .gitignore

Certifique-se de que o `.gitignore` está configurado:

```gitignore
# Configuração local
config/config.local.php
.deploy-config
.deploy-config.json

# Arquivos temporários
tmp_rovodev_*

# Uploads
uploads/*
!uploads/.keep

# IDEs
.vscode/
.idea/

# Logs
*.log
error_log
```

### Arquivo .ftpignore

O `.ftpignore` controla o que é enviado no deploy. Edite conforme necessário.

---

## 🔒 Segurança

### ⚠️ IMPORTANTE

1. **Nunca commite senhas ou credenciais**
   - Use GitHub Secrets para GitHub Actions
   - Use `.deploy-config` (ignorado pelo Git) para scripts locais

2. **Proteja seus secrets**
   - `.deploy-config` deve ter permissão 600 (apenas você lê)
   - Secrets do GitHub são criptografados

3. **Use FTPS quando possível**
   - Mais seguro que FTP comum
   - Configure `FTP_PROTOCOL: ftps` no GitHub
   - Use porta 990 ou 21 com TLS

4. **Não envie config.local.php**
   - Mantenha configurações sensíveis apenas no servidor
   - Configure `config/config.local.php` manualmente no cPanel

---

## 🔍 Troubleshooting

### Deploy falha com erro de conexão FTP

**Causa**: Firewall ou credenciais incorretas

**Solução**:
1. Verifique as credenciais no cPanel
2. Teste conexão FTP com FileZilla
3. Verifique se o IP está liberado no firewall do hosting
4. Tente usar modo passivo (já configurado nos scripts)

### Erro: "550 Permission denied"

**Causa**: Sem permissão para escrever no diretório

**Solução**:
1. Verifique as permissões da pasta no cPanel
2. A pasta deve ter permissão 755 ou 775
3. Verifique se o usuário FTP tem acesso à pasta

### Deploy demora muito tempo

**Causa**: Muitos arquivos ou uploads incluídos

**Solução**:
1. Certifique-se de que `uploads/` está excluído
2. Use `dangerous-clean-slate: false` (já configurado)
3. Considere fazer upload de `uploads/` separadamente uma única vez

### Erro: "PHP syntax error detected"

**Causa**: Erro de sintaxe em algum arquivo PHP

**Solução**:
1. Veja o arquivo com erro no log do GitHub Actions
2. Corrija o erro localmente
3. Teste com `php -l arquivo.php`
4. Commit e push novamente

### GitHub Actions não executa

**Causa**: Workflow desabilitado ou branch incorreto

**Solução**:
1. Vá em **Actions** e verifique se está habilitado
2. Verifique se o branch está correto no workflow (main/master)
3. Edite `.github/workflows/deploy-cpanel.yml` se necessário

### Config.local.php sumiu do servidor

**Causa**: Deploy com `dangerous-clean-slate: true`

**Solução**:
1. Está configurado como `false` para evitar isso
2. Se aconteceu, recrie manualmente no cPanel
3. Nunca use `dangerous-clean-slate: true` em produção

---

## 📊 Comparação dos Métodos

| Método | Automação | Facilidade | Validação | Recomendado |
|--------|-----------|------------|-----------|-------------|
| GitHub Actions | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ✅ Sim |
| Script Local | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | 👍 Alternativa |
| Git cPanel | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ | ⚠️ Se disponível |
| Upload Manual | ⭐ | ⭐⭐ | ❌ | ❌ Não |

---

## 🎯 Recomendação Final

**Para desenvolvimento contínuo e equipe:**
- Use **GitHub Actions** - totalmente automático e confiável

**Para deploys pontuais ou sem GitHub:**
- Use **Scripts locais** (deploy.sh ou deploy.ps1) - rápido e simples

**Para projetos simples com suporte do hosting:**
- Use **Git cPanel** - se disponível no seu plano

---

## 📞 Suporte

Se tiver problemas:

1. ✅ Verifique a seção [Troubleshooting](#troubleshooting)
2. 📋 Verifique os logs do GitHub Actions ou do script
3. 🔍 Teste conexão FTP com FileZilla primeiro
4. 📧 Entre em contato com o suporte do hosting se for problema de permissões

---

## ✅ Checklist de Deploy

- [ ] Secrets configurados no GitHub (se usar Actions)
- [ ] `.gitignore` configurado corretamente
- [ ] `config/config.local.php` não está no Git
- [ ] Testei localmente antes do deploy
- [ ] FTPS está habilitado (se possível)
- [ ] Backup do servidor foi feito
- [ ] Primeira execução do deploy testada
- [ ] Deploy automático funcionando

---

**🎉 Pronto! Agora você tem deploy automático configurado!**

Qualquer dúvida, consulte este guia ou os comentários nos arquivos de configuração.
