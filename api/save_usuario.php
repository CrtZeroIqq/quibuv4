<?php
// save_usuario.php
require_once 'conexion.php';

// CORS & JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Leer input raw
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        // Si no hay raw JSON, usamos form data
        $input = $_POST;
    } else {
        $input = json_decode($raw, true);
    }

    // Validar
    if (
        empty($input['email']) ||
        ! filter_var($input['email'], FILTER_VALIDATE_EMAIL) ||
        empty($input['nombre']) ||
        empty($input['rut']) ||
        empty($input['telefono']) ||
        empty($input['ciudad'])
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Faltan datos o email inválido.'
        ]);
        exit;
    }

    $email    = $input['email'];
    $nombre   = $input['nombre'];
    $rut      = $input['rut'];
    $telefono = $input['telefono'];
    $ciudad   = $input['ciudad'];

    $pdo = getConnection();

    // ¿Existe usuario?
    $stmt = $pdo->prepare("SELECT id FROM wp_usuarios_app WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        // UPDATE
        $upd = $pdo->prepare("
            UPDATE wp_usuarios_app
               SET nombre     = :nombre,
                   rut        = :rut,
                   telefono   = :telefono,
                   ciudad     = :ciudad,
                   updated_at = CURRENT_TIMESTAMP
             WHERE email = :email
        ");
        $upd->execute([
            'nombre'   => $nombre,
            'rut'      => $rut,
            'telefono' => $telefono,
            'ciudad'   => $ciudad,
            'email'    => $email,
        ]);
        $id = $existing;
    } else {
        // INSERT
        $ins = $pdo->prepare("
            INSERT INTO wp_usuarios_app
                (email, nombre, rut, telefono, ciudad)
            VALUES
                (:email, :nombre, :rut, :telefono, :ciudad)
        ");
        $ins->execute([
            'email'    => $email,
            'nombre'   => $nombre,
            'rut'      => $rut,
            'telefono' => $telefono,
            'ciudad'   => $ciudad,
        ]);
        $id = $pdo->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'id'      => (int)$id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos'
    ]);
}

