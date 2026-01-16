<?php
// API para cadastro rápido de cliente
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
  echo json_encode(['success' => false, 'error' => 'Método inválido']);
  exit;
}

require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$action = $_POST['action'] ?? '';

if($action === 'quick_create_client'){
  $name = trim($_POST['name'] ?? '');
  $whatsapp = preg_replace('/\D+/', '', $_POST['whatsapp'] ?? '');
  $contact_name = trim($_POST['contact_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $cpf = trim($_POST['cpf'] ?? '');
  $cnpj = trim($_POST['cnpj'] ?? '');
  $cep = trim($_POST['cep'] ?? $_POST['address_cep'] ?? ''); // Aceita ambos os formatos
  $street = trim($_POST['address_street'] ?? '');
  $number = trim($_POST['address_number'] ?? '');
  $neigh = trim($_POST['address_neighborhood'] ?? '');
  $city = trim($_POST['address_city'] ?? '');
  $state = trim($_POST['address_state'] ?? '');
  $comp = trim($_POST['address_complement'] ?? '');
  
  if(empty($name) || empty($whatsapp)){
    echo json_encode(['success' => false, 'error' => 'Nome e WhatsApp são obrigatórios']);
    exit;
  }
  
  try {
    $st = $pdo->prepare("INSERT INTO clients (name, whatsapp, contact_name, phone, email, cpf, cnpj, 
                        cep, address_street, address_number, address_neighborhood, 
                        address_city, address_state, address_complement, active, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $st->execute([$name, $whatsapp, $contact_name, $phone, $email, $cpf, $cnpj, 
                  $cep, $street, $number, $neigh, $city, $state, $comp]);
    $client_id = (int)$pdo->lastInsertId();
    
    // Log de auditoria
    if(function_exists('audit')){
      audit($pdo, 'create', 'clients', $client_id, ['name' => $name]);
    }
    
    echo json_encode([
      'success' => true, 
      'client_id' => $client_id,
      'name' => $name,
      'whatsapp' => $whatsapp
    ]);
    
  } catch(Exception $e){
    echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar: ' . $e->getMessage()]);
  }
} else {
  echo json_encode(['success' => false, 'error' => 'Ação inválida']);
}
