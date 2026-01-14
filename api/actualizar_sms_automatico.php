<?php
// actualizar_sms_automatico.php

header('Content-Type: application/json');
require_once 'conexion.php'; // Ajusta si está en otro path

// Validar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

// Obtener parámetros desde GET
$email = $_GET['email'] ?? '';
$sms_automatico = isset($_GET['sms_automatico']) ? intval($_GET['sms_automatico']) : null;

if (!$email || $sms_automatico === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
    exit;
}

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("UPDATE wp_usuarios_app SET sms_automatico = :sms_automatico WHERE email = :email");
    $stmt->execute([
        ':sms_automatico' => $sms_automatico,
        ':email' => $email
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Preferencia actualizada']);
    } else {
        echo json_encode(['status' => 'warning', 'message' => 'No se actualizó ningún registro. ¿Email correcto?']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos', 'error' => $e->getMessage()]);
}
