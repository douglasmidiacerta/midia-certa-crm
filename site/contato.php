<?php
/**
 * Página de Contato - Mídia Certa Gráfica
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/utils.php';

$base = $config['base_path'] ?? '';
$branding = require_once __DIR__ . '/../config/branding.php';

// Processar formulário
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    
    if($nome && $email && $mensagem) {
        // Aqui você pode enviar email, salvar no banco, etc.
        // Por enquanto, vamos apenas mostrar sucesso
        $_SESSION['contato_sucesso'] = true;
        header('Location: contato.php');
        exit;
    }
}

$sucesso = isset($_SESSION['contato_sucesso']);
unset($_SESSION['contato_sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contato - <?= h($branding['nome_empresa']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= h($base) ?>/assets/css/app.css">
    <style>
        :root {
            --cor-primaria: <?= $branding['cores']['primaria'] ?>;
        }
        
        .page-header {
            background: <?= $branding['gradiente_principal'] ?>;
            color: white;
            padding: 60px 0 40px;
        }
        
        .contact-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php include __DIR__ . '/partials/navbar.php'; ?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="display-4 fw-bold">Entre em Contato</h1>
        <p class="lead mb-0">Estamos prontos para atender você!</p>
    </div>
</section>

<!-- Contato -->
<section class="py-5">
    <div class="container">
        <?php if($sucesso): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <strong>✅ Mensagem enviada com sucesso!</strong> Em breve entraremos em contato.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="contact-card">
                    <h3 class="fw-bold mb-4">Envie sua Mensagem</h3>
                    
                    <form method="post">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nome *</label>
                                <input type="text" class="form-control" name="nome" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Telefone *</label>
                                <input type="tel" class="form-control" name="telefone" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-bold">E-mail *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-bold">Mensagem *</label>
                                <textarea class="form-control" name="mensagem" rows="5" required></textarea>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg">Enviar Mensagem</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="contact-card mb-3">
                    <h5 class="fw-bold mb-3">📞 Telefone</h5>
                    <p>(11) 9999-9999</p>
                </div>
                
                <div class="contact-card mb-3">
                    <h5 class="fw-bold mb-3">📧 E-mail</h5>
                    <p>contato@midiacerta.com.br</p>
                </div>
                
                <div class="contact-card mb-3">
                    <h5 class="fw-bold mb-3">📍 Endereço</h5>
                    <p>Rua Exemplo, 123<br>
                    Bairro - Cidade/UF<br>
                    CEP 00000-000</p>
                </div>
                
                <div class="contact-card">
                    <h5 class="fw-bold mb-3">🕐 Horário</h5>
                    <p>Segunda a Sexta<br>
                    08:00 às 18:00</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<?php include __DIR__ . '/partials/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
