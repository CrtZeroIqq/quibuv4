<?php
/**
 * Quibu - Verificar Sesión
 * Verifica si la sesión del usuario está activa
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Verificar si hay sesión activa
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        // Verificar que no haya expirado (24 horas)
        $login_time = $_SESSION['login_time'] ?? 0;
        $current_time = time();
        $elapsed = $current_time - $login_time;

        if ($elapsed > 86400) {
            // Sesión expirada
            session_destroy();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Sesión expirada',
                'code' => 'session_expired'
            ]);
            exit;
        }

        // Sesión válida
        echo json_encode([
            'success' => true,
            'message' => 'Sesión activa',
            'data' => [
                'user_id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'nombre' => $_SESSION['user_nombre'],
                'expires_in' => 86400 - $elapsed
            ]
        ]);
    } else {
        // No hay sesión
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No hay sesión activa',
            'code' => 'no_session'
        ]);
    }

} catch (Exception $e) {
    error_log("Error en verify_session.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar sesión'
    ]);
}
