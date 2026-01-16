<?php
if (!function_exists('get_config') && isset($pdo)) {
  $site_config = [];
  try {
    $configs = $pdo->query("SELECT config_key, config_value FROM site_config")->fetchAll();
    foreach ($configs as $cfg) {
      $site_config[$cfg['config_key']] = $cfg['config_value'];
    }
  } catch (Exception $e) {
    $site_config = [];
  }

  function get_config($key, $default = '') {
    global $site_config;
    return $site_config[$key] ?? $default;
  }
}

$company_name = $branding['nome_empresa'] ?? 'Empresa';
$company_email = get_config('footer_email', 'contato@midiacerta.com.br');
$company_phone = get_config('footer_telefone', '(11) 9999-9999');
$company_address = get_config('footer_endereco', 'Endereço da gráfica');
$company_cnpj = get_config('footer_cnpj', '');

$default_privacy = "A {$company_name} coleta apenas os dados necessários para atender seus pedidos e responder contatos. Os dados podem incluir nome, telefone, email e informações do pedido. Utilizamos esses dados para orçamento, produção, entrega e suporte. Não vendemos dados pessoais. Você pode solicitar atualização ou exclusão pelo email {$company_email}.";
$default_terms = "Ao solicitar orçamento ou contratar serviços da {$company_name}, o cliente declara que as informações e artes enviadas são de sua responsabilidade. Alterações após aprovação podem gerar custos adicionais. Prazos podem variar conforme aprovação e produção. Em caso de dúvidas, fale conosco em {$company_phone}.";
$default_cookies = "Usamos cookies para melhorar a navegação, medir desempenho e personalizar sua experiência. Você pode gerenciar cookies no seu navegador. Ao continuar, você concorda com o uso de cookies.";

$privacy_text = get_config('privacy_policy', $default_privacy);
$terms_text = get_config('terms_policy', $default_terms);
$cookies_text = get_config('cookies_policy', $default_cookies);
?>

<footer class="bg-dark text-white py-5 mt-5" style="color:#f9fafb;">
  <style>
    footer .text-muted { color: #cbd5e1 !important; }
  </style>
  <div class="container">
    <div class="row">
      <div class="col-md-4 mb-4">
        <h5 class="fw-bold mb-3"><?= h($branding['nome_empresa']) ?></h5>
        <p><?= h($branding['slogan']) ?></p>
        <p style="color:#e5e7eb;">Gráfica rápida e de qualidade para suas necessidades profissionais.</p>
        <?php if (get_config('company_legal_name', '')): ?>
          <p class="mb-1" style="color:#e5e7eb;"><?= h(get_config('company_legal_name', '')) ?></p>
        <?php endif; ?>
        <?php if (get_config('footer_cnpj', '')): ?>
          <p class="mb-0" style="color:#e5e7eb;">CNPJ: <?= h(get_config('footer_cnpj', '')) ?></p>
        <?php endif; ?>
      </div>
      
      <div class="col-md-4 mb-4">
        <h5 class="fw-bold mb-3">Links Rápidos</h5>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="index.php" class="text-white text-decoration-none">Home</a></li>
          <li class="mb-2"><a href="produtos.php" class="text-white text-decoration-none">Produtos</a></li>
          <li class="mb-2"><a href="contato.php" class="text-white text-decoration-none">Contato</a></li>
          <li class="mb-2"><a href="artigos.php" class="text-white text-decoration-none">Artigos</a></li>
          <li class="mb-2"><a href="../client_portal.php" class="text-white text-decoration-none">Portal do Cliente</a></li>
          <li class="mb-2"><a href="../client_login.php" class="text-white text-decoration-none">Login</a></li>
        </ul>
      </div>
      
      <div class="col-md-4 mb-4" style="color:#e5e7eb;">
        <h5 class="fw-bold mb-3">Contato</h5>
        <p class="mb-2">
          <svg width="16" height="16" fill="currentColor" class="me-2" viewBox="0 0 16 16">
            <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328z"/>
          </svg>
          <?= h(get_config('footer_telefone', '(11) 9999-9999')) ?>
        </p>
        <p class="mb-2">
          <svg width="16" height="16" fill="currentColor" class="me-2" viewBox="0 0 16 16">
            <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555ZM0 4.697v7.104l5.803-3.558L0 4.697ZM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757Zm3.436-.586L16 11.801V4.697l-5.803 3.546Z"/>
          </svg>
          <?= h(get_config('footer_email', 'contato@midiacerta.com.br')) ?>
        </p>
        <p class="mb-0">
          <svg width="16" height="16" fill="currentColor" class="me-2" viewBox="0 0 16 16">
            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
          </svg>
          <?= h(get_config('footer_endereco', 'Endereço da gráfica')) ?>
        </p>
      </div>
    </div>
    
    <hr class="my-4 border-secondary">
    
    <div class="row">
      <div class="col-md-6 text-center text-md-start">
        <p class="mb-0">&copy; <?= date('Y') ?> <?= h($branding['nome_empresa']) ?>. Todos os direitos reservados.</p>
      </div>
      <div class="col-md-6 text-center text-md-end">
        <p class="mb-0">
          <a href="#" class="text-white text-decoration-none me-3" data-bs-toggle="modal" data-bs-target="#privacyModal">Política de Privacidade</a>
          <a href="#" class="text-white text-decoration-none me-3" data-bs-toggle="modal" data-bs-target="#termsModal">Termos de Uso</a>
          <a href="#" class="text-white text-decoration-none" data-bs-toggle="modal" data-bs-target="#cookiesModal">Cookies</a>
        </p>
      </div>
    </div>
  </div>
</footer>

<div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Política de Privacidade</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <?= nl2br(h($privacy_text)) ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Termos de Uso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <?= nl2br(h($terms_text)) ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cookiesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Política de Cookies</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <?= nl2br(h($cookies_text)) ?>
      </div>
    </div>
  </div>
</div>

<div id="cookieConsent" class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-sm p-3" style="display:none; z-index: 1200;">
  <div class="container d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
    <div class="text-muted">
      <?= h($cookies_text) ?>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#cookiesModal">Ver política</button>
      <button type="button" class="btn btn-primary btn-sm" id="acceptCookies">Aceitar</button>
    </div>
  </div>
</div>

<script>
  (function () {
    var consentKey = 'cookie_consent_v1';
    var banner = document.getElementById('cookieConsent');
    var accepted = localStorage.getItem(consentKey);
    if (!accepted && banner) {
      banner.style.display = 'block';
    }
    var btn = document.getElementById('acceptCookies');
    if (btn) {
      btn.addEventListener('click', function () {
        localStorage.setItem(consentKey, 'accepted');
        if (banner) {
          banner.style.display = 'none';
        }
      });
    }
  })();
</script>
