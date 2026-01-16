<?php
/**
 * Processar Pedido do Site - Cria O.S. com status "pedido_pendente"
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';
require_once __DIR__ . '/../config/client_auth.php';

// Verificar se cliente está logado
if(!is_client_logged_in()) {
    header('Location: ../client_login.php');
    exit;
}

$cliente_id = get_client_id();

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produtos.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Dados do pedido
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $quantidade = isset($_POST['quantidade']) ? (float)$_POST['quantidade'] : 1;
    $width = isset($_POST['width']) ? (float)$_POST['width'] : null;
    $height = isset($_POST['height']) ? (float)$_POST['height'] : null;
    $prazo_desejado = !empty($_POST['prazo_desejado']) ? $_POST['prazo_desejado'] : null;
    $pagamento_preferencial = trim($_POST['pagamento_preferencial'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Buscar produto
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? AND active = 1");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch();
    
    if(!$produto) {
        throw new Exception('Produto não encontrado');
    }
    
    // Buscar cliente
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND active = 1");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    
    if(!$cliente) {
        throw new Exception('Cliente não encontrado');
    }
    
    // Calcular preço
    if($produto['is_sqm_product']) {
        if(!$width || !$height) {
            throw new Exception('Informe largura e altura');
        }
        $sqm = $width * $height;
        $preco_unitario = $produto['price_per_sqm'] * $sqm;
        $quantidade = 1; // Para m², quantidade é sempre 1
    } else {
        $preco_unitario = $produto['price'];
        $sqm = null;
    }
    
    $preco_total = $preco_unitario * $quantidade;
    
    // Buscar próximo número de O.S.
    $next_num = $pdo->query("SELECT COALESCE(MAX(os_number), 0) + 1 FROM os")->fetchColumn();
    $os_code = str_pad($next_num, 6, '0', STR_PAD_LEFT);
    
    // Criar O.S. com status "pedido_pendente"
    $stmt = $pdo->prepare("
        INSERT INTO os (
            os_number, code, client_id, seller_user_id, 
            os_type, status, origem, 
            pagamento_preferencial, prazo_desejado, 
            notes, created_at
        ) VALUES (?, ?, ?, ?, ?, 'pedido_pendente', 'site', ?, ?, ?, NOW())
    ");
    
    // Usar primeiro usuário como seller (será ajustado depois)
    $primeiro_vendedor = $pdo->query("SELECT id FROM users WHERE active=1 ORDER BY id LIMIT 1")->fetchColumn();
    
    $stmt->execute([
        $next_num,
        $os_code,
        $cliente_id,
        $primeiro_vendedor,
        $produto['type'],
        $pagamento_preferencial ?: null,
        $prazo_desejado,
        $observacoes
    ]);
    
    $os_id = $pdo->lastInsertId();
    
    // Adicionar linha do produto
    $stmt = $pdo->prepare("
        INSERT INTO os_lines (
            os_id, item_id, qty, unit_price, unit_cost, 
            width, height, sqm, notes, created_at
        ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, '', NOW())
    ");
    
    $stmt->execute([
        $os_id,
        $produto_id,
        $quantidade,
        $preco_unitario,
        $width,
        $height,
        $sqm
    ]);
    
    // Upload de arquivo (se houver)
    if(isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/os_' . $os_id;
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = $_FILES['arquivo'];
        $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
        $new_name = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $file_path = $upload_dir . '/' . $new_name;
        
        if(move_uploaded_file($file_info['tmp_name'], $file_path)) {
            // Registrar arquivo
            $stmt = $pdo->prepare("
                INSERT INTO os_files (
                    os_id, kind, file_path, original_name, 
                    mime, size, created_at
                ) VALUES (?, 'arte_pdf', ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $os_id,
                'uploads/os_' . $os_id . '/' . $new_name,
                $file_info['name'],
                $file_info['type'],
                $file_info['size']
            ]);
        }
    }
    
    // Registrar auditoria
    audit($pdo, 'os', 'create', $os_id, [
        'origem' => 'site',
        'cliente' => $cliente['name'],
        'produto' => $produto['name'],
        'status' => 'pedido_pendente'
    ]);
    
    $pdo->commit();
    
    // Redirecionar para portal com mensagem de sucesso
    $_SESSION['flash_message'] = "Pedido #{$os_code} enviado com sucesso! Nossa equipe entrará em contato em breve.";
    $_SESSION['flash_type'] = 'success';
    header('Location: ../client_portal.php');
    exit;
    
} catch(Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = "Erro ao processar pedido: " . $e->getMessage();
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . $base . '/site/produto.php?id=' . $produto_id);
    exit;
}
