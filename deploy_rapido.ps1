# Script de Deploy Rápido
# Use: .\deploy_rapido.ps1 "Sua mensagem de commit"

param(
    [string]$mensagem = "Atualização do sistema"
)

Write-Host "🚀 Deploy Rápido - Mídia Certa CRM" -ForegroundColor Cyan
Write-Host ""

# Adicionar todos os arquivos
Write-Host "📦 Adicionando arquivos..." -ForegroundColor Yellow
git add .

# Fazer commit
Write-Host "💾 Fazendo commit: $mensagem" -ForegroundColor Yellow
git commit -m "$mensagem"

# Enviar para GitHub (deploy automático)
Write-Host "📤 Enviando para GitHub..." -ForegroundColor Yellow
git push origin main

Write-Host ""
Write-Host "✅ Deploy iniciado! Acompanhe em:" -ForegroundColor Green
Write-Host "   https://github.com/douglasmidiacerta/midia-certa-crm/actions" -ForegroundColor Cyan
Write-Host ""
Write-Host "⏱️  Deploy leva cerca de 1-2 minutos" -ForegroundColor Yellow
