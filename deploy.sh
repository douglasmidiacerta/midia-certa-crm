#!/bin/bash

# 🚀 Script de Deploy Manual para cPanel via FTP/FTPS
# Use este script se não quiser usar GitHub Actions

set -e  # Para em caso de erro

echo "================================================"
echo "🚀 Deploy Mídia Certa CRM/ERP para cPanel"
echo "================================================"
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar se o arquivo de configuração existe
CONFIG_FILE=".deploy-config"

if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${YELLOW}⚠️  Arquivo de configuração não encontrado!${NC}"
    echo "Criando arquivo .deploy-config..."
    echo ""
    
    read -p "🌐 Servidor FTP (ex: ftp.seudominio.com): " FTP_SERVER
    read -p "👤 Usuário FTP: " FTP_USER
    read -sp "🔑 Senha FTP: " FTP_PASS
    echo ""
    read -p "📁 Diretório no servidor (ex: /public_html/): " FTP_DIR
    read -p "🔌 Porta FTP (21 para FTP, 990 para FTPS): " FTP_PORT
    read -p "🔒 Usar FTPS? (s/n): " USE_FTPS
    
    # Salvar configuração (ATENÇÃO: arquivo sensível!)
    cat > "$CONFIG_FILE" << EOF
FTP_SERVER="$FTP_SERVER"
FTP_USER="$FTP_USER"
FTP_PASS="$FTP_PASS"
FTP_DIR="$FTP_DIR"
FTP_PORT="$FTP_PORT"
USE_FTPS="$USE_FTPS"
EOF
    
    chmod 600 "$CONFIG_FILE"  # Proteger arquivo
    echo -e "${GREEN}✅ Configuração salva em $CONFIG_FILE${NC}"
    echo ""
fi

# Carregar configuração
source "$CONFIG_FILE"

echo -e "${YELLOW}📋 Configuração carregada:${NC}"
echo "   Servidor: $FTP_SERVER"
echo "   Usuário: $FTP_USER"
echo "   Diretório: $FTP_DIR"
echo "   Porta: $FTP_PORT"
echo ""

read -p "Continuar com o deploy? (s/n): " CONFIRM
if [ "$CONFIRM" != "s" ]; then
    echo "Deploy cancelado."
    exit 0
fi

echo ""
echo -e "${YELLOW}🔍 Validando arquivos PHP...${NC}"

# Validar sintaxe PHP
PHP_ERRORS=0
for file in $(find . -name "*.php" -not -path "./vendor/*" -not -path "./uploads/*"); do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "${RED}❌ Erro em: $file${NC}"
        php -l "$file"
        PHP_ERRORS=$((PHP_ERRORS + 1))
    fi
done

if [ $PHP_ERRORS -gt 0 ]; then
    echo -e "${RED}❌ Encontrados $PHP_ERRORS erros de sintaxe PHP!${NC}"
    read -p "Continuar mesmo assim? (s/n): " CONTINUE
    if [ "$CONTINUE" != "s" ]; then
        exit 1
    fi
else
    echo -e "${GREEN}✅ Todos os arquivos PHP estão válidos!${NC}"
fi

echo ""
echo -e "${YELLOW}🧹 Limpando arquivos temporários...${NC}"
find . -name "tmp_rovodev_*" -delete
find . -name ".DS_Store" -delete
find . -name "Thumbs.db" -delete
find . -name "desktop.ini" -delete
find . -name "*.log" -delete
echo -e "${GREEN}✅ Arquivos temporários removidos${NC}"

echo ""
echo -e "${YELLOW}📦 Preparando para upload via FTP...${NC}"

# Verificar se lftp está instalado
if ! command -v lftp &> /dev/null; then
    echo -e "${RED}❌ lftp não está instalado!${NC}"
    echo "Instale com: sudo apt-get install lftp (Ubuntu/Debian)"
    echo "ou: brew install lftp (macOS)"
    exit 1
fi

# Configurar protocolo
if [ "$USE_FTPS" = "s" ]; then
    PROTOCOL="ftps"
    SSL_OPTS="set ftp:ssl-allow yes; set ftp:ssl-protect-data yes;"
else
    PROTOCOL="ftp"
    SSL_OPTS=""
fi

echo -e "${YELLOW}📤 Iniciando upload...${NC}"
echo ""

# Upload usando lftp
lftp -c "
set ftp:list-options -a;
$SSL_OPTS
open $PROTOCOL://$FTP_USER:$FTP_PASS@$FTP_SERVER:$FTP_PORT;
lcd .;
cd $FTP_DIR;
mirror --reverse \
       --delete \
       --verbose \
       --exclude .git/ \
       --exclude .github/ \
       --exclude .gitignore \
       --exclude .vscode/ \
       --exclude .idea/ \
       --exclude node_modules/ \
       --exclude tmp_rovodev_ \
       --exclude '*.log' \
       --exclude error_log \
       --exclude .DS_Store \
       --exclude Thumbs.db \
       --exclude desktop.ini \
       --exclude config/config.local.php \
       --exclude '*.md' \
       --exclude uploads/ \
       --exclude database/ \
       --exclude deploy.sh \
       --exclude deploy.ps1 \
       --exclude .deploy-config \
       --exclude .ftpignore \
       . ;
bye;
"

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}================================================${NC}"
    echo -e "${GREEN}🎉 Deploy concluído com sucesso!${NC}"
    echo -e "${GREEN}================================================${NC}"
    echo ""
    echo "📅 Data: $(date)"
    echo "🔗 Acesse seu site para verificar"
else
    echo ""
    echo -e "${RED}❌ Erro durante o deploy!${NC}"
    exit 1
fi
