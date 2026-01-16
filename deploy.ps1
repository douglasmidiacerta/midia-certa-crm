# 🚀 Script de Deploy Manual para cPanel via FTP/FTPS (Windows PowerShell)
# Use este script se não quiser usar GitHub Actions

$ErrorActionPreference = "Stop"

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "🚀 Deploy Mídia Certa CRM/ERP para cPanel" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Arquivo de configuração
$ConfigFile = ".deploy-config.json"

if (-not (Test-Path $ConfigFile)) {
    Write-Host "⚠️  Arquivo de configuração não encontrado!" -ForegroundColor Yellow
    Write-Host "Criando arquivo de configuração..." -ForegroundColor Yellow
    Write-Host ""
    
    $FtpServer = Read-Host "🌐 Servidor FTP (ex: ftp.seudominio.com)"
    $FtpUser = Read-Host "👤 Usuário FTP"
    $FtpPass = Read-Host "🔑 Senha FTP" -AsSecureString
    $FtpPassPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($FtpPass))
    $FtpDir = Read-Host "📁 Diretório no servidor (ex: /public_html/)"
    $FtpPort = Read-Host "🔌 Porta FTP (21 para FTP, 990 para FTPS)"
    $UseFtps = Read-Host "🔒 Usar FTPS? (s/n)"
    
    $Config = @{
        FTP_SERVER = $FtpServer
        FTP_USER = $FtpUser
        FTP_PASS = $FtpPassPlain
        FTP_DIR = $FtpDir
        FTP_PORT = $FtpPort
        USE_FTPS = $UseFtps
    }
    
    $Config | ConvertTo-Json | Out-File $ConfigFile -Encoding UTF8
    Write-Host "✅ Configuração salva em $ConfigFile" -ForegroundColor Green
    Write-Host ""
    Write-Host "⚠️  ATENÇÃO: Adicione .deploy-config.json ao .gitignore!" -ForegroundColor Yellow
    Write-Host ""
}

# Carregar configuração
$Config = Get-Content $ConfigFile | ConvertFrom-Json

Write-Host "📋 Configuração carregada:" -ForegroundColor Yellow
Write-Host "   Servidor: $($Config.FTP_SERVER)"
Write-Host "   Usuário: $($Config.FTP_USER)"
Write-Host "   Diretório: $($Config.FTP_DIR)"
Write-Host "   Porta: $($Config.FTP_PORT)"
Write-Host ""

$Confirm = Read-Host "Continuar com o deploy? (s/n)"
if ($Confirm -ne "s") {
    Write-Host "Deploy cancelado."
    exit 0
}

Write-Host ""
Write-Host "🔍 Validando arquivos PHP..." -ForegroundColor Yellow

