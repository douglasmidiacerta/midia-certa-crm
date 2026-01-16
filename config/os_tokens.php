<?php
// Funções para gerenciar tokens de aprovação e acompanhamento de OS

/**
 * Gera token de aprovação de arte para o cliente
 * @param PDO $pdo
 * @param int $os_id
 * @param int $expiry_hours Horas até expirar (padrão: 72h = 3 dias)
 * @return string Token gerado
 */
function generate_approval_token(PDO $pdo, int $os_id, int $expiry_hours = 72): string {
    $token = bin2hex(random_bytes(32)); // 64 caracteres
    $expires_at = date('Y-m-d H:i:s', time() + ($expiry_hours * 3600));
    
    // Remove tokens antigos não utilizados da mesma OS
    $pdo->prepare("DELETE FROM os_approval_tokens WHERE os_id=? AND used_at IS NULL")
        ->execute([$os_id]);
    
    // Insere novo token
    $st = $pdo->prepare("INSERT INTO os_approval_tokens (os_id, token, expires_at, created_at)
                        VALUES (?,?,?, NOW())");
    $st->execute([$os_id, $token, $expires_at]);
    
    return $token;
}

/**
 * Gera ou recupera token de acompanhamento (permanente) da OS
 * @param PDO $pdo
 * @param int $os_id
 * @return string Token
 */
function get_or_create_tracking_token(PDO $pdo, int $os_id): string {
    // Verifica se já existe
    $st = $pdo->prepare("SELECT token FROM os_tracking_tokens WHERE os_id=? LIMIT 1");
    $st->execute([$os_id]);
    $row = $st->fetch();
    
    if($row){
        return $row['token'];
    }
    
    // Cria novo token permanente
    $token = bin2hex(random_bytes(32));
    $st = $pdo->prepare("INSERT INTO os_tracking_tokens (os_id, token, created_at) VALUES (?,?, NOW())");
    $st->execute([$os_id, $token]);
    
    return $token;
}

/**
 * Gera URL completa para aprovação de arte (redireciona para o Portal do Cliente)
 * @param array $config Configuração do sistema
 * @param string $token
 * @return string URL
 */
function get_approval_url(array $config, string $token): string {
    $base = $config['public_base_url'] ?? rtrim($config['base_path'] ?? '', '/');
    if(empty($base) || $base === ''){
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://" . $_SERVER['HTTP_HOST'];
    }
    // Redireciona para o Portal do Cliente ao invés da página pública
    return rtrim($base, '/') . '/client_portal.php?approval_token=' . urlencode($token);
}

/**
 * Gera URL completa para acompanhamento do pedido (redireciona para o Portal do Cliente)
 * @param array $config Configuração do sistema
 * @param string $token
 * @return string URL
 */
function get_tracking_url(array $config, string $token): string {
    $base = $config['public_base_url'] ?? rtrim($config['base_path'] ?? '', '/');
    if(empty($base) || $base === ''){
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://" . $_SERVER['HTTP_HOST'];
    }
    // Redireciona para o Portal do Cliente ao invés da página pública
    return rtrim($base, '/') . '/client_portal.php?tracking_token=' . urlencode($token);
}

/**
 * Gera link do WhatsApp com mensagem para aprovação de arte
 * @param string $phone Telefone no formato internacional (ex: 5511999999999)
 * @param array $os Dados da OS
 * @param string $approval_url URL de aprovação
 * @return string URL do WhatsApp
 */
function get_whatsapp_approval_link(string $phone, array $os, string $approval_url): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    $message = "Olá! 👋\n\n";
    $message .= "Sua arte do pedido *#{$os['code']}* está pronta para aprovação! 🎨\n\n";
    $message .= "⚠️ *IMPORTANTE:* Confira todos os detalhes antes de aprovar:\n";
    $message .= "📌 Layout e disposição\n";
    $message .= "📌 Ortografia e textos\n";
    $message .= "📌 Telefones e e-mails\n";
    $message .= "📌 Endereços\n\n";
    $message .= "🔐 *Acesse seu painel do cliente:*\n";
    $message .= $approval_url . "\n\n";
    $message .= "💡 Faça login com seu CPF/CNPJ e aprove a arte para seguirmos com a produção!\n\n";
    $message .= "Equipe Mídia Certa 🖨️";
    
    return "https://wa.me/" . $phone . "?text=" . urlencode($message);
}

/**
 * Gera link do WhatsApp com mensagem de acompanhamento
 * @param string $phone Telefone no formato internacional
 * @param array $os Dados da OS
 * @param string $tracking_url URL de acompanhamento
 * @return string URL do WhatsApp
 */
function get_whatsapp_tracking_link(string $phone, array $os, string $tracking_url): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    $message = "Olá! 👋\n\n";
    $message .= "Pedido *#{$os['code']}* confirmado! ✅\n\n";
    $message .= "🔐 *Acesse seu painel do cliente para acompanhar:*\n";
    $message .= $tracking_url . "\n\n";
    $message .= "💡 Faça login com seu CPF/CNPJ e acompanhe cada etapa do seu pedido em tempo real!\n\n";
    $message .= "Equipe Mídia Certa 🖨️";
    
    return "https://wa.me/" . $phone . "?text=" . urlencode($message);
}

/**
 * Envia solicitação de aprovação (gera token e retorna link do WhatsApp)
 * @param PDO $pdo
 * @param array $config
 * @param int $os_id
 * @return array ['approval_url' => string, 'whatsapp_link' => string, 'token' => string]
 */
function send_approval_request(PDO $pdo, array $config, int $os_id): array {
    // Busca dados da OS e cliente
    $st = $pdo->prepare("SELECT o.*, c.name as client_name, c.whatsapp 
                         FROM os o 
                         JOIN clients c ON c.id = o.client_id 
                         WHERE o.id=?");
    $st->execute([$os_id]);
    $os = $st->fetch();
    
    if(!$os){
        throw new Exception("OS não encontrada");
    }
    
    // Gera token de aprovação
    $token = generate_approval_token($pdo, $os_id);
    $approval_url = get_approval_url($config, $token);
    
    // Gera link do WhatsApp
    $whatsapp_link = get_whatsapp_approval_link($os['whatsapp'], $os, $approval_url);
    
    return [
        'token' => $token,
        'approval_url' => $approval_url,
        'whatsapp_link' => $whatsapp_link
    ];
}
