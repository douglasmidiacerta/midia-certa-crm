<?php
// Usa config.local.php se existir (desenvolvimento), senão usa config.php (produção)
$configFile = file_exists(__DIR__ . '/config.local.php') 
  ? __DIR__ . '/config.local.php' 
  : __DIR__ . '/config.php';
$config = require $configFile;
$db = $config['db'];
$dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);

// Migração automática (evita tela branca por tabela/coluna faltando)
// Roda 1x por sessão.
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['schema_ok'])) {
  try {
    require_once __DIR__ . '/migrate.php';
    mc_migrate($pdo);
  } catch (Throwable $e) {
    // Se falhar (permissões/limites), o sistema ainda pode funcionar se o schema já estiver correto.
  }
  $_SESSION['schema_ok'] = 1;
}
