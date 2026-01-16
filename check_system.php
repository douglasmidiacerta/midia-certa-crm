<?php
/**
 * Script para verificar erros do sistema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Verificação de Erros</title>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} h2{border-bottom:2px solid #333;padding-bottom:10px;}</style>";
echo "</head><body>";

echo "<h1>🔍 Verificação de Erros - Mídia Certa CRM</h1>";

// 1. Verificar arquivos de configuração
echo "<h2>1. Arquivos de Configuração</h2>";

$config_files = [
    'config/config.php',
    'config/db.php',
    'config/auth.php',
    'config/migrate.php'
];

foreach ($config_files as $file) {
    if (file_exists($file)) {
        echo "✅ <span class='success'>$file existe</span><br>";
        // Tentar incluir para ver se tem erro
        try {
            if ($file === 'config/db.php') {
                echo "   → Pulando db.php (conecta ao banco)<br>";
            } else {
                include_once $file;
                echo "   → Sem erros de sintaxe<br>";
            }
        } catch (Throwable $e) {
            echo "   ❌ <span class='error'>ERRO: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "❌ <span class='error'>$file NÃO EXISTE</span><br>";
    }
}

// 2. Verificar conexão com banco
echo "<h2>2. Conexão com Banco de Dados</h2>";
try {
    require_once 'config/db.php';
    echo "✅ <span class='success'>Conectado ao banco com sucesso!</span><br>";
    echo "   → Banco: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "<br>";
} catch (Throwable $e) {
    echo "❌ <span class='error'>ERRO: " . $e->getMessage() . "</span><br>";
}

// 3. Verificar tabelas importantes
if (isset($pdo)) {
    echo "<h2>3. Tabelas do Banco</h2>";
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "✅ <span class='success'>Total de tabelas: " . count($tables) . "</span><br>";
        
        $required_tables = ['users', 'clients', 'os', 'items', 'migrations'];
        foreach ($required_tables as $table) {
            if (in_array($table, $tables)) {
                echo "   ✅ $table<br>";
            } else {
                echo "   ❌ <span class='error'>$table NÃO EXISTE</span><br>";
            }
        }
    } catch (Throwable $e) {
        echo "❌ <span class='error'>ERRO: " . $e->getMessage() . "</span><br>";
    }
}

// 4. Verificar sessão e autenticação
echo "<h2>4. Sistema de Autenticação</h2>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "✅ <span class='success'>Sessão ativa - User ID: {$_SESSION['user_id']}</span><br>";
    echo "   → Nome: " . ($_SESSION['username'] ?? 'N/A') . "<br>";
    echo "   → Perfil: " . ($_SESSION['profile'] ?? 'N/A') . "<br>";
} else {
    echo "⚠️ <span class='warning'>Nenhuma sessão ativa (normal se não estiver logado)</span><br>";
}

// 5. Verificar permissões de pastas
echo "<h2>5. Permissões de Pastas</h2>";
$folders = ['uploads', 'uploads/os_1', 'uploads/carousel'];
foreach ($folders as $folder) {
    if (is_dir($folder)) {
        if (is_writable($folder)) {
            echo "✅ <span class='success'>$folder - Gravável</span><br>";
        } else {
            echo "❌ <span class='error'>$folder - SEM PERMISSÃO DE ESCRITA</span><br>";
        }
    } else {
        echo "⚠️ <span class='warning'>$folder - Não existe</span><br>";
    }
}

// 6. Verificar páginas com erro
echo "<h2>6. Testar Páginas Principais</h2>";
echo "<p>Clique para testar cada página:</p>";
echo "<ul>";
echo "<li><a href='pages/dashboard.php' target='_blank'>Dashboard</a></li>";
echo "<li><a href='pages/os.php' target='_blank'>O.S</a></li>";
echo "<li><a href='pages/items.php' target='_blank'>Produtos</a></li>";
echo "<li><a href='pages/fin_receber.php' target='_blank'>A Receber</a></li>";
echo "<li><a href='pages/fin_pagar.php' target='_blank'>A Pagar</a></li>";
echo "<li><a href='pages/marketing_site.php' target='_blank'>Gerenciar Site</a></li>";
echo "<li><a href='site/index.php' target='_blank'>Site Público</a></li>";
echo "<li><a href='client_portal.php' target='_blank'>Portal do Cliente</a></li>";
echo "</ul>";

// 7. Informações do PHP
echo "<h2>7. Informações do PHP</h2>";
echo "Versão PHP: " . phpversion() . "<br>";
echo "Limite de memória: " . ini_get('memory_limit') . "<br>";
echo "Upload máximo: " . ini_get('upload_max_filesize') . "<br>";
echo "Tempo máximo: " . ini_get('max_execution_time') . "s<br>";

// 8. Verificar error_log
echo "<h2>8. Últimos Erros do Log</h2>";
if (file_exists('error_log')) {
    $log = file_get_contents('error_log');
    $lines = explode("\n", $log);
    $last_lines = array_slice($lines, -20);
    echo "<pre style='background:#f0f0f0;padding:10px;overflow:auto;max-height:300px;'>";
    echo htmlspecialchars(implode("\n", $last_lines));
    echo "</pre>";
} else {
    echo "⚠️ <span class='warning'>Arquivo error_log não encontrado</span><br>";
}

echo "<hr>";
echo "<p><strong>✅ Verificação completa!</strong></p>";
echo "<p>Acesse as páginas acima para ver os erros específicos.</p>";

echo "</body></html>";
