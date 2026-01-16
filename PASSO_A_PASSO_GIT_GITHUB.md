# 🚀 Passo a Passo: Git e GitHub do Zero

**Guia completo para criar repositório GitHub e começar a versionar seu projeto.**

---

## 📋 Índice

1. [Instalar Git](#passo-1-instalar-git)
2. [Criar Conta no GitHub](#passo-2-criar-conta-no-github)
3. [Criar Repositório no GitHub](#passo-3-criar-repositório-no-github)
4. [Configurar Git Local](#passo-4-configurar-git-local)
5. [Enviar Código para o GitHub](#passo-5-enviar-código-para-o-github)
6. [Configurar Deploy Automático](#passo-6-configurar-deploy-automático)

---

## Passo 1: Instalar Git

### 🪟 Windows

1. Baixe o Git em: https://git-scm.com/download/win
2. Execute o instalador
3. Use as opções padrão (pode clicar "Next" em tudo)
4. Após instalar, abra o **PowerShell** ou **Git Bash**
5. Teste se funcionou:
   ```powershell
   git --version
   ```
   Deve aparecer algo como: `git version 2.43.0`

### 🐧 Linux (Ubuntu/Debian)

```bash
sudo apt-get update
sudo apt-get install git
git --version
```

### 🍎 macOS

```bash
# Se tiver Homebrew instalado:
brew install git

# Ou use o Xcode Command Line Tools:
xcode-select --install

git --version
```

✅ **Git instalado!**

---

## Passo 2: Criar Conta no GitHub

1. Acesse: https://github.com
2. Clique em **"Sign up"** (Criar conta)
3. Preencha:
   - **Email:** seu email
   - **Password:** crie uma senha forte
   - **Username:** escolha um nome de usuário
4. Resolva o puzzle de verificação
5. Clique em **"Create account"**
6. Verifique seu email e confirme a conta
7. Escolha o plano **Free** (gratuito)

✅ **Conta criada!**

---

## Passo 3: Criar Repositório no GitHub

1. Faça login no GitHub
2. Clique no **"+"** no canto superior direito
3. Selecione **"New repository"**
4. Configure o repositório:

   ```
   Repository name: midia-certa-crm
   Description: Sistema CRM/ERP Mídia Certa
   
   ⚪ Public (qualquer um pode ver)
   🔘 Private (só você e quem você autorizar) ← RECOMENDADO
   
   ☐ Add a README file (NÃO marque, já temos arquivos)
   ☐ Add .gitignore (NÃO marque, já temos)
   ☐ Choose a license (NÃO marque)
   ```

5. Clique em **"Create repository"**

✅ **Repositório criado!**

📋 **Anote a URL do repositório:** Será algo como:
```
https://github.com/seu-usuario/midia-certa-crm.git
```

---

## Passo 4: Configurar Git Local

Agora vamos configurar o Git no seu computador.

### 1️⃣ Abrir Terminal no Projeto

#### 🪟 Windows
- Abra o **PowerShell**
- Navegue até a pasta do projeto:
  ```powershell
  cd "C:\Users\Pc - Acer\Documents\midia-certa-crm-v1\midia-certa-crm-v3_8"
  ```

#### 🐧 Linux / 🍎 macOS
```bash
cd /caminho/para/seu/projeto
```

### 2️⃣ Configurar seu Nome e Email (primeira vez apenas)

```bash
git config --global user.name "Seu Nome"
git config --global user.email "seu.email@exemplo.com"
```

💡 Use o mesmo email da sua conta do GitHub.

### 3️⃣ Inicializar o Git no Projeto

```bash
# Inicializar repositório
git init

# Verificar status
git status
```

Você vai ver uma lista de arquivos "untracked" (não rastreados).

### 4️⃣ Verificar o .gitignore

O arquivo `.gitignore` já está configurado para proteger arquivos sensíveis:

```bash
# Ver conteúdo do .gitignore (Windows PowerShell)
Get-Content .gitignore

# Ver conteúdo do .gitignore (Linux/macOS/Git Bash)
cat .gitignore
```

✅ **Git configurado localmente!**

---

## Passo 5: Enviar Código para o GitHub

### 1️⃣ Adicionar Todos os Arquivos

```bash
# Adicionar todos os arquivos (respeitando o .gitignore)
git add .

# Ver o que foi adicionado
git status
```

Você verá os arquivos em verde (prontos para commit).

### 2️⃣ Fazer o Primeiro Commit

```bash
git commit -m "Primeiro commit - Sistema CRM Mídia Certa"
```

💡 **Commit** é como uma "foto" do seu projeto naquele momento.

### 3️⃣ Renomear Branch para 'main'

```bash
git branch -M main
```

💡 O GitHub usa `main` como branch padrão (antigamente era `master`).

### 4️⃣ Conectar com o GitHub

Substitua `SEU-USUARIO` pelo seu nome de usuário do GitHub:

```bash
git remote add origin https://github.com/SEU-USUARIO/midia-certa-crm.git
```

Exemplo:
```bash
git remote add origin https://github.com/joaosilva/midia-certa-crm.git
```

### 5️⃣ Enviar para o GitHub

```bash
git push -u origin main
```

Vai pedir suas credenciais do GitHub:
- **Username:** seu usuário do GitHub
- **Password:** 

⚠️ **ATENÇÃO:** Desde 2021, o GitHub não aceita mais senha normal!

Você precisa criar um **Personal Access Token**:

#### 🔑 Como Criar Personal Access Token:

1. No GitHub, vá em: **Settings** (seu perfil) → **Developer settings**
2. Clique em **Personal access tokens** → **Tokens (classic)**
3. Clique em **Generate new token** → **Generate new token (classic)**
4. Configure:
   - **Note:** `Deploy CRM Mídia Certa`
   - **Expiration:** `No expiration` (ou escolha um prazo)
   - **Scopes:** Marque:
     - ✅ `repo` (acesso completo aos repositórios)
     - ✅ `workflow` (para GitHub Actions)
5. Clique em **Generate token**
6. **COPIE O TOKEN** (ele aparece só uma vez!)
7. Use esse token como "senha" no `git push`

💾 **Salve o token em local seguro** (vai precisar dele sempre que fizer push).

### 6️⃣ Alternativa: Usar GitHub Desktop (Mais Fácil)

Se preferir uma interface gráfica:

1. Baixe: https://desktop.github.com/
2. Instale e faça login com sua conta GitHub
3. Clique em **"Add"** → **"Add existing repository"**
4. Selecione a pasta do projeto
5. Faça commit e push pela interface gráfica (muito mais simples!)

✅ **Código enviado para o GitHub!**

Acesse `https://github.com/SEU-USUARIO/midia-certa-crm` para ver seu código online! 🎉

---

## Passo 6: Configurar Deploy Automático

Agora que seu código está no GitHub, siga o **[PASSO_A_PASSO_DEPLOY.md](./PASSO_A_PASSO_DEPLOY.md)** para configurar o deploy automático!

Resumo rápido:
1. Vá em **Settings** → **Secrets and variables** → **Actions**
2. Adicione os secrets do FTP
3. Faça push e o deploy acontece automaticamente!

---

## 🔄 Fluxo de Trabalho Diário

Depois de tudo configurado, seu fluxo será:

```bash
# 1. Fazer alterações no código
# ... editar arquivos ...

# 2. Ver o que mudou
git status

# 3. Adicionar as alterações
git add .

# 4. Fazer commit
git commit -m "Descrição do que você fez"

# 5. Enviar para o GitHub (e fazer deploy automático!)
git push origin main
```

🎉 **Pronto! Deploy automático acontece!**

---

## 📌 Comandos Git Úteis

### Ver Histórico de Commits
```bash
git log --oneline
```

### Desfazer Alterações (antes do commit)
```bash
# Descartar alterações de um arquivo
git checkout -- nome_do_arquivo.php

# Descartar TODAS as alterações
git reset --hard
```

### Ver Diferenças
```bash
# Ver o que mudou
git diff

# Ver diferença de um arquivo específico
git diff nome_do_arquivo.php
```

### Criar Nova Branch (para testar algo)
```bash
# Criar e mudar para nova branch
git checkout -b nova-funcionalidade

# Voltar para a main
git checkout main

# Mesclar a branch
git merge nova-funcionalidade
```

### Atualizar do GitHub (se trabalhar em equipe)
```bash
git pull origin main
```

---

## 🔒 Segurança: Arquivos Protegidos

O `.gitignore` já está configurado para **NÃO** enviar:

- ❌ `config/config.local.php` - Senhas de banco de dados
- ❌ `.deploy-config*` - Senhas de FTP
- ❌ `uploads/*` - Arquivos enviados por usuários
- ❌ `tmp_rovodev_*` - Arquivos temporários
- ❌ `*.log` - Logs

✅ **Esses arquivos ficam APENAS no seu computador e no servidor!**

---

## 🆘 Problemas Comuns

### ❌ "fatal: not a git repository"

**Solução:**
```bash
git init
```

### ❌ "remote origin already exists"

**Solução:**
```bash
git remote remove origin
git remote add origin https://github.com/SEU-USUARIO/seu-repo.git
```

### ❌ "Authentication failed"

**Solução:**
- Use **Personal Access Token** em vez de senha
- Ou use **GitHub Desktop** (mais fácil)

### ❌ "Updates were rejected"

**Solução:**
```bash
# Baixar alterações do GitHub primeiro
git pull origin main --rebase

# Depois enviar
git push origin main
```

### ❌ Commitei algo por engano (senha, etc)

**Solução:**
```bash
# Desfazer o último commit (mantém as alterações)
git reset HEAD~1

# Remover arquivo do Git (mas manter no computador)
git rm --cached config/config.local.php

# Adicionar ao .gitignore
echo "config/config.local.php" >> .gitignore

# Fazer novo commit
git add .
git commit -m "Remove arquivo sensível"
git push origin main --force
```

⚠️ **IMPORTANTE:** Se já enviou senha pro GitHub, **TROQUE A SENHA** imediatamente!

---

## ✅ Checklist Completo

### Antes de começar:
- [ ] Git instalado
- [ ] Conta no GitHub criada
- [ ] Repositório criado no GitHub

### Configuração inicial:
- [ ] `git init` executado
- [ ] Nome e email configurados
- [ ] `.gitignore` verificado
- [ ] Primeiro commit feito
- [ ] Conectado com GitHub (`git remote add origin`)
- [ ] Personal Access Token criado
- [ ] Código enviado (`git push`)

### Deploy automático:
- [ ] Secrets configurados no GitHub
- [ ] `.github/workflows/deploy-cpanel.yml` existe
- [ ] Primeiro deploy funcionou
- [ ] Deploy automático ativo

---

## 🎯 Resumo Rápido

1. ✅ Instale o Git
2. ✅ Crie conta no GitHub
3. ✅ Crie repositório no GitHub
4. ✅ `git init` no projeto
5. ✅ `git add .`
6. ✅ `git commit -m "Primeiro commit"`
7. ✅ `git remote add origin URL-DO-SEU-REPO`
8. ✅ `git push -u origin main`
9. ✅ Configure secrets no GitHub
10. ✅ Faça push → Deploy automático! 🚀

---

## 🎓 Aprender Mais

- **Git Básico:** https://git-scm.com/book/pt-br/v2
- **GitHub Docs:** https://docs.github.com/pt
- **Git Cheat Sheet:** https://education.github.com/git-cheat-sheet-education.pdf

---

## 🎉 Próximos Passos

Depois de configurar tudo:

1. 📖 Siga o **[PASSO_A_PASSO_DEPLOY.md](./PASSO_A_PASSO_DEPLOY.md)**
2. 🚀 Configure deploy automático
3. 💻 Comece a desenvolver com tranquilidade
4. 🔄 Faça `git push` para fazer deploy

**Nunca mais perca código ou precise fazer upload manual!** 🎊

---

**Criado em:** 16/01/2026  
**Versão:** 1.0
