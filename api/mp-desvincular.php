<?php
/**
 * Quibu - Desvincular Cuenta de Mercado Pago
 *
 * Permite al tesorero desvincular su cuenta de Mercado Pago
 * para poder vincular una cuenta diferente.
 *
 * Endpoint: /api/mp-desvincular.php
 * Método: POST
 * Parámetros: usuario_id
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/conexion.php';

/**
 * Función helper para responder JSON
 */
function respond($success, $data = [], $message = '', $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, [], 'Método no permitido. Use POST.', 405);
    }

    // Obtener datos
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $usuario_id = isset($input['usuario_id']) ? intval($input['usuario_id']) : 0;

    if ($usuario_id <= 0) {
        respond(false, [], 'El parámetro usuario_id es requerido.', 400);
    }

    // Conectar a BD
    $pdo = getConnection();

    // Verificar que el usuario exista
    $stmt = $pdo->prepare("
        SELECT
            id,
            nombre,
            email,
            mp_user_id,
            mp_linked_at
        FROM wp_usuarios_app
        WHERE id = ?
    ");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        respond(false, [], 'Usuario no encontrado.', 404);
    }

    // Verificar que tenga cuenta vinculada
    if (empty($usuario['mp_user_id'])) {
        respond(false, [
            'ya_desvinculado' => true
        ], 'La cuenta ya está desvinculada (no había ninguna cuenta vinculada).', 200);
    }

    // Guardar info de la cuenta que se desvincula (para log)
    $cuenta_anterior = [
        'mp_user_id' => $usuario['mp_user_id'],
        'vinculada_en' => $usuario['mp_linked_at']
    ];

    // Desvincular cuenta
    $stmt = $pdo->prepare("
        UPDATE wp_usuarios_app
        SET
            mp_access_token = NULL,
            mp_user_id = NULL,
            mp_public_key = NULL,
            mp_refresh_token = NULL,
            mp_linked_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([$usuario_id]);

    // Log
    error_log("Cuenta MP desvinculada - Usuario: {$usuario['nombre']} (ID: {$usuario_id}), MP User ID anterior: {$cuenta_anterior['mp_user_id']}");

    // Responder
    respond(true, [
        'usuario' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email']
        ],
        'cuenta_anterior' => $cuenta_anterior,
        'instrucciones' => [
            '1. Cierra sesión de Mercado Pago en tu navegador',
            '2. Vuelve a vincular tu cuenta desde la app',
            '3. Al autorizar, inicia sesión con TU cuenta personal (no la de la empresa)'
        ]
    ], 'Cuenta de Mercado Pago desvinculada exitosamente.', 200);

} catch (PDOException $e) {
    error_log("Error DB en mp-desvincular: " . $e->getMessage());
    respond(false, [], 'Error de base de datos.', 500);
} catch (Exception $e) {
    error_log("Error en mp-desvincular: " . $e->getMessage());
    respond(false, [], $e->getMessage(), 500);
}
