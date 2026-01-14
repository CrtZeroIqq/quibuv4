<?php
/**
 * API: Obtener URL de autorización de Mercado Pago
 * Endpoint para la app móvil (FlutterFlow)
 * 
 * GET /api/mp-obtener-url-oauth.php?usuario_id=123
 * 
 * Retorna la URL para que el usuario autorice Mercado Pago
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mp-config.php';

try {
    // Obtener usuario_id
    $usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
    
    if (!$usuario_id) {
        throw new Exception('usuario_id es requerido');
    }
    
    // Verificar que el usuario exista
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, email FROM wp_usuarios_app WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Verificar si ya tiene MP vinculado
    $stmt = $pdo->prepare("SELECT mp_user_id, mp_linked_at FROM wp_usuarios_app WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $mp_data = $stmt->fetch();
    
    if (!empty($mp_data['mp_user_id'])) {
        // Ya tiene MP vinculado
        echo json_encode([
            'success' => true,
            'ya_vinculado' => true,
            'mp_user_id' => $mp_data['mp_user_id'],
            'vinculado_desde' => $mp_data['mp_linked_at']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Generar state token (para seguridad CSRF)
    $state = bin2hex(random_bytes(16)) . '_' . $usuario_id;
    
    // Guardar state en la BD temporalmente (válido por 10 minutos)
    $stmt = $pdo->prepare("
        INSERT INTO wp_mp_oauth_states (state_token, usuario_id, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt->execute([$state, $usuario_id]);
    
    // Construir URL de autorización de Mercado Pago
    $params = [
        'client_id' => MP_CLIENT_ID,
        'response_type' => 'code',
        'platform_id' => 'mp',
        'redirect_uri' => MP_REDIRECT_URI,
        'state' => $state
    ];
    
    $auth_url = MP_AUTH_URL . '/authorization?' . http_build_query($params);
    
    echo json_encode([
        'success' => true,
        'ya_vinculado' => false,
        'auth_url' => $auth_url,
        'instrucciones' => 'Abre esta URL en un navegador web. Después de autorizar, serás redirigido de vuelta.'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
