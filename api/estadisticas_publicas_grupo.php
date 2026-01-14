<?php
/**
 * Quibu - API de Estadísticas Públicas del Grupo
 *
 * Devuelve estadísticas de progreso y recaudación de un grupo
 * Para uso en dashboards públicos (no requiere autenticación)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/conexion.php';

// Verificar parámetros
if (!isset($_GET['idGrupo'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Falta el parámetro idGrupo'
    ]);
    exit;
}

$grupo_id = intval($_GET['idGrupo']);

try {
    $pdo = getConnection();

    // Obtener información del grupo
    $stmt = $pdo->prepare("
        SELECT
            g.*,
            u.nombre as tesorero_nombre,
            u.email as tesorero_email
        FROM wp_grupos_cobranza g
        LEFT JOIN wp_usuarios_app u ON g.id_usuario = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$grupo_id]);
    $grupo = $stmt->fetch();

    if (!$grupo) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado'
        ]);
        exit;
    }

    // Contar pagadores registrados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wp_pagadores
        WHERE id_grupo = ?
    ");
    $stmt->execute([$grupo_id]);
    $pagadores_registrados = $stmt->fetch()['total'];

    // Obtener información de cuotas
    $stmt = $pdo->prepare("
        SELECT
            MIN(fecha_cuota) as fecha_primera_cuota,
            MAX(fecha_cuota) as fecha_ultima_cuota,
            COUNT(*) as total_cuotas,
            SUM(valor) as valor_total_cuotas
        FROM wp_cuotas_definidas
        WHERE id_grupo = ?
    ");
    $stmt->execute([$grupo_id]);
    $info_cuotas = $stmt->fetch();

    // Calcular total recaudado
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT pc.id_cuota) as cuotas_pagadas,
            SUM(pc.monto_pagado) as total_recaudado
        FROM wp_pagos_cuotas pc
        INNER JOIN wp_cuotas_definidas cd ON pc.id_cuota = cd.id
        WHERE cd.id_grupo = ?
    ");
    $stmt->execute([$grupo_id]);
    $info_pagos = $stmt->fetch();

    // Calcular estadísticas
    $total_recaudado = floatval($info_pagos['total_recaudado'] ?? 0);
    $meta_total = floatval($info_cuotas['valor_total_cuotas'] ?? 0) * intval($grupo['cantidad_personas'] ?? 0);
    $porcentaje_recaudacion = $meta_total > 0 ? round(($total_recaudado / $meta_total) * 100, 1) : 0;
    $porcentaje_registro = $grupo['cantidad_personas'] > 0 ? round(($pagadores_registrados / $grupo['cantidad_personas']) * 100, 1) : 0;

    // Cuotas pendientes del mes actual
    $mes_actual = date('Y-m');
    $stmt = $pdo->prepare("
        SELECT
            cd.id,
            cd.nro_cuota,
            cd.fecha_cuota,
            cd.valor,
            cd.descripcion
        FROM wp_cuotas_definidas cd
        WHERE cd.id_grupo = ?
        AND DATE_FORMAT(cd.fecha_cuota, '%Y-%m') = ?
        LIMIT 1
    ");
    $stmt->execute([$grupo_id, $mes_actual]);
    $cuota_actual = $stmt->fetch();

    // Construir respuesta
    $response = [
        'success' => true,
        'grupo' => [
            'id' => $grupo['id'],
            'nombre' => $grupo['nombre_grupo'],
            'descripcion' => $grupo['descripcion'] ?? '',
            'cantidadPersonas' => intval($grupo['cantidad_personas'] ?? 0),
            'tipoGrupo' => $grupo['tipo_grupo'] ?? '',
            'tesorero' => [
                'nombre' => $grupo['tesorero_nombre'] ?? 'No asignado',
                'email' => $grupo['tesorero_email'] ?? ''
            ]
        ],
        'periodo' => [
            'fechaPrimeraCuota' => $info_cuotas['fecha_primera_cuota'] ?? null,
            'fechaUltimaCuota' => $info_cuotas['fecha_ultima_cuota'] ?? null,
            'totalCuotas' => intval($info_cuotas['total_cuotas'] ?? 0)
        ],
        'estadisticasGenerales' => [
            'pagadoresRegistrados' => $pagadores_registrados,
            'porcentajeRegistro' => $porcentaje_registro,
            'totalRecaudado' => $total_recaudado,
            'metaTotalCurso' => $meta_total,
            'porcentajeRecaudacion' => $porcentaje_recaudacion,
            'cuotasPagadas' => intval($info_pagos['cuotas_pagadas'] ?? 0),
            'cuotasTotales' => intval($info_cuotas['total_cuotas'] ?? 0) * intval($grupo['cantidad_personas'] ?? 0)
        ],
        'cuotaActual' => $cuota_actual ? [
            'nroCuota' => $cuota_actual['nro_cuota'],
            'fecha' => $cuota_actual['fecha_cuota'],
            'valor' => floatval($cuota_actual['valor']),
            'descripcion' => $cuota_actual['descripcion'] ?? ''
        ] : null
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en estadisticas_publicas_grupo.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas del grupo',
        'error' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : null
    ]);
}
