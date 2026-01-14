<?php
// get_sms_automatico.php

require_once 'conexion.php';

header('Content-Type: application/json');

// Validar que se recibe el parámetro "email"
if (!isset($_GET['email'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Falta el parámetro email']);
    exit;
}

$email = $_GET['email'];

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT sms_automatico FROM wp_usuarios_app WHERE email = ?");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode([
            'status' => 'success',
            'sms_automatico' => (int)$row['sms_automatico']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
