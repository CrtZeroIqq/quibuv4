<?php
header('Content-Type: application/json');
require_once 'conexion.php';

if (!isset($_GET['grupo'])) {
    echo json_encode(["error" => "Falta el parámetro 'grupo'"]);
    exit;
}

$grupo_id = intval($_GET['grupo']);
$conexion = getConnection();

// 1. Obtener total de cuotas del grupo
$stmt = $conexion->prepare("SELECT cantidadCuotas FROM wp_grupos_cobranza WHERE id = ?");
$stmt->execute([$grupo_id]);
$cantidad_total = $stmt->fetchColumn();

if (!$cantidad_total) {
    echo json_encode(["error" => "Grupo no encontrado"]);
    exit;
}

// 2. Obtener pagadores del grupo con toda la info
$stmt_pagadores = $conexion->prepare("SELECT nombre, rut, email, telefono, fecha_registro FROM wp_pagadores WHERE id_grupo = ?");
$stmt_pagadores->execute([$grupo_id]);
$pagadores = $stmt_pagadores->fetchAll();

$datos = [];

foreach ($pagadores as $pagador) {
    $rut = $pagador['rut'];
    $nombre = $pagador['nombre'];
    $email = $pagador['email'];
    $telefono = $pagador['telefono'];
    $fecha_registro = $pagador['fecha_registro'];

    // 3. Cuotas pagadas
    $stmt_cuotas = $conexion->prepare("SELECT COUNT(*) FROM wp_pagos_cuotas WHERE rut = ?");
    $stmt_cuotas->execute([$rut]);
    $cuotas_pagadas = intval($stmt_cuotas->fetchColumn());

    // 4. Última cuota pagada (fecha más reciente)
    $stmt_ultima = $conexion->prepare("SELECT MAX(fecha_pago) FROM wp_pagos_cuotas WHERE rut = ?");
    $stmt_ultima->execute([$rut]);
    $ultima_cuota_pagada = $stmt_ultima->fetchColumn() ?: "Sin pagos aún";

    $cuotas_pendientes = $cantidad_total - $cuotas_pagadas;

    $datos[] = [
        "nombre" => $nombre,
        "rut" => $rut,
        "email" => $email,
        "telefono" => $telefono,
        "fecha_registro" => $fecha_registro,
        "cuotas_pagadas" => $cuotas_pagadas,
        "cuotas_total" => intval($cantidad_total),
        "cuotas_pendientes" => $cuotas_pendientes,
        "ultima_cuota_pagada" => $ultima_cuota_pagada,
        "resumen" => "$cuotas_pagadas de $cantidad_total"
    ];
}

echo json_encode($datos);
