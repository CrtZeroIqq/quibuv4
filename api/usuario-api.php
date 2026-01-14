<?php
require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email inválido'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, email, nombre, rut, telefono, ciudad 
                           FROM wp_usuarios_app 
                           WHERE email = :email 
                           LIMIT 1");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        // Forzar que todos los campos existan, incluso si están en null
        $response = [
            'id'       => $usuario['id'],
            'email'    => $usuario['email'],
            'nombre'   => $usuario['nombre'] ?? '',
            'rut'      => $usuario['rut'] ?? '',
            'telefono' => $usuario['telefono'] ?? '',
            'ciudad'   => $usuario['ciudad'] ?? ''
        ];

        echo json_encode([
            'success' => true,
            'data' => $response
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'data' => null
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Método no permitido'
]);
