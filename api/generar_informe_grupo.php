<?php
require_once 'conexion.php';

header('Content-Type: application/json');

// Obtener el ID del grupo desde la URL
$grupo_id = isset($_GET['grupo']) ? intval($_GET['grupo']) : 0;
if ($grupo_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Grupo ID inválido.']);
    exit;
}

try {
    $pdo = getConnection();

    // Obtener datos base desde wp_grupos_cobranza
    $stmt = $pdo->prepare("SELECT nombre_grupo, valor_cuota, cantidadCuotas, cantidad_personas FROM wp_grupos_cobranza WHERE id = ?");
    $stmt->execute([$grupo_id]);
    $grupo = $stmt->fetch();

    if (!$grupo) {
        echo json_encode(['success' => false, 'message' => 'Grupo no encontrado.']);
        exit;
    }

    $nombre_grupo = $grupo['nombre_grupo'];
    $valor_cuota = $grupo['valor_cuota'];
    $cantidad_cuotas = $grupo['cantidadCuotas'];
    $cantidad_personas = $grupo['cantidad_personas'];

    // Total esperado
    $total_esperado = $valor_cuota * $cantidad_cuotas * $cantidad_personas;

    // Total recaudado (sumando monto_pagado)
    $stmt = $pdo->prepare("
        SELECT SUM(monto_pagado) as total_recaudado
        FROM wp_pagos_cuotas pc
        JOIN wp_cuotas_definidas cd ON pc.id_cuota = cd.id
        WHERE cd.id_grupo = ?
    ");
    $stmt->execute([$grupo_id]);
    $total_recaudado = $stmt->fetchColumn() ?: 0;

    // Cuotas pagadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM wp_pagos_cuotas pc
        JOIN wp_cuotas_definidas cd ON pc.id_cuota = cd.id
        WHERE cd.id_grupo = ?
    ");
    $stmt->execute([$grupo_id]);
    $cuotas_pagadas = $stmt->fetchColumn();

    // Personas al día
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT pc.id_pagador, COUNT(*) AS cuotas_pagadas
            FROM wp_pagos_cuotas pc
            JOIN wp_cuotas_definidas cd ON pc.id_cuota = cd.id
            WHERE cd.id_grupo = ?
            GROUP BY pc.id_pagador
            HAVING cuotas_pagadas = ?
        ) AS sub
    ");
    $stmt->execute([$grupo_id, $cantidad_cuotas]);
    $personas_al_dia = $stmt->fetchColumn();

    // Morosos
    $morosos = $cantidad_personas - $personas_al_dia;

    // Porcentaje recaudado
    $porcentaje = $total_esperado > 0 ? round(($total_recaudado / $total_esperado) * 100, 2) : 0;

	setlocale(LC_MONETARY, 'es_CL.UTF-8'); // Asegura formato local
	$total_recaudado_formateado = '$' . number_format($total_recaudado, 0, ',', '.');
	$total_esperado_formateado = '$' . number_format($total_esperado, 0, ',', '.');
	$total_valor_cuota_formateado = '$' . number_format($valor_cuota, 0, ',', '.');

    // Respuesta JSON
    echo json_encode([
        'success' => true,
        'grupo_id' => $grupo_id,
        'nombre_grupo' => $nombre_grupo,
        'valor_cuota' => $valor_cuota,
        'cantidad_cuotas' => $cantidad_cuotas,
        'cantidad_personas' => $cantidad_personas,
        'total_esperado' => $total_esperado,
        'total_recaudado' => $total_recaudado,
    	'total_recaudado_formateado' => $total_recaudado_formateado,
    	'total_esperado_formateado' => $total_esperado_formateado,
    	'total_valor_cuota_formateado' => $total_valor_cuota_formateado,
        'cuotas_pagadas' => $cuotas_pagadas,
        'personas_al_dia' => $personas_al_dia,
        'morosos' => $morosos,
        'porcentaje' => $porcentaje
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
