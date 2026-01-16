<?php
// CONFIRMAÇÃO PÚBLICA DE RETIRADA - Cliente confirma que retirou
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/utils.php';

$token = trim($_GET['token'] ?? '');
$error = null;
$success = false;
$os = null;

if ($token && strlen($token) === 32) {
    // Busca OS pelo token
    $st = $pdo->prepare("
        SELECT o.id, o.code, o.status, c.name AS client_name
        FROM os o
        JOIN clients c ON c.id = o.client_id
        WHERE o.delivery_notes LIKE ?
        AND o.status = 'disponivel'
    ");
    $st->execute(["%TOKEN_RETIRADA:$token%"]);
    $os = $st->fetch();
    
    if (!$os) {
        $error = "Link inválido ou OS já foi finalizada.";
    }
    
    // Confirmação
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $os) {
        $confirm = $_POST['confirm'] ?? '';
        if ($confirm === 'sim') {
            // Finaliza a OS
            $pdo->prepare("UPDATE os SET status = 'finalizada' WHERE id = ?")->execute([$os['id']]);
            $success = true;
        }
    }
} else {
    $error = "Token inválido.";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Retirada - Mídia Certa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .confirmation-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        .logo {
            width: 200px;
            margin: 0 auto 1.5rem;
            display: block;
        }
        .success-icon {
            font-size: 5rem;
            animation: bounce 1s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body>
    <div class="confirmation-card p-4">
        <img src="assets/images/midia-certa-432x107.png" alt="Mídia Certa" class="logo">
        
        <?php if ($error): ?>
            <div class="text-center">
                <div style="font-size: 4rem;">❌</div>
                <h4 class="mt-3" style="font-weight: 900;">Erro</h4>
                <p class="text-muted"><?= h($error) ?></p>
            </div>
        <?php elseif ($success): ?>
            <div class="text-center">
                <div class="success-icon">✅</div>
                <h4 class="mt-3" style="font-weight: 900; color: #059669;">Retirada Confirmada!</h4>
                <p class="text-muted">Obrigado por confirmar a retirada da OS <strong>#<?= h($os['code']) ?></strong>.</p>
                <p class="text-muted">Volte sempre! 😊</p>
                <hr>
                <p class="small text-muted mb-0">
                    <strong>Mídia Certa</strong><br>
                    Soluções Gráficas
                </p>
            </div>
        <?php else: ?>
            <div class="text-center mb-4">
                <div style="font-size: 4rem;">📦</div>
                <h4 class="mt-3" style="font-weight: 900;">Confirmar Retirada</h4>
                <p class="text-muted">OS <strong>#<?= h($os['code']) ?></strong></p>
            </div>
            
            <div class="alert alert-info">
                <strong>Cliente:</strong> <?= h($os['client_name']) ?>
            </div>
            
            <form method="post">
                <p class="text-center mb-4">
                    <strong>Você confirma que retirou este material no nosso balcão?</strong>
                </p>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="confirm" value="sim" class="btn btn-success btn-lg">
                        ✅ Sim, Confirmo a Retirada
                    </button>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        ← Voltar
                    </a>
                </div>
            </form>
            
            <hr class="my-4">
            <p class="small text-muted text-center mb-0">
                <strong>Dúvidas?</strong> Entre em contato conosco.
            </p>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
