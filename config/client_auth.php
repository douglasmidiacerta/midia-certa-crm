<?php
/**
 * Sistema de Autenticação do Portal do Cliente
 */

// Inicia sessão de cliente se ainda não iniciada
function client_session_start() {
  if (session_status() === PHP_SESSION_NONE) {
    session_name('CLIENT_SESSION');
    session_start();
  }
}

// Verifica se o cliente está logado
function is_client_logged_in() {
  client_session_start();
  return isset($_SESSION['client_id']) && isset($_SESSION['client_token']);
}

// Obtém ID do cliente logado
function get_client_id() {
  client_session_start();
  return $_SESSION['client_id'] ?? null;
}

// Obtém dados do cliente logado
function get_client_data($pdo) {
  if (!is_client_logged_in()) return null;
  
  $client_id = get_client_id();
  $st = $pdo->prepare("SELECT c.*, ca.email 
                       FROM clients c 
                       LEFT JOIN client_auth ca ON ca.client_id = c.id 
                       WHERE c.id = ?");
  $st->execute([$client_id]);
  return $st->fetch();
}

// Requer que o cliente esteja logado (redireciona se não estiver)
function require_client_login($redirect_to = 'client_login.php') {
  if (!is_client_logged_in()) {
    header('Location: ' . $redirect_to);
    exit;
  }
}

// Faz login do cliente
function client_login($pdo, $email, $password) {
  // Busca cliente por email
  $st = $pdo->prepare("SELECT ca.*, c.name, c.active as client_active, c.portal_enabled
                       FROM client_auth ca 
                       JOIN clients c ON c.id = ca.client_id 
                       WHERE ca.email = ? AND ca.active = 1");
  $st->execute([$email]);
  $client = $st->fetch();
  
  if (!$client) {
    return ['success' => false, 'error' => 'Email ou senha incorretos.'];
  }
  
  // Verifica senha
  if (!password_verify($password, $client['password_hash'])) {
    // Log de tentativa falhada
    client_log($pdo, null, 'login_failed', ['email' => $email]);
    return ['success' => false, 'error' => 'Email ou senha incorretos.'];
  }
  
  // Verifica se o cliente está ativo
  if (!$client['client_active'] || !$client['portal_enabled']) {
    return ['success' => false, 'error' => 'Sua conta está desativada. Entre em contato com o suporte.'];
  }
  
  // Verifica se o email foi verificado (DESABILITADO - cadastro manual já vem verificado)
  // if (!$client['email_verified']) {
  //   return ['success' => false, 'error' => 'Por favor, verifique seu email antes de fazer login.', 'needs_verification' => true];
  // }
  
  // Cria sessão
  client_session_start();
  $session_token = bin2hex(random_bytes(32));
  
  $_SESSION['client_id'] = $client['client_id'];
  $_SESSION['client_name'] = $client['name'];
  $_SESSION['client_email'] = $client['email'];
  $_SESSION['client_token'] = $session_token;
  
  // Salva sessão no banco
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
  
  $st = $pdo->prepare("INSERT INTO client_sessions (client_id, session_token, ip_address, user_agent, expires_at) 
                       VALUES (?, ?, ?, ?, ?)");
  $st->execute([$client['client_id'], $session_token, $ip, $user_agent, $expires]);
  
  // Atualiza último login
  $pdo->prepare("UPDATE client_auth SET last_login = NOW() WHERE id = ?")
      ->execute([$client['id']]);
  
  // Log de login bem-sucedido
  client_log($pdo, $client['client_id'], 'login_success');
  
  return ['success' => true, 'client_id' => $client['client_id']];
}

// Faz logout do cliente
function client_logout($pdo) {
  client_session_start();
  
  if (isset($_SESSION['client_token'])) {
    // Remove sessão do banco
    $pdo->prepare("DELETE FROM client_sessions WHERE session_token = ?")
        ->execute([$_SESSION['client_token']]);
  }
  
  if (isset($_SESSION['client_id'])) {
    client_log($pdo, $_SESSION['client_id'], 'logout');
  }
  
  // Limpa sessão
  session_unset();
  session_destroy();
}

// Registra novo cliente no portal
function client_register($pdo, $data) {
  $name = trim($data['name'] ?? '');
  $email = trim($data['email'] ?? '');
  $password = $data['password'] ?? '';
  $phone = trim($data['phone'] ?? '');
  
  // Processa CPF/CNPJ unificado
  $cpf_cnpj = trim($data['cpf_cnpj'] ?? '');
  $cpf_cnpj_limpo = preg_replace('/[^\d]/', '', $cpf_cnpj);
  $cpf = strlen($cpf_cnpj_limpo) === 11 ? $cpf_cnpj : '';
  $cnpj = strlen($cpf_cnpj_limpo) === 14 ? $cpf_cnpj : '';
  
  $cep = trim($data['cep'] ?? '');
  $address_street = trim($data['address_street'] ?? '');
  $address_number = trim($data['address_number'] ?? '');
  $address_neighborhood = trim($data['address_neighborhood'] ?? '');
  $address_city = trim($data['address_city'] ?? '');
  $address_state = trim($data['address_state'] ?? '');
  $address_complement = trim($data['address_complement'] ?? '');
  
  // Validações
  if (!$name || !$email || !$password) {
    return ['success' => false, 'error' => 'Nome, email e senha são obrigatórios.'];
  }
  
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['success' => false, 'error' => 'Email inválido.'];
  }
  
  if (strlen($password) < 6) {
    return ['success' => false, 'error' => 'A senha deve ter no mínimo 6 caracteres.'];
  }
  
  // Verifica se email já existe
  $st = $pdo->prepare("SELECT COUNT(*) as c FROM client_auth WHERE email = ?");
  $st->execute([$email]);
  if ((int)$st->fetch()['c'] > 0) {
    return ['success' => false, 'error' => 'Este email já está cadastrado.'];
  }
  
  $pdo->beginTransaction();
  
  try {
    // Cria cliente com os campos corretos da tabela
    $st = $pdo->prepare("INSERT INTO clients (name, email, whatsapp, cpf, cnpj, cep, address_street, address_number, address_neighborhood, address_city, address_state, address_complement, active, portal_enabled, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())");
    $st->execute([$name, $email, $phone, $cpf, $cnpj, $cep, $address_street, $address_number, $address_neighborhood, $address_city, $address_state, $address_complement]);
    $client_id = (int)$pdo->lastInsertId();
    
    // Cria autenticação
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $verification_token = bin2hex(random_bytes(32));
    
    $st = $pdo->prepare("INSERT INTO client_auth (client_id, email, password_hash, active, email_verified, verification_token) 
                         VALUES (?, ?, ?, 1, 0, ?)");
    $st->execute([$client_id, $email, $password_hash, $verification_token]);
    
    $pdo->commit();
    
    // Log de registro
    client_log($pdo, $client_id, 'register', ['email' => $email]);
    
    return [
      'success' => true, 
      'client_id' => $client_id,
      'verification_token' => $verification_token,
      'message' => 'Cadastro realizado com sucesso! Verifique seu email para ativar sua conta.'
    ];
    
  } catch (Exception $e) {
    $pdo->rollBack();
    return ['success' => false, 'error' => 'Erro ao criar conta: ' . $e->getMessage()];
  }
}

// Verifica email do cliente
function client_verify_email($pdo, $token) {
  $st = $pdo->prepare("UPDATE client_auth SET email_verified = 1, verification_token = NULL 
                       WHERE verification_token = ? AND active = 1");
  $st->execute([$token]);
  
  return $st->rowCount() > 0;
}

// Registra log de ação do cliente
function client_log($pdo, $client_id, $action, $details = []) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $details_json = !empty($details) ? json_encode($details) : null;
  
  $st = $pdo->prepare("INSERT INTO client_portal_logs (client_id, action, ip_address, user_agent, details) 
                       VALUES (?, ?, ?, ?, ?)");
  $st->execute([$client_id, $action, $ip, $user_agent, $details_json]);
}

// Limpa sessões expiradas (executar periodicamente)
function client_clean_expired_sessions($pdo) {
  $pdo->query("DELETE FROM client_sessions WHERE expires_at < NOW()");
}
