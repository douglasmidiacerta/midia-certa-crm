<?php
// Enhanced schema migration system with automatic migration tracking
// Goal: keep the system running even if the DB was created with an older schema.

function mc_table_exists(PDO $pdo, string $table): bool {
  $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?");
  $st->execute([$db, $table]);
  return (int)$st->fetchColumn() > 0;
}

function mc_column_exists(PDO $pdo, string $table, string $column): bool {
  $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?");
  $st->execute([$db, $table, $column]);
  return (int)$st->fetchColumn() > 0;
}

function mc_ensure_column(PDO $pdo, string $table, string $column, string $definition_sql): void {
  if (!mc_table_exists($pdo, $table)) return;
  if (mc_column_exists($pdo, $table, $column)) return;
  $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition_sql");
}

function mc_run_schema_sql(PDO $pdo, string $schema_file): void {
  if (!is_file($schema_file)) return;
  $sql = file_get_contents($schema_file);
  // Remove comments
  $sql = preg_replace('/^\s*--.*$/m', '', $sql);
  $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
  $parts = array_filter(array_map('trim', explode(';', $sql)));
  foreach ($parts as $stmt) {
    try {
      $pdo->exec($stmt);
    } catch (Throwable $e) {
      // ignore: this is best-effort
    }
  }
}

/**
 * Check if a migration has already been executed
 */
function mc_migration_executed(PDO $pdo, string $migration_file): bool {
  if (!mc_table_exists($pdo, 'migrations')) return false;
  
  $st = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration_file = ? AND status = 'success'");
  $st->execute([$migration_file]);
  return (int)$st->fetchColumn() > 0;
}

/**
 * Mark a migration as executed
 */
function mc_mark_migration(PDO $pdo, string $migration_file, string $status = 'success', ?string $error = null): void {
  if (!mc_table_exists($pdo, 'migrations')) return;
  
  $st = $pdo->prepare("INSERT INTO migrations (migration_file, status, error_message) VALUES (?, ?, ?) 
                       ON DUPLICATE KEY UPDATE status = ?, error_message = ?, executed_at = CURRENT_TIMESTAMP");
  $st->execute([$migration_file, $status, $error, $status, $error]);
}

/**
 * Run a single migration file with error handling and logging
 */
function mc_run_migration_file(PDO $pdo, string $file_path): bool {
  $migration_file = basename($file_path);
  
  // Skip if already executed
  if (mc_migration_executed($pdo, $migration_file)) {
    return true;
  }
  
  try {
    mc_run_schema_sql($pdo, $file_path);
    mc_mark_migration($pdo, $migration_file, 'success');
    return true;
  } catch (Throwable $e) {
    mc_mark_migration($pdo, $migration_file, 'failed', $e->getMessage());
    error_log("Migration failed: {$migration_file} - {$e->getMessage()}");
    return false;
  }
}

/**
 * Run all pending migrations from database/updates/ directory
 */
function mc_run_all_migrations(PDO $pdo): array {
  $updates_dir = __DIR__ . '/../database/updates';
  $results = [
    'executed' => [],
    'skipped' => [],
    'failed' => []
  ];
  
  if (!is_dir($updates_dir)) {
    return $results;
  }
  
  // Get all SQL files, sorted alphabetically
  $files = glob($updates_dir . '/*.sql');
  sort($files);
  
  foreach ($files as $file) {
    $migration_file = basename($file);
    
    if (mc_migration_executed($pdo, $migration_file)) {
      $results['skipped'][] = $migration_file;
      continue;
    }
    
    if (mc_run_migration_file($pdo, $file)) {
      $results['executed'][] = $migration_file;
    } else {
      $results['failed'][] = $migration_file;
    }
  }
  
  return $results;
}

function mc_migrate(PDO $pdo): void {
  // 1) Ensure all base tables exist (safe due to IF NOT EXISTS)
  mc_run_schema_sql($pdo, __DIR__ . '/../database/schema.sql');

  // 2) Create migrations table if it doesn't exist (must be first!)
  mc_run_schema_sql($pdo, __DIR__ . '/../database/updates/create_migrations_table.sql');

  // 3) Run all pending migrations automatically from database/updates/
  mc_run_all_migrations($pdo);

  // 4) Legacy: Columns added during recent iterations (kept for backward compatibility)
  mc_ensure_column($pdo, 'cash_moves', 'move_type', "VARCHAR(20) NOT NULL DEFAULT 'entry'");
  mc_ensure_column($pdo, 'audit_logs', 'details', "TEXT NULL");

  // Delivery fields
  mc_ensure_column($pdo, 'os', 'delivery_cost', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
  mc_ensure_column($pdo, 'os', 'delivery_pay_to', "VARCHAR(30) NOT NULL DEFAULT 'internal'");
  mc_ensure_column($pdo, 'os', 'delivery_pay_mode', "VARCHAR(30) NOT NULL DEFAULT 'now'");
}
