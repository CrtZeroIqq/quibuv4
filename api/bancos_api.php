<?php
// bancos_api.php
require_once 'conexion.php';

// Cabeceras CORS y JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    $pdo  = getConnection();
    $stmt = $pdo->query("SELECT id, nombre AS label FROM wp_bancos ORDER BY nombre ASC");
    $bancos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $bancos
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar bancos'
    ]);
}
