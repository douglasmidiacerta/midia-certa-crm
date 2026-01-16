<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function is_logged_in(): bool { return !empty($_SESSION['user']); }
function require_login() { if(!is_logged_in()){ header("Location: index.php"); exit; } }

function user(){ return $_SESSION['user'] ?? null; }
function user_id(){ $u=user(); return $u ? (int)$u['id'] : 0; }
function user_role(){ $u=user(); return $u ? ($u['role'] ?? '') : ''; }

function can_admin(): bool { return user_role()==='admin'; }
function can_finance(): bool { return in_array(user_role(), ['admin','financeiro'], true); }
function can_sales(): bool { return in_array(user_role(), ['admin','vendas'], true); }

function deny(){ http_response_code(403); echo "Acesso negado."; exit; }

function require_role($roles){
  $r = user_role();
  $roles = is_array($roles) ? $roles : [$roles];
  if(!in_array($r,$roles,true)) deny();
}
