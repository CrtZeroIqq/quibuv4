<?php
header('Content-Type: application/json');
require_once 'conexion.php';

if (!isset($_GET['grupo'])) {
    echo json_encode(["error" => "Falta el parámetro 'grupo'"]);
    exit;
}

$grupo_id = intval($_GET['grupo']);
$conexion = getConnection();

// 1. Obtener datos del grupo
$stmt = $conexion->prepare("SELECT cantidad_personas, valor_cuota, cantidadCuotas FROM wp_grupos_cobranza WHERE id = ?");
$stmt->execute([$grupo_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo) {
    echo json_encode(["error" => "Grupo no encontrado"]);
    exit;
}

$cantidad_personas = intval($grupo['cantidad_personas']);
$valor_cuota = intval($grupo['valor_cuota']);
$cantidad_cuotas = intval($grupo['cantidadCuotas']);

$total_esperado = $cantidad_personas * $valor_cuota * $cantidad_cuotas;

// 2. Contar cantidad de cuotas pagadas en ese grupo
$stmt = $conexion->prepare("
    SELECT COUNT(*) AS cuotas_pagadas
    FROM wp_pagos_cuotas p
    INNER JOIN wp_cuotas_definidas c ON p.id_cuota = c.id
    WHERE c.id_grupo = ?
");
$stmt->execute([$grupo_id]);
$cuotas_pagadas = intval($stmt->fetchColumn());

// 3. Recalcular el total recaudado real como cuotas_pagadas * valor_cuota
$total_recaudado = $cuotas_pagadas * $valor_cuota;

$porcentaje = $total_esperado > 0 ? round($total_recaudado / $total_esperado, 4) : 0;
$progreso_texto = number_format($porcentaje * 100, 0) . '%';

echo json_encode([
    "total_esperado" => $total_esperado,
    "total_recaudado" => $total_recaudado,
    "porcentaje" => $porcentaje,
    "progreso_texto" => $progreso_texto
]);

