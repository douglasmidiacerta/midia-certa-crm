<?php
// Lightweight schema migration helper.
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

function mc_migrate(PDO $pdo): void {
  // 1) Ensure all base tables exist (safe due to IF NOT EXISTS)
  mc_run_schema_sql($pdo, __DIR__ . '/../database/schema.sql');

  // 2) Columns added during recent iterations
  mc_ensure_column($pdo, 'cash_moves', 'move_type', "VARCHAR(20) NOT NULL DEFAULT 'entry'");
  mc_ensure_column($pdo, 'audit_logs', 'details', "TEXT NULL");

  // Delivery fields
  mc_ensure_column($pdo, 'os', 'delivery_cost', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
  mc_ensure_column($pdo, 'os', 'delivery_pay_to', "VARCHAR(30) NOT NULL DEFAULT 'internal'");
  mc_ensure_column($pdo, 'os', 'delivery_pay_mode', "VARCHAR(30) NOT NULL DEFAULT 'now'");
}
