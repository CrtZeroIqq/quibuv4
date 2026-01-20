<?php
/**
 * Quibu - Login para App Móvil
 * Autentica usuarios (tesoreros) y crea sesión
 */

session_start();

require_once __DIR__ . '/api/conexion.php';

// CORS para la app móvil
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Función para responder con JSON
 */
function respond($success, $data = [], $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Aceptar POST y GET
    $method = $_SERVER['REQUEST_METHOD'];

    if (!in_array($method, ['POST', 'GET'])) {
        respond(false, [], 'Método no permitido. Use POST o GET.', 405);
    }

    // Obtener datos según el método
    if ($method === 'POST') {
        // Intentar JSON primero
        $input = json_decode(file_get_contents('php://input'), true);

        // Si no viene JSON, usar POST normal
        if (!$input) {
            $input = $_POST;
        }
    } else {
        // GET request
        $input = $_GET;
    }

    // Validar que venga el email
    if (empty($input['email'])) {
        respond(false, [], 'El email es requerido.', 400);
    }

    $email = trim($input['email']);

    // Conectar a base de datos
    $pdo = getConnection();

    // Buscar usuario por email
    $stmt = $pdo->prepare("
        SELECT
            id,
            nombre,
            email,
            rut,
            telefono,
            ciudad,
            created_at,
            sms_automatico,
            mp_access_token,
            mp_user_id,
            mp_linked_at
        FROM wp_usuarios_app
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        respond(false, [], 'Usuario no encontrado con ese email.', 404);
    }

    // Crear sesión
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_email'] = $usuario['email'];
    $_SESSION['user_nombre'] = $usuario['nombre'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Contar grupos del usuario
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wp_grupos_cobranza
        WHERE id_usuario = ?
    ");
    $stmt->execute([$usuario['id']]);
    $grupos_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Preparar respuesta
    $response = [
        'usuario' => [
            'id' => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'rut' => $usuario['rut'],
            'telefono' => $usuario['telefono'],
            'ciudad' => $usuario['ciudad'],
            'created_at' => $usuario['created_at'],
            'sms_automatico' => (bool)$usuario['sms_automatico'],
            'grupos_count' => (int)$grupos_count,
            'mercadopago_vinculado' => !empty($usuario['mp_access_token']),
            'mp_user_id' => $usuario['mp_user_id'],
            'mp_linked_at' => $usuario['mp_linked_at']
        ],
        'session' => [
            'token' => session_id(),
            'expires_in' => 86400 // 24 horas
        ]
    ];

    respond(true, $response, 'Login exitoso');

} catch (PDOException $e) {
    error_log("Error en login.php: " . $e->getMessage());
    respond(false, [], 'Error al procesar login. Intente nuevamente.', 500);
} catch (Exception $e) {
    error_log("Error general en login.php: " . $e->getMessage());
    respond(false, [], 'Error inesperado. Intente nuevamente.', 500);
}
