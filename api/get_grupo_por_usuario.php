<?php
require_once 'conexion.php';

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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

    // Buscar ID del usuario por email (revisado en base a estructura)
    $stmt = $pdo->prepare("SELECT id FROM wp_usuarios_app WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no encontrado.'
        ]);
        exit;
    }

    $id_usuario = (int)$usuario['id'];

    // Buscar último grupo creado por ese usuario (id_usuario)
    $stmt2 = $pdo->prepare("SELECT id, nombre_grupo FROM wp_grupos_cobranza WHERE id_usuario = :id_usuario ORDER BY id DESC LIMIT 1");
    $stmt2->execute(['id_usuario' => $id_usuario]);
    $grupo = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        echo json_encode([
            'success' => false,
            'message' => 'Este usuario aún no ha creado un grupo.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'idGrupo' => (int)$grupo['id'],
        'nombreGrupo' => $grupo['nombre_grupo']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de servidor.',
        'error' => $e->getMessage()
    ]);
}
