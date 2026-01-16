<?php
require_login();
require_role(['admin','financeiro']);

$defaultFile = __DIR__.'/../database/imports/clientes_midia_certa_2025-12-27.csv';

function normalize_digits($s){
  $s = preg_replace('/\D+/', '', (string)$s);
  return $s;
}

$report = null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  $mode = $_POST['mode'] ?? 'default';
  $path = $defaultFile;

  if($mode==='upload'){
    if(!isset($_FILES['csv']) || $_FILES['csv']['error']!==UPLOAD_ERR_OK){
      flash_set('danger','Falha ao enviar o CSV.');
      redirect($base.'/app.php?page=clients_import');
    }
    $tmp = $_FILES['csv']['tmp_name'];
    $path = $tmp;
  }

  if(!is_readable($path)){
    flash_set('danger','Arquivo CSV não encontrado.');
    redirect($base.'/app.php?page=clients_import');
  }

  $h = fopen($path,'r');
  if(!$h){
    flash_set('danger','Não foi possível abrir o CSV.');
    redirect($base.'/app.php?page=clients_import');
  }

  $header = fgetcsv($h);
  if(!$header){
    fclose($h);
    flash_set('danger','CSV vazio.');
    redirect($base.'/app.php?page=clients_import');
  }

  // Esperado: legacy_no,name,fantasy,whatsapp
  $idx = array_flip($header);
  $need = ['name','whatsapp'];
  foreach($need as $k){
    if(!isset($idx[$k])){
      fclose($h);
      flash_set('danger','CSV inválido: faltando coluna "'.$k.'".');
      redirect($base.'/app.php?page=clients_import');
    }
  }

  $inserted=0; $skipped=0; $errors=0;
  $pdo->beginTransaction();
  try{
    $stIns = $pdo->prepare("INSERT INTO clients (name,contact_name,whatsapp,phone_fixed,email,cpf,cnpj,cep,address_street,address_number,address_neighborhood,address_city,address_state,address_complement,notes,phone,doc,address,created_at,active)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),1)");

    // Dedup simples: por nome + whatsapp (quando existir)
    $stChk = $pdo->prepare("SELECT id FROM clients WHERE active=1 AND name=? AND whatsapp=? LIMIT 1");

    while(($row=fgetcsv($h))!==false){
      $name = trim($row[$idx['name']] ?? '');
      $wh = trim($row[$idx['whatsapp']] ?? '');
      $fantasy = trim($row[$idx['fantasy']] ?? '');
      $legacy = trim($row[$idx['legacy_no']] ?? '');

      if(!$name){ $skipped++; continue; }

      $whDigits = normalize_digits($wh);
      if($whDigits && strlen($whDigits)>=10){
        // mantém só dígitos (sem +55)
        if(strlen($whDigits)>11) $whDigits = substr($whDigits,-11);
      } else {
        $whDigits = '';
      }

      $stChk->execute([$name,$whDigits]);
      if($stChk->fetch()) { $skipped++; continue; }

      $notes = [];
      if($fantasy) $notes[] = 'Fantasia: '.$fantasy;
      if($legacy) $notes[] = 'Código legado: '.$legacy;
      $notes = implode(' | ', $notes);

      // campos compat
      $doc='';
      $address_legacy='';

      $stIns->execute([$name,'',$whDigits,'','','','','','','','','','','',$notes,$whDigits,$doc,$address_legacy]);
      $inserted++;
    }

    $pdo->commit();
  } catch(Exception $e){
    $pdo->rollBack();
    $errors++;
    $report = 'Erro: '.$e->getMessage();
  }

  fclose($h);

  if(!$errors){
    flash_set('success',"Importação concluída. Inseridos: $inserted | Ignorados: $skipped");
    redirect($base.'/app.php?page=clients');
  }
}
?>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 style="font-weight:900" class="m-0">Importar Clientes</h5>
    <a class="btn btn-outline-secondary btn-sm" href="<?= h($base) ?>/app.php?page=clients">Voltar</a>
  </div>

  <div class="text-muted small mt-2">
    Você pode importar usando o arquivo já incluso no projeto (<b>database/imports/clientes_midia_certa_2025-12-27.csv</b>) ou enviar um CSV no mesmo formato.
    <div class="mt-1">Colunas esperadas: <code>legacy_no,name,fantasy,whatsapp</code></div>
  </div>

  <?php if($report): ?>
    <div class="alert alert-danger mt-3"><?= h($report) ?></div>
  <?php endif; ?>

  <form class="mt-3" method="post" enctype="multipart/form-data">
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">Modo</label>
        <select class="form-select" name="mode" id="modeSel" onchange="document.getElementById('uploadBox').style.display = (this.value==='upload'?'block':'none');">
          <option value="default">Usar CSV incluso no projeto</option>
          <option value="upload">Enviar meu CSV</option>
        </select>
      </div>
      <div class="col-md-6" id="uploadBox" style="display:none">
        <label class="form-label">CSV</label>
        <input class="form-control" type="file" name="csv" accept=".csv,text/csv">
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary" type="submit" onclick="return confirm('Importar clientes agora? Isso vai inserir novos registros no banco.');">Importar</button>
    </div>

    <div class="alert alert-warning mt-3 mb-0">
      <b>Atenção:</b> esta importação não apaga nada. Ela só insere novos clientes. Duplicados simples (mesmo nome + whatsapp) são ignorados.
    </div>
  </form>
</div>
