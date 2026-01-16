<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/utils.php';
$config = require __DIR__ . '/config/config.php';
$base = $config['base_path'];

if (is_logged_in()) redirect($base.'/app.php?page=dashboard');

// If no users, redirect to installer
$c = (int)$pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
if($c === 0){
  redirect($base.'/install.php');
}

if(($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST'){
  $email = trim($_POST['email'] ?? '');
  $pass = $_POST['password'] ?? '';
  $st = $pdo->prepare("SELECT id,name,email,role,password_hash FROM users WHERE email=? AND active=1 LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();
  if($u && password_verify($pass, $u['password_hash'])){
    $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
    flash_set('success','Bem-vindo!');
    redirect($base.'/app.php?page=dashboard');
  } else {
    flash_set('danger','Login inválido.');
  }
}
$f = flash_get();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($config['app_name']) ?> - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{background:#0b1f3a; min-height:100vh; display:flex; align-items:center; justify-content:center;}
  .card{border:0; border-radius:18px; width:min(460px,92vw);} </style>
</head>
<body>
  <div class="card p-4">
    <div class="d-flex align-items-center gap-2 mb-3">
      <img src="<?= h($base) ?>/assets/logo.svg" style="height:34px" alt="Logo">
      <div>
        <div style="font-weight:800"><?= h($config['app_name']) ?></div>
        <div class="text-muted" style="font-size:.9rem">Acesso ao sistema</div>
      </div>
    </div>
    <?php if($f): ?><div class="alert alert-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div><?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="form-control" name="email" type="email" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Senha</label>
        <input class="form-control" name="password" type="password" required>
      </div>
      <button class="btn btn-primary w-100">Entrar</button>
    </form>
  </div>
</body>
</html>
