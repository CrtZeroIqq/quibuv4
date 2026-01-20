<?php
/**
 * API: Obtener URL de autorización de Mercado Pago v2
 * Endpoint mejorado para la app móvil (FlutterFlow)
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

    // Conectar a BD
    $pdo = getConnection();

    // Verificar que el usuario exista y obtener sus datos de MP
    $stmt = $pdo->prepare("SELECT id, email, nombre, mp_user_id, mp_linked_at FROM wp_usuarios_app WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }

    // Verificar si ya tiene MP vinculado
    if (!empty($usuario['mp_user_id'])) {
        // Ya tiene MP vinculado
        echo json_encode([
            'success' => true,
            'ya_vinculado' => true,
            'mp_user_id' => $usuario['mp_user_id'],
            'vinculado_desde' => $usuario['mp_linked_at'],
            'mensaje' => 'Tu cuenta de Mercado Pago ya está vinculada',
            'usuario' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email']
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Generar state token simple (formato: random_hex_usuario_id)
    // No guardamos en BD, solo validamos que el usuario_id sea válido en el callback
    $state = bin2hex(random_bytes(16)) . '_' . $usuario_id;

    // Construir URL de autorización de Mercado Pago
    $params = [
        'client_id' => MP_CLIENT_ID,
        'response_type' => 'code',
        'platform_id' => 'mp',
        'redirect_uri' => MP_REDIRECT_URI,
        'state' => $state
    ];

    $auth_url = MP_AUTH_URL . '/authorization?' . http_build_query($params);

    // Respuesta mejorada para FlutterFlow
    echo json_encode([
        'success' => true,
        'ya_vinculado' => false,
        'auth_url' => $auth_url,
        'open_in_browser' => true,  // Indica a la app que debe abrir en navegador
        'action' => 'open_browser',  // Acción que debe tomar la app
        'instrucciones' => 'Abre esta URL en un navegador web. Después de autorizar, serás redirigido de vuelta.',
        'redirect_uri' => MP_REDIRECT_URI,
        'mp_config' => [
            'client_id' => MP_CLIENT_ID,
            'auth_url_base' => MP_AUTH_URL
        ],
        'usuario' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en mp-obtener-url-oauth.php: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'mensaje' => 'No se pudo generar la URL de autorización de Mercado Pago'
    ], JSON_UNESCAPED_UNICODE);
}
