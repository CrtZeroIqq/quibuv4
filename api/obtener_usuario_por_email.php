<?php
require_once 'conexion.php';

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    if (empty($_GET['email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Falta el parámetro email.'
        ]);
        exit;
    }

    $email = trim($_GET['email']);
    $pdo = getConnection();

    // Buscar usuario por email
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nombre,
            email,
            rut
        FROM wp_usuarios_app 
        WHERE email = :email
        LIMIT 1
    ");
    
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no encontrado con ese email.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'usuario' => [
            'id' => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'rut' => $usuario['rut']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al buscar usuario.',
        'error' => $e->getMessage()
    ]);
}