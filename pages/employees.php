<?php
require_login();
require_role(['admin']);

function employee_role_from($access_level, $department){
  $access_level = (string)$access_level;
  $department = strtolower((string)$department);
  if($access_level === 'admin') return 'admin';
  if(str_contains($department,'fin')) return 'financeiro';
  return 'vendas';
}

$action = $_POST['action'] ?? '';
$edit_id = (int)($_GET['edit'] ?? 0);
$del_id  = (int)($_GET['del'] ?? 0);

function employees_upload_photo($file){
  if(empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
  if(($file['error'] ?? 0) !== UPLOAD_ERR_OK) return '';
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if(!in_array($ext, ['jpg','jpeg','png','webp'])) return '';
  $dir = __DIR__ . '/../uploads/employees';
  if(!is_dir($dir)) @mkdir($dir, 0775, true);
  $name = 'emp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dst = $dir . '/' . $name;
  if(!move_uploaded_file($file['tmp_name'], $dst)) return '';
  return 'employees/' . $name;
}

function employees_upload_doc($file){
  if(empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
  if(($file['error'] ?? 0) !== UPLOAD_ERR_OK) return '';
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if($ext !== 'pdf') return '';
  $dir = __DIR__ . '/../uploads/employees_docs';
  if(!is_dir($dir)) @mkdir($dir, 0775, true);
  $name = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
  $dst = $dir . '/' . $name;
  if(!move_uploaded_file($file['tmp_name'], $dst)) return '';
  return 'employees_docs/' . $name;
}

if($del_id){
  $st = $pdo->prepare("UPDATE employees SET active=0, status='desligado' WHERE id=?");
  $st->execute([$del_id]);
  flash_set('success','Funcionário removido.');
}

if($_SERVER['REQUEST_METHOD']==='POST' && $action==='save'){
  $id = (int)($_POST['id'] ?? 0);

  $full_name = trim($_POST['full_name'] ?? '');
  $role_title = trim($_POST['role_title'] ?? '');
  $status = $_POST['status'] ?? 'ativo';

  $cpf = trim($_POST['cpf'] ?? '');
  $rg  = trim($_POST['rg'] ?? '');
  $birth_date = trim($_POST['birth_date'] ?? '');
  $gender = trim($_POST['gender'] ?? '');

  $email_work = trim($_POST['email_work'] ?? '');
  $email_personal = trim($_POST['email_personal'] ?? '');
  $whatsapp = trim($_POST['whatsapp'] ?? '');

  // Acesso ao sistema (criar usuário)
  $create_user = (int)($_POST['create_user'] ?? 0) === 1;
  $login_email = trim($_POST['login_email'] ?? '');
  if(!$login_email) $login_email = $email_work;
  $login_pass  = trim($_POST['login_pass'] ?? '');


  $cep = trim($_POST['cep'] ?? '');
  $street = trim($_POST['street'] ?? '');
  $number = trim($_POST['number'] ?? '');
  $neighborhood = trim($_POST['neighborhood'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $state = trim($_POST['state'] ?? '');
  $complement = trim($_POST['complement'] ?? '');

  $ctps_number = trim($_POST['ctps_number'] ?? '');
  $ctps_series = trim($_POST['ctps_series'] ?? '');
  $ctps_uf = trim($_POST['ctps_uf'] ?? '');
  $pis = trim($_POST['pis'] ?? '');
  $admission_date = trim($_POST['admission_date'] ?? '');
  $contract_type = trim($_POST['contract_type'] ?? 'clt');

  $department = trim($_POST['department'] ?? '');
  $manager_id = (int)($_POST['manager_id'] ?? 0);
  $access_level = trim($_POST['access_level'] ?? 'operacional');

  if(!$full_name || !$whatsapp){
    flash_set('danger','Nome completo e WhatsApp são obrigatórios.');
  } else {
    $photo_path = employees_upload_photo($_FILES['photo'] ?? null);
    $doc_path = employees_upload_doc($_FILES['doc_pdf'] ?? null);

    if($id){
      $cur = $pdo->prepare("SELECT photo_path, doc_path FROM employees WHERE id=?");
      $cur->execute([$id]);
      $cur = $cur->fetch();

      $photo_final = $photo_path ?: ($cur['photo_path'] ?? '');
      $doc_final   = $doc_path ?: ($cur['doc_path'] ?? '');

      $st = $pdo->prepare("UPDATE employees SET
        full_name=?, role_title=?, status=?,
        cpf=?, rg=?, birth_date=?, gender=?,
        email_work=?, email_personal=?, whatsapp=?,
        cep=?, street=?, number=?, neighborhood=?, city=?, state=?, complement=?,
        ctps_number=?, ctps_series=?, ctps_uf=?, pis=?, admission_date=?, contract_type=?,
        department=?, manager_id=?, access_level=?,
        photo_path=?, doc_path=?, updated_at=NOW()
        WHERE id=?");
      $st->execute([
        $full_name,$role_title,$status,
        $cpf,$rg,($birth_date?:null),$gender,
        $email_work,$email_personal,$whatsapp,
        $cep,$street,$number,$neighborhood,$city,$state,$complement,
        $ctps_number,$ctps_series,$ctps_uf,$pis,($admission_date?:null),$contract_type,
        $department,($manager_id?:null),$access_level,
        $photo_final,$doc_final,$id
      ]);
      $emp_id = $id;

      // criar usuário de acesso (opcional)
      if($create_user){
        if(!$login_email){
          flash_set('danger','Informe um e-mail corporativo para criar o usuário.');
          redirect($base.'/app.php?page=employees');
        }

        // gera senha se não veio
        if(!$login_pass){
          $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
          $login_pass = '';
          for($i=0;$i<10;$i++){ $login_pass .= $alphabet[random_int(0, strlen($alphabet)-1)]; }
        }

        // já existe usuário com esse email?
        $stU = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stU->execute([$login_email]);
        $u = $stU->fetch();

        if($u){
          $user_id = (int)$u['id'];
        } else {
          $role = employee_role_from($access_level, $department);
          $hash = password_hash($login_pass, PASSWORD_BCRYPT);
          $stI = $pdo->prepare("INSERT INTO users (name,email,role,password_hash,active,created_at) VALUES (?,?,?,?,1,NOW())");
          $stI->execute([$full_name, $login_email, $role, $hash]);
          $user_id = (int)$pdo->lastInsertId();
        }

        // vincula ao funcionário
        $stL = $pdo->prepare("UPDATE employees SET user_id=? WHERE id=?");
        $stL->execute([$user_id, $emp_id]);

          flash_set('success',"Funcionário atualizado. Usuário criado: {$login_email} | Senha: {$login_pass}");
      } else {
        flash_set('success','Funcionário atualizado.');
      }
    } else {
      // INSERT - Novo funcionário
      $st = $pdo->prepare("INSERT INTO employees (
        full_name, role_title, status,
        cpf, rg, birth_date, gender,
        email_work, email_personal, whatsapp,
        cep, street, number, neighborhood, city, state, complement,
        ctps_number, ctps_series, ctps_uf, pis, admission_date, contract_type,
        department, manager_id, access_level,
        photo_path, doc_path, active, created_at, updated_at
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())");
      $st->execute([
        $full_name,$role_title,$status,
        $cpf,$rg,($birth_date?:null),$gender,
        $email_work,$email_personal,$whatsapp,
        $cep,$street,$number,$neighborhood,$city,$state,$complement,
        $ctps_number,$ctps_series,$ctps_uf,$pis,($admission_date?:null),$contract_type,
        $department,($manager_id?:null),$access_level,
        $photo_path,$doc_path
      ]);
      $emp_id = (int)$pdo->lastInsertId();

      // criar usuário de acesso (opcional)
      if($create_user){
        if(!$login_email){
          flash_set('danger','Informe um e-mail corporativo para criar o usuário.');
          redirect($base.'/app.php?page=employees');
        }

        // gera senha se não veio
        if(!$login_pass){
          $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
          $login_pass = '';
          for($i=0;$i<10;$i++){ $login_pass .= $alphabet[random_int(0, strlen($alphabet)-1)]; }
        }

        $role = employee_role_from($access_level, $department);
        $hash = password_hash($login_pass, PASSWORD_BCRYPT);
        $stI = $pdo->prepare("INSERT INTO users (name,email,role,password_hash,active,created_at) VALUES (?,?,?,?,1,NOW())");
        $stI->execute([$full_name, $login_email, $role, $hash]);
        $user_id = (int)$pdo->lastInsertId();

        // vincula ao funcionário
        $stL = $pdo->prepare("UPDATE employees SET user_id=? WHERE id=?");
        $stL->execute([$user_id, $emp_id]);

        flash_set('success',"Funcionário cadastrado. Usuário criado: {$login_email} | Senha: {$login_pass}");
      } else {
        flash_set('success','Funcionário cadastrado.');
      }
    }
    redirect($base.'/app.php?page=employees');
  }
}

$managers = $pdo->query("SELECT id, full_name FROM employees WHERE active=1 ORDER BY full_name")->fetchAll();

$editing = null;
if($edit_id){
  $st = $pdo->prepare("SELECT * FROM employees WHERE id=?");
  $st->execute([$edit_id]);
  $editing = $st->fetch();
}

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT id, full_name, role_title, status, whatsapp, department, access_level, photo_path FROM employees WHERE active=1";
if($q){
  $sql .= " AND (full_name LIKE ? OR whatsapp LIKE ? OR cpf LIKE ? OR rg LIKE ?)";
  $like = '%'.$q.'%';
  $params = [$like,$like,$like,$like];
}
$sql .= " ORDER BY full_name LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function badge_status($s){
  $map = ['ativo'=>'success','ferias'=>'warning','desligado'=>'secondary'];
  $cls = $map[$s] ?? 'secondary';
  return "<span class='badge bg-$cls'>".h(mb_strtoupper($s))."</span>";
}
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center gap-2">
        <h5 class="m-0" style="font-weight:900">Funcionários</h5>
        <form class="d-flex gap-2" method="get" action="<?=h($base)?>/app.php">
          <input type="hidden" name="page" value="employees">
          <input class="form-control form-control-sm" name="q" value="<?=h($q)?>" placeholder="Buscar nome/whatsapp/cpf...">
          <button class="btn btn-sm btn-outline-secondary" type="submit">Buscar</button>
        </form>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Foto</th>
              <th>Nome</th>
              <th>Cargo</th>
              <th>Status</th>
              <th>WhatsApp</th>
              <th>Depto</th>
              <th>Acesso</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td style="width:64px">
                <?php if(!empty($r['photo_path'])): ?>
                  <img src="<?=h($base)?>/uploads/<?=h($r['photo_path'])?>" style="width:44px;height:44px;object-fit:cover;border-radius:999px">
                <?php else: ?>
                  <div style="width:44px;height:44px;border-radius:999px;background:#e9ecef"></div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:800"><?=h($r['full_name'])?></div>
              </td>
              <td><?=h($r['role_title'])?></td>
              <td><?=badge_status($r['status'])?></td>
              <td><?=h($r['whatsapp'])?></td>
              <td><?=h($r['department'])?></td>
              <td><?=h($r['access_level'])?></td>
              <td class="text-end">
                <?php if($r['access_level'] !== 'sem_acesso'): ?>
                  <a class="btn btn-sm btn-outline-info" href="<?=h($base)?>/app.php?page=user_permissions&id=<?=$r['id']?>" title="Gerenciar permissões">
                    🔐 Permissões
                  </a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-primary" href="<?=h($base)?>/app.php?page=employees&edit=<?=$r['id']?>">Editar</a>
                <a class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover funcionário?')" href="<?=h($base)?>/app.php?page=employees&del=<?=$r['id']?>">Excluir</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$rows): ?>
            <tr><td colspan="8" class="text-muted">Nenhum funcionário cadastrado.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card p-3">
      <h6 style="font-weight:900"><?= $editing ? 'Editar funcionário' : 'Cadastrar funcionário' ?></h6>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?=h($editing['id'] ?? 0)?>">

        <div class="d-flex align-items-center gap-3 mb-3">
          <div>
            <?php if(!empty($editing['photo_path'])): ?>
              <img src="<?=h($base)?>/uploads/<?=h($editing['photo_path'])?>" style="width:72px;height:72px;border-radius:999px;object-fit:cover">
            <?php else: ?>
              <div style="width:72px;height:72px;border-radius:999px;background:#e9ecef"></div>
            <?php endif; ?>
          </div>
          <div class="flex-fill">
            <label class="form-label">Foto (opcional)</label>
            <input class="form-control" type="file" name="photo" accept="image/*">
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label">Nome completo *</label>
          <input class="form-control" name="full_name" value="<?=h($editing['full_name'] ?? '')?>" required>
        </div>

        <div class="row g-2">
          <div class="col-7">
            <label class="form-label">Cargo *</label>
            <input class="form-control" name="role_title" value="<?=h($editing['role_title'] ?? '')?>" required>
          </div>
          <div class="col-5">
            <label class="form-label">Status *</label>
            <select class="form-select" name="status" required>
              <?php $stt = $editing['status'] ?? 'ativo'; ?>
              <option value="ativo" <?= $stt==='ativo'?'selected':'' ?>>Ativo</option>
              <option value="ferias" <?= $stt==='ferias'?'selected':'' ?>>Em Férias</option>
              <option value="desligado" <?= $stt==='desligado'?'selected':'' ?>>Desligado</option>
            </select>
          </div>
        </div>

        <hr>
        <div class="fw-bold mb-2">Informações pessoais</div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">CPF *</label>
            <input class="form-control" name="cpf" value="<?=h($editing['cpf'] ?? '')?>" data-cpf-cnpj required>
          </div>
          <div class="col-6">
            <label class="form-label">RG</label>
            <input class="form-control" name="rg" value="<?=h($editing['rg'] ?? '')?>">
          </div>
          <div class="col-6">
            <label class="form-label">Nascimento</label>
            <input class="form-control" type="date" name="birth_date" value="<?=h($editing['birth_date'] ?? '')?>">
          </div>
          <div class="col-6">
            <label class="form-label">Gênero</label>
            <input class="form-control" name="gender" value="<?=h($editing['gender'] ?? '')?>" placeholder="Ex: Masculino/Feminino/Outro">
          </div>
        </div>

        <hr>
        <div class="fw-bold mb-2">Contato</div>

        <div class="mb-2">
          <label class="form-label">WhatsApp *</label>
          <input class="form-control" name="whatsapp" value="<?=h($editing['whatsapp'] ?? '')?>" placeholder="(31) 9xxxx-xxxx" required>
        </div>
        <div class="mb-2">
          <label class="form-label">E-mail corporativo *</label>
          <input class="form-control" name="email_work" value="<?=h($editing['email_work'] ?? '')?>" type="email" required>
        </div>

        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-bold">Acesso ao sistema</div>
            <div class="form-check form-switch m-0">
              <input class="form-check-input" type="checkbox" name="create_user" value="1" id="create_user" <?= $editing ? '' : 'checked' ?>>
              <label class="form-check-label" for="create_user">Criar usuário</label>
            </div>
          </div>

          <div class="mt-2">
            <label class="form-label">E-mail de login</label>
            <input class="form-control" name="login_email" value="<?=h($editing['email_work'] ?? '')?>" placeholder="usa o corporativo por padrão">
          </div>

          <div class="mt-2">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label mb-0">Senha</label>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="genPass">Gerar</button>
            </div>
            <input class="form-control" name="login_pass" id="login_pass" placeholder="deixe em branco para gerar automaticamente">
            <div class="text-muted small mt-1">A senha aparece só uma vez no aviso “Funcionário cadastrado”.</div>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">E-mail pessoal</label>
          <input class="form-control" name="email_personal" value="<?=h($editing['email_personal'] ?? '')?>">
        </div>

        <hr>
        <div class="fw-bold mb-2">Endereço</div>

        <div class="row g-2">
          <div class="col-5">
            <label class="form-label">CEP</label>
            <input class="form-control" name="cep" id="emp_cep" value="<?=h($editing['cep'] ?? '')?>">
          </div>
          <div class="col-7">
            <label class="form-label">Rua</label>
            <input class="form-control" name="street" id="emp_street" value="<?=h($editing['street'] ?? '')?>">
          </div>
          <div class="col-4">
            <label class="form-label">Número</label>
            <input class="form-control" name="number" value="<?=h($editing['number'] ?? '')?>">
          </div>
          <div class="col-8">
            <label class="form-label">Bairro</label>
            <input class="form-control" name="neighborhood" id="emp_neighborhood" value="<?=h($editing['neighborhood'] ?? '')?>">
          </div>
          <div class="col-8">
            <label class="form-label">Cidade</label>
            <input class="form-control" name="city" id="emp_city" value="<?=h($editing['city'] ?? '')?>">
          </div>
          <div class="col-4">
            <label class="form-label">UF</label>
            <input class="form-control" name="state" id="emp_state" value="<?=h($editing['state'] ?? '')?>">
          </div>
          <div class="col-12">
            <label class="form-label">Complemento</label>
            <input class="form-control" name="complement" value="<?=h($editing['complement'] ?? '')?>">
          </div>
        </div>

        <hr>
        <div class="fw-bold mb-2">Dados profissionais / CLT</div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">CTPS Nº</label>
            <input class="form-control" name="ctps_number" value="<?=h($editing['ctps_number'] ?? '')?>">
          </div>
          <div class="col-4">
            <label class="form-label">Série</label>
            <input class="form-control" name="ctps_series" value="<?=h($editing['ctps_series'] ?? '')?>">
          </div>
          <div class="col-2">
            <label class="form-label">UF</label>
            <input class="form-control" name="ctps_uf" value="<?=h($editing['ctps_uf'] ?? '')?>">
          </div>
          <div class="col-6">
            <label class="form-label">PIS/PASEP</label>
            <input class="form-control" name="pis" value="<?=h($editing['pis'] ?? '')?>">
          </div>
          <div class="col-6">
            <label class="form-label">Admissão</label>
            <input class="form-control" type="date" name="admission_date" value="<?=h($editing['admission_date'] ?? '')?>">
          </div>
          <div class="col-12">
            <label class="form-label">Tipo de contrato</label>
            <?php $ct = $editing['contract_type'] ?? 'clt'; ?>
            <select class="form-select" name="contract_type">
              <option value="clt" <?= $ct==='clt'?'selected':'' ?>>CLT</option>
              <option value="pj" <?= $ct==='pj'?'selected':'' ?>>PJ</option>
              <option value="estagio" <?= $ct==='estagio'?'selected':'' ?>>Estágio</option>
              <option value="temporario" <?= $ct==='temporario'?'selected':'' ?>>Temporário</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Contrato/Carteira (PDF)</label>
            <input class="form-control" type="file" name="doc_pdf" accept="application/pdf">
            <?php if(!empty($editing['doc_path'])): ?>
              <div class="small mt-1">
                <a target="_blank" href="<?=h($base)?>/uploads/<?=h($editing['doc_path'])?>">Ver PDF atual</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <hr>
        <div class="fw-bold mb-2">Hierarquia e permissões</div>

        <div class="row g-2">
          <div class="col-7">
            <label class="form-label">Departamento</label>
            <input class="form-control" name="department" value="<?=h($editing['department'] ?? '')?>" placeholder="Vendas, Suporte, Financeiro...">
          </div>
          <div class="col-5">
            <label class="form-label">Gestor direto</label>
            <select class="form-select" name="manager_id">
              <option value="">—</option>
              <?php foreach($managers as $m): ?>
                <option value="<?=$m['id']?>" <?= (int)($editing['manager_id'] ?? 0)==(int)$m['id']?'selected':'' ?>>
                  <?=h($m['full_name'])?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Nível de acesso</label>
            <?php $al = $editing['access_level'] ?? 'operacional'; ?>
            <select class="form-select" name="access_level">
              <option value="admin" <?= $al==='admin'?'selected':'' ?>>Admin</option>
              <option value="operacional" <?= $al==='operacional'?'selected':'' ?>>Operacional</option>
              <option value="visualizacao" <?= $al==='visualizacao'?'selected':'' ?>>Visualização</option>
            </select>
          </div>
        </div>

        <button class="btn btn-primary w-100 mt-3" type="submit">Salvar</button>
        <?php if($editing): ?>
          <a class="btn btn-outline-secondary w-100 mt-2" href="<?=h($base)?>/app.php?page=employees">Cancelar</a>
        <?php endif; ?>
      </form>
      
      <script src="<?= h($base) ?>/assets/js/cpf_cnpj.js"></script>
    </div>
  </div>
</div>

<script>
async function viaCep(cep){
  cep = (cep||'').replace(/\D/g,'');
  if(cep.length!==8) return null;
  const r = await fetch('https://viacep.com.br/ws/'+cep+'/json/');
  const j = await r.json();
  if(j.erro) return null;
  return j;
}
document.getElementById('emp_cep')?.addEventListener('blur', async (e)=>{
  const j = await viaCep(e.target.value);
  if(!j) return;
  document.getElementById('emp_street').value = j.logradouro || '';
  document.getElementById('emp_neighborhood').value = j.bairro || '';
  document.getElementById('emp_city').value = j.localidade || '';
  document.getElementById('emp_state').value = j.uf || '';
});

// gerar senha
const genBtn = document.getElementById('genPass');
const passInp = document.getElementById('login_pass');
if(genBtn && passInp){
  genBtn.addEventListener('click', ()=>{
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    let p = '';
    for(let i=0;i<10;i++) p += chars[Math.floor(Math.random()*chars.length)];
    passInp.value = p;
  });
}

// habilitar/desabilitar campos do login
const sw = document.getElementById('create_user');
const loginEmail = document.querySelector('input[name="login_email"]');
if(sw && loginEmail && passInp){
  const sync = ()=>{
    const on = sw.checked;
    loginEmail.disabled = !on;
    passInp.disabled = !on;
    genBtn.disabled = !on;
  };
  sw.addEventListener('change', sync);
  sync();
}

</script>
