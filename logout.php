<?php
/**
 * Quibu - Logout
 * Cierra la sesión del usuario
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Destruir todas las variables de sesión
    $_SESSION = array();

    // Destruir la cookie de sesión si existe
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }

    // Destruir la sesión
    session_destroy();

    echo json_encode([
        'success' => true,
        'message' => 'Sesión cerrada exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en logout.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cerrar sesión'
    ]);
}
