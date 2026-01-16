<?php
// IMPRESSÃO PROFISSIONAL DE OS - Formato A4
require_login();
$id = (int)($_GET['id'] ?? 0);
if(!$id){ flash_set('danger','O.S inválida'); redirect($base.'/app.php?page=os'); }

$st = $pdo->prepare("SELECT o.*,
                            c.name client_name, c.whatsapp, c.email,
                            c.address_street, c.address_number, c.address_neighborhood, 
                            c.address_city, c.address_state,
                            u.name seller_name
                     FROM os o
                     JOIN clients c ON c.id=o.client_id
                     JOIN users u ON u.id=o.seller_user_id
                     WHERE o.id=?");
$st->execute([$id]);
$os = $st->fetch();
if(!$os){ flash_set('danger','O.S não encontrada'); redirect($base.'/app.php?page=os'); }

$lines = $pdo->prepare("SELECT l.*, i.name item_name FROM os_lines l 
                        JOIN items i ON i.id=l.item_id 
                        WHERE l.os_id=? ORDER BY l.id");
$lines->execute([$id]);
$lines = $lines->fetchAll();

$total = array_sum(array_map(fn($l) => $l['qty'] * $l['unit_price'], $lines));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OS <?= h($os['code']) ?> - <?= h(COMPANY_NAME) ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
        }
        .header {
            border-bottom: 3px solid #0B1E3B;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .company-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .company-logo {
            max-width: 180px;
        }
        .company-details {
            text-align: right;
            font-size: 9pt;
        }
        .company-details strong {
            font-size: 11pt;
            display: block;
            margin-bottom: 3px;
        }
        .os-title {
            background: #0B1E3B;
            color: white;
            padding: 8px 12px;
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
        }
        .section {
            margin-bottom: 15px;
        }
        .section-title {
            background: #f0f0f0;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 10pt;
            border-left: 4px solid #0B1E3B;
            margin-bottom: 8px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        .info-item {
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        .info-item label {
            font-weight: bold;
            font-size: 9pt;
            color: #666;
            display: block;
        }
        .info-item value {
            font-size: 10pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background: #0B1E3B;
            color: white;
            padding: 6px;
            text-align: left;
            font-size: 9pt;
        }
        td {
            border-bottom: 1px solid #ddd;
            padding: 6px;
            font-size: 10pt;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background: #f9f9f9;
            font-weight: bold;
            font-size: 12pt;
        }
        .footer {
            margin-top: 30px;
            border-top: 2px solid #ddd;
            padding-top: 15px;
            font-size: 9pt;
            color: #666;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
        }
        .signature-box {
            border-top: 1px solid #000;
            padding-top: 5px;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #059669;
            color: white;
            border-radius: 4px;
            font-size: 10pt;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Botão Imprimir (oculto na impressão) -->
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #0B1E3B; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
            🖨️ Imprimir / Salvar PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-left: 10px;">
            ← Voltar
        </button>
    </div>

    <!-- Cabeçalho -->
    <div class="header">
        <div class="company-info">
            <div>
                <img src="<?= h($base) ?>/assets/images/midia-certa-432x107.png" class="company-logo" alt="<?= h(COMPANY_NAME) ?>">
            </div>
            <div class="company-details">
                <strong><?= h(COMPANY_NAME) ?></strong>
                <?= h(COMPANY_PHONE1) ?> | <?= h(COMPANY_PHONE2) ?><br>
                <?= h(COMPANY_ADDRESS) ?><br>
                CNPJ: <?= h(COMPANY_CNPJ) ?>
            </div>
        </div>
    </div>

    <!-- Título OS -->
    <div class="os-title">
        ORDEM DE SERVIÇO Nº <?= h($os['code']) ?>
    </div>

    <!-- Informações da OS -->
    <div class="section">
        <div class="info-grid">
            <div class="info-item">
                <label>Data de Emissão:</label>
                <value><?= date('d/m/Y H:i', strtotime($os['created_at'])) ?></value>
            </div>
            <div class="info-item">
                <label>Status:</label>
                <value><span class="status-badge"><?= strtoupper(h($os['status'])) ?></span></value>
            </div>
            <div class="info-item">
                <label>Vendedor:</label>
                <value><?= h($os['seller_name']) ?></value>
            </div>
            <?php if ($os['due_date']): ?>
            <div class="info-item">
                <label>Prazo de Entrega:</label>
                <value><?= date('d/m/Y', strtotime($os['due_date'])) ?></value>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dados do Cliente -->
    <div class="section">
        <div class="section-title">DADOS DO CLIENTE</div>
        <div class="info-grid">
            <div class="info-item">
                <label>Nome/Razão Social:</label>
                <value><?= h($os['client_name']) ?></value>
            </div>
            <div class="info-item">
                <label>Telefone/WhatsApp:</label>
                <value><?= h($os['whatsapp']) ?></value>
            </div>
            <div class="info-item">
                <label>E-mail:</label>
                <value><?= h($os['email'] ?? 'Não informado') ?></value>
            </div>
        </div>
        <?php if ($os['address_street']): ?>
        <div class="info-item">
            <label>Endereço:</label>
            <value>
                <?= h($os['address_street']) ?>, <?= h($os['address_number']) ?> 
                - <?= h($os['address_neighborhood']) ?>
                - <?= h($os['address_city']) ?>/<?= h($os['address_state']) ?>
            </value>
        </div>
        <?php endif; ?>
    </div>

    <!-- Itens da OS -->
    <div class="section">
        <div class="section-title">ITENS / PRODUTOS</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Qtd</th>
                    <th>Descrição do Produto/Serviço</th>
                    <th class="text-right" style="width: 15%;">Valor Unit.</th>
                    <th class="text-right" style="width: 15%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                <tr>
                    <td class="text-center"><?= h($line['qty']) ?></td>
                    <td>
                        <strong><?= h($line['item_name']) ?></strong>
                        <?php if ($line['specs']): ?>
                        <br><small style="color: #666;"><?= nl2br(h($line['specs'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">R$ <?= number_format($line['unit_price'], 2, ',', '.') ?></td>
                    <td class="text-right">R$ <?= number_format($line['qty'] * $line['unit_price'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL:</td>
                    <td class="text-right">R$ <?= number_format($total, 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Observações -->
    <?php if ($os['notes']): ?>
    <div class="section">
        <div class="section-title">OBSERVAÇÕES</div>
        <div style="padding: 10px; background: #f9f9f9; border-left: 3px solid #0B1E3B;">
            <?= nl2br(h($os['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Assinaturas -->
    <div class="signatures">
        <div class="signature-box">
            <strong>Assinatura do Cliente</strong><br>
            <small>CPF: _____________________</small>
        </div>
        <div class="signature-box">
            <strong><?= h(COMPANY_NAME) ?></strong><br>
            <small>Vendedor: <?= h($os['seller_name']) ?></small>
        </div>
    </div>

    <!-- Rodapé -->
    <div class="footer">
        <p><strong>Termos e Condições:</strong></p>
        <ul style="margin-left: 20px; margin-top: 5px;">
            <li>A conferência completa da arte é responsabilidade do cliente.</li>
            <li>Ao aprovar, o cliente concorda com tudo contido nesta O.S.</li>
            <li>Prazos de entrega contam a partir da aprovação da arte.</li>
            <li>Não nos responsabilizamos por erros não apontados pelo cliente.</li>
        </ul>
        <p style="margin-top: 10px; text-align: center; font-size: 8pt;">
            <?= h(COMPANY_NAME) ?> - <?= h(COMPANY_ADDRESS) ?> - <?= h(COMPANY_PHONE1) ?> / <?= h(COMPANY_PHONE2) ?>
        </p>
    </div>
</body>
</html>
