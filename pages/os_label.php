<?php
// ETIQUETA COM QR CODE - Máximo 9cm de largura
require_login();
$id = (int)($_GET['id'] ?? 0);
if(!$id){ flash_set('danger','O.S inválida'); redirect($base.'/app.php?page=os'); }

$st = $pdo->prepare("SELECT o.code, c.name client_name FROM os o JOIN clients c ON c.id=o.client_id WHERE o.id=?");
$st->execute([$id]);
$os = $st->fetch();
if(!$os){ flash_set('danger','O.S não encontrada'); redirect($base.'/app.php?page=os'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta OS <?= h($os['code']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 10mm;
        }
        .label {
            width: 90mm; /* 9cm */
            border: 2px solid #000;
            padding: 5mm;
            page-break-after: always;
            background: white;
            color: black;
        }
        .logo {
            text-align: center;
            margin-bottom: 3mm;
        }
        .logo img {
            max-width: 70mm;
            height: auto;
        }
        .company-name {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 2mm;
        }
        .os-number {
            text-align: center;
            font-size: 24pt;
            font-weight: bold;
            background: #000;
            color: white;
            padding: 3mm;
            margin: 3mm 0;
            border: 2px solid #000;
        }
        .client-name {
            text-align: center;
            font-size: 10pt;
            margin-bottom: 3mm;
            font-weight: bold;
        }
        .qr-code {
            text-align: center;
            margin: 3mm 0;
        }
        .contact-info {
            text-align: center;
            font-size: 8pt;
            line-height: 1.4;
            border-top: 1px solid #ccc;
            padding-top: 2mm;
            margin-top: 3mm;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .label {
                margin: 0;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <!-- Botão Imprimir (oculto na impressão) -->
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #0B1E3B; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
            🖨️ Imprimir Etiqueta
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-left: 10px;">
            ← Voltar
        </button>
    </div>

    <!-- Etiqueta -->
    <div class="label">
        <div class="logo">
            <img src="<?= h($base) ?>/assets/images/midia-certa-432x107.png" alt="<?= h(COMPANY_NAME) ?>">
        </div>
        
        <div class="company-name">
            <?= h(COMPANY_NAME) ?>
        </div>
        
        <div class="os-number">
            OS <?= h($os['code']) ?>
        </div>
        
        <div class="client-name">
            <?= h($os['client_name']) ?>
        </div>
        
        <div class="qr-code">
            <!-- QR Code gerado via API pública do Google Charts (gratuito) -->
            <img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=<?= urlencode(COMPANY_WHATSAPP_LINK) ?>&choe=UTF-8" 
                 alt="QR Code WhatsApp" 
                 style="width: 40mm; height: 40mm;">
            <div style="font-size: 8pt; margin-top: 2mm;">
                <strong>Escaneie para falar conosco</strong>
            </div>
        </div>
        
        <div class="contact-info">
            <?= h(COMPANY_ADDRESS) ?><br>
            <?= h(COMPANY_PHONE1) ?> | <?= h(COMPANY_PHONE2) ?><br>
            CNPJ: <?= h(COMPANY_CNPJ) ?>
        </div>
    </div>

    <div class="no-print" style="margin-top: 20px; padding: 15px; background: #f0f9ff; border: 1px solid #3b82f6; border-radius: 4px;">
        <h6 style="font-weight: bold; margin-bottom: 10px;">💡 Configuração da Impressora:</h6>
        <ul style="margin-left: 20px; line-height: 1.8;">
            <li><strong>Tamanho do papel:</strong> Personalizado (90mm de largura)</li>
            <li><strong>Orientação:</strong> Retrato</li>
            <li><strong>Margens:</strong> Mínimas ou zero</li>
            <li><strong>Impressora sugerida:</strong> Impressora térmica de etiquetas ou impressora comum em papel adesivo 90mm</li>
            <li><strong>Papel recomendado:</strong> Etiqueta adesiva 90mm x 60mm (ou cortar papel A4 adesivo)</li>
        </ul>
        <p style="margin-top: 10px; color: #666; font-size: 10pt;">
            <strong>Dica:</strong> Se usar impressora comum, imprima em papel adesivo A4 e corte as etiquetas manualmente.
        </p>
    </div>
</body>
</html>