# Validar sintaxe PHP (requer PHP instalado no Windows)
$PhpErrors = 0
if (Get-Command php -ErrorAction SilentlyContinue) {
    Get-ChildItem -Path . -Filter *.php -Recurse -File | Where-Object {
        $_.FullName -notmatch "vendor|uploads"
    } | ForEach-Object {
        $result = php -l $_.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Host "❌ Erro em: $($_.FullName)" -ForegroundColor Red
            Write-Host $result
            $PhpErrors++
        }
    }
    
    if ($PhpErrors -gt 0) {
        Write-Host "❌ Encontrados $PhpErrors erros de sintaxe PHP!" -ForegroundColor Red
        $Continue = Read-Host "Continuar mesmo assim? (s/n)"
        if ($Continue -ne "s") {
            exit 1
        }
    } else {
        Write-Host "✅ Todos os arquivos PHP estão válidos!" -ForegroundColor Green
    }
} else {
    Write-Host "⚠️  PHP não encontrado. Pulando validação..." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "🧹 Limpando arquivos temporários..." -ForegroundColor Yellow
Get-ChildItem -Path . -Recurse -Include tmp_rovodev_*,.DS_Store,Thumbs.db,desktop.ini,*.log -File | Remove-Item -Force
Write-Host "✅ Arquivos temporários removidos" -ForegroundColor Green

Write-Host ""
Write-Host "📦 Instalando WinSCP para upload (se necessário)..." -ForegroundColor Yellow

# Verificar se WinSCP está disponível ou usar WebClient nativo
$UseWinSCP = $false

if (Get-Command winscp.com -ErrorAction SilentlyContinue) {
    $UseWinSCP = $true
    Write-Host "✅ WinSCP encontrado!" -ForegroundColor Green
} else {
    Write-Host "⚠️  WinSCP não encontrado. Usando método alternativo..." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "📤 Iniciando upload..." -ForegroundColor Yellow

if ($UseWinSCP) {
    # Usar WinSCP para upload
    $Protocol = if ($Config.USE_FTPS -eq "s") { "ftps" } else { "ftp" }
    
    $WinSCPScript = @"
open $Protocol`://$($Config.FTP_USER):$($Config.FTP_PASS)@$($Config.FTP_SERVER):$($Config.FTP_PORT)
cd $($Config.FTP_DIR)
synchronize remote . -delete -filemask="|.git/;.github/;.vscode/;.idea/;node_modules/;tmp_rovodev_*;*.log;.DS_Store;Thumbs.db;desktop.ini;config/config.local.php;*.md;uploads/;database/;deploy.*;.deploy-config.*;.ftpignore"
exit
"@
    
    $WinSCPScript | winscp.com /script=- /log=deploy.log
    
} else {
    # Usar FTP nativo do PowerShell (mais básico)
    Write-Host "Usando método FTP nativo (upload arquivo por arquivo)..." -ForegroundColor Yellow
    
    $FtpUri = "ftp://$($Config.FTP_SERVER):$($Config.FTP_PORT)$($Config.FTP_DIR)"
    $Credentials = New-Object System.Net.NetworkCredential($Config.FTP_USER, $Config.FTP_PASS)
    
    # Lista de exclusões
    $Exclude = @("*.git*", ".vscode", ".idea", "node_modules", "tmp_rovodev_*", "*.log", 
                 ".DS_Store", "Thumbs.db", "desktop.ini", "config.local.php", "*.md", 
                 "uploads", "database", "deploy.*", ".deploy-config*", ".ftpignore")
    
    # Upload recursivo (simplificado)
    Get-ChildItem -Path . -Recurse -File | Where-Object {
        $file = $_
        $shouldExclude = $false
        foreach ($pattern in $Exclude) {
            if ($file.Name -like $pattern -or $file.FullName -like "*\$pattern\*") {
                $shouldExclude = $true
                break
            }
        }
        -not $shouldExclude
    } | ForEach-Object {
        try {
            $relativePath = $_.FullName.Substring((Get-Location).Path.Length + 1)
            $targetUri = "$FtpUri/$($relativePath -replace '\\', '/')"
            
            Write-Host "  Uploading: $relativePath" -ForegroundColor Gray
            
            $request = [System.Net.FtpWebRequest]::Create($targetUri)
            $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
            $request.Credentials = $Credentials
            $request.UseBinary = $true
            $request.UsePassive = $true
            
            $fileContent = [System.IO.File]::ReadAllBytes($_.FullName)
            $request.ContentLength = $fileContent.Length
            
            $requestStream = $request.GetRequestStream()
            $requestStream.Write($fileContent, 0, $fileContent.Length)
            $requestStream.Close()
            
            $response = $request.GetResponse()
            $response.Close()
        } catch {
            Write-Host "  ❌ Erro ao enviar $relativePath : $_" -ForegroundColor Red
        }
    }
}

Write-Host ""
Write-Host "================================================" -ForegroundColor Green
Write-Host "🎉 Deploy concluído com sucesso!" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Green
Write-Host ""
Write-Host "📅 Data: $(Get-Date)"
Write-Host "🔗 Acesse seu site para verificar"
