<?php
require_login();
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT o.*, c.name client_name, c.phone client_phone, c.doc client_doc, u.name seller_name
                     FROM os o
                     JOIN clients c ON c.id=o.client_id
                     JOIN users u ON u.id=o.seller_user_id
                     WHERE o.id=?");
$st->execute([$id]);
$os = $st->fetch();
if(!$os){ die('O.S não encontrada'); }

$lines = $pdo->prepare("SELECT l.*, i.name item_name, i.type item_type FROM os_lines l JOIN items i ON i.id=l.item_id WHERE l.os_id=? ORDER BY l.id");
$lines->execute([$id]);
$lines = $lines->fetchAll();

$ar = $pdo->prepare("SELECT t.* FROM ar_titles t WHERE t.os_id=? ORDER BY t.id");
$ar->execute([$id]);
$ar = $ar->fetchAll();

$files = $pdo->prepare("SELECT f.* FROM os_files f WHERE f.os_id=? ORDER BY f.id DESC");
$files->execute([$id]);
$files = $files->fetchAll();

$total = 0; foreach($lines as $l){ $total += ((float)$l['qty'])*((float)$l['unit_price']); }
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>O.S <?= h($os['code']) ?></title>
<style>
body{font-family:Arial, Helvetica, sans-serif; margin:24px; color:#111;}
.header{display:flex; justify-content:space-between; align-items:flex-start; gap:16px;}
.logo img{max-height:50px;}
.box{border:1px solid #ddd; padding:12px; border-radius:10px;}
.small{font-size:12px; color:#444;}
table{width:100%; border-collapse:collapse; margin-top:10px;}
th,td{border-bottom:1px solid #eee; padding:8px; text-align:left;}
th{background:#fafafa;}
.tot{display:flex; justify-content:flex-end; margin-top:8px;}
.tot div{min-width:240px;}
@media print {.no-print{display:none}}
</style>
</head>
<body>
<div class="header">
  <div class="logo">
    <img src="<?= h($base) ?>/assets/images/midia-certa-432x107.png" alt="Mídia Certa">
    <div class="small">Publicidade Gráfica</div>
  </div>
  <div class="box">
    <div><b>O.S:</b> <?= h($os['code']) ?></div>
    <div><b>Status:</b> <?= strtoupper(h($os['status'])) ?></div>
    <div><b>Data:</b> <?= h(substr($os['created_at'],0,10)) ?></div>
  </div>
</div>

<div class="box" style="margin-top:12px">
  <div><b>Cliente:</b> <?= h($os['client_name']) ?> <?= $os['client_phone']?('• '.h($os['client_phone'])):'' ?></div>
  <div><b>Atendente/Vendedor:</b> <?= h($os['seller_name']) ?></div>
  <div><b>Entrega:</b> <?= h($os['delivery_method']) ?> <?= $os['due_date']?('• '.h($os['due_date'])):'' ?></div>
</div>

<div class="box" style="margin-top:12px">
  <b>Itens</b>
  <table>
    <thead><tr><th>Item</th><th>Qtd</th><th>Preço</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach($lines as $l): ?>
      <tr>
        <td><?= h($l['item_name']) ?> <span class="small">(<?= h($l['item_type']) ?>)</span></td>
        <td><?= h($l['qty']) ?></td>
        <td>R$ <?= number_format((float)$l['unit_price'],2,',','.') ?></td>
        <td>R$ <?= number_format(((float)$l['qty'])*((float)$l['unit_price']),2,',','.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="tot">
    <div>
      <div style="display:flex;justify-content:space-between"><span>Total</span><b>R$ <?= number_format($total,2,',','.') ?></b></div>
      <?php foreach($ar as $t): ?>
        <div style="display:flex;justify-content:space-between" class="small">
          <span><?= h($t['kind']) ?> (<?= h($t['method']) ?>)</span>
          <span>R$ <?= number_format((float)$t['amount'],2,',','.') ?> • <?= strtoupper(h($t['status'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="box" style="margin-top:12px">
  <b>Arquivos</b>
  <ul>
    <?php foreach($files as $f): ?>
      <li class="small"><b><?= h($f['kind']) ?></b> — <?= h($f['original_name'] ?: basename($f['file_path'])) ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="box" style="margin-top:12px">
  <b>Termos / Responsabilidade</b>
  <div class="small" style="margin-top:6px">
    Erros ortográficos, cores e demais detalhes NÃO são de responsabilidade da Mídia Certa.
    A conferência completa da arte é responsabilidade do cliente. Ao aprovar, o cliente concorda com tudo contido nesta O.S.
    Após o pedido realizado, não é possível alterar o pedido/arte sem autorização do administrador.
  </div>
</div>

<div class="no-print" style="margin-top:12px">
  <button onclick="window.print()">Imprimir / Salvar como PDF</button>
</div>
</body>
</html>
