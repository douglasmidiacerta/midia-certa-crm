<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function now(){ return date('Y-m-d H:i:s'); }
function date_br($d){
  if(!$d) return '';
  $t = strtotime($d);
  return $t ? date('d/m/Y', $t) : $d;
}
function money($v){ return 'R$ '.number_format((float)$v, 2, ',', '.'); }
function redirect($url){ header("Location: $url"); exit; }

function flash_set($type, $msg){
  if (session_status() === PHP_SESSION_NONE) session_start();
  $_SESSION['flash'] = ['type'=>$type, 'msg'=>$msg];
}
function flash_get(){
  if (session_status() === PHP_SESSION_NONE) session_start();
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}

function flash_html(){
  $f = flash_get();
  if(!$f) return '';
  $type = $f['type'] ?? 'info';
  $msg  = $f['msg'] ?? '';

  $map = [
    'success' => 'success',
    'ok'      => 'success',
    'error'   => 'danger',
    'danger'  => 'danger',
    'warning' => 'warning',
    'info'    => 'info',
  ];
  $bt = $map[$type] ?? 'info';

  return '<div class="alert alert-'.$bt.' alert-dismissible fade show" role="alert">'
    . h($msg)
    . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
    . '</div>';
}


function require_file($field, $config){
  if(!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK){
    return [false, 'Comprovante obrigatório.'];
  }
  $max = (int)$config['upload_max_mb'] * 1024 * 1024;
  if($_FILES[$field]['size'] > $max){
    return [false, 'Arquivo muito grande (máx '.$config['upload_max_mb'].'MB).'];
  }
  return [true,''];
}

function save_upload($field, $config){
  $dir = $config['upload_dir'];
  if(!is_dir($dir)) @mkdir($dir, 0775, true);
  $name = $_FILES[$field]['name'] ?? 'file';
  $ext = pathinfo($name, PATHINFO_EXTENSION);
  $ext = preg_replace('/[^a-zA-Z0-9]/','', $ext);
  $fname = bin2hex(random_bytes(8)).($ext?'.'.$ext:'');
  $dest = rtrim($dir,'/').'/'.$fname;
  if(!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)){
    throw new RuntimeException('Falha ao salvar upload.');
  }
  return $fname;
}

function audit($pdo, $action, $entity, $entity_id, $details=null){
  $uid = $_SESSION['user']['id'] ?? null;
  $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, details, created_at) VALUES (?,?,?,?,?,?)");
  $stmt->execute([$uid, $action, $entity, $entity_id, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, now()]);
}


function full_url(string $path): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? '';
  if(!$host) return $path;
  return $scheme.'://'.$host.$path;
}