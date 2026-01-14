<?php
/**
 * API: Estadísticas Públicas del Grupo - PANORAMA GENERAL DEL CURSO
 * Devuelve información agregada del progreso total SIN revelar identidades
 * 
 * URL: /api/estadisticas_publicas_grupo.php?idGrupo=X
 * Uso: Mostrar en la página de pago ANTES de ingresar RUT
 */

require_once 'conexion.php';

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Validar parámetros
    if (empty($_GET['idGrupo'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Falta el parámetro idGrupo.'
        ]);
        exit;
    }

    $id_grupo = (int)$_GET['idGrupo'];
    $pdo = getConnection();

    // Obtener información básica del grupo
    $stmt_grupo = $pdo->prepare("
        SELECT 
            id,
            nombre_grupo,
            tipo_grupo,
            cantidad_personas,
            valor_cuota,
            cantidadCuotas as cantidad_cuotas,
            temporalidad,
            fecha_inicio,
            fecha_creacion
        FROM wp_grupos_cobranza 
        WHERE id = :id_grupo
    ");
    $stmt_grupo->execute(['id_grupo' => $id_grupo]);
    $grupo = $stmt_grupo->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado.'
        ]);
        exit;
    }

    // Calcular meta total del curso
    $cantidad_personas = (int)$grupo['cantidad_personas'];
    $valor_cuota = (int)$grupo['valor_cuota'];
    $cantidad_cuotas = (int)$grupo['cantidad_cuotas'];
    $meta_total_curso = $cantidad_personas * $valor_cuota * $cantidad_cuotas;

    // Obtener estadísticas de cuotas
    $stmt_cuotas = $pdo->prepare("
        SELECT 
            COUNT(*) as total_cuotas_definidas,
            MIN(fecha_cuota) as fecha_primera_cuota,
            MAX(fecha_cuota) as fecha_ultima_cuota
        FROM wp_cuotas_definidas
        WHERE id_grupo = :id_grupo
    ");
    $stmt_cuotas->execute(['id_grupo' => $id_grupo]);
    $cuotas_info = $stmt_cuotas->fetch(PDO::FETCH_ASSOC);

    // Contar pagadores registrados
    $stmt_pagadores = $pdo->prepare("
        SELECT COUNT(DISTINCT id) as total_pagadores_registrados
        FROM wp_pagadores
        WHERE id_grupo = :id_grupo
    ");
    $stmt_pagadores->execute(['id_grupo' => $id_grupo]);
    $pagadores_info = $stmt_pagadores->fetch(PDO::FETCH_ASSOC);

    // Estadísticas TOTALES de todos los pagos del curso
    $stmt_pagos_totales = $pdo->prepare("
        SELECT 
            COUNT(*) as total_transacciones,
            SUM(pc.monto_pagado) as total_recaudado_historico,
            COUNT(DISTINCT pc.id_pagador) as pagadores_unicos_que_pagaron,
            COUNT(DISTINCT cd.nro_cuota) as cuotas_con_al_menos_un_pago
        FROM wp_pagos_cuotas pc
        INNER JOIN wp_cuotas_definidas cd ON pc.id_cuota = cd.id
        WHERE cd.id_grupo = :id_grupo
    ");
    $stmt_pagos_totales->execute(['id_grupo' => $id_grupo]);
    $pagos_totales = $stmt_pagos_totales->fetch(PDO::FETCH_ASSOC);

    // Calcular métricas generales
    $pagadores_registrados = (int)$pagadores_info['total_pagadores_registrados'];
    $pagadores_faltantes = $cantidad_personas - $pagadores_registrados;
    $porcentaje_registro = $cantidad_personas > 0 
        ? round(($pagadores_registrados / $cantidad_personas) * 100, 1)
        : 0;

    $total_recaudado = (int)$pagos_totales['total_recaudado_historico'];
    $porcentaje_recaudacion = $meta_total_curso > 0 
        ? round(($total_recaudado / $meta_total_curso) * 100, 1)
        : 0;

    $cuotas_con_pagos = (int)$pagos_totales['cuotas_con_al_menos_un_pago'];
    $cuotas_sin_pagos = $cantidad_cuotas - $cuotas_con_pagos;

    // Formatear respuesta
    echo json_encode([
        'success' => true,
        'grupo' => [
            'id' => (int)$grupo['id'],
            'nombreGrupo' => $grupo['nombre_grupo'],
            'tipoGrupo' => $grupo['tipo_grupo'],
            'cantidadPersonas' => $cantidad_personas,
            'valorCuota' => $valor_cuota,
            'totalCuotas' => $cantidad_cuotas,
            'temporalidad' => (int)$grupo['temporalidad'],
            'fechaInicio' => $grupo['fecha_inicio'],
            'metaTotalCurso' => $meta_total_curso
        ],
        'estadisticasGenerales' => [
            'pagadoresRegistrados' => $pagadores_registrados,
            'pagadoresFaltantes' => $pagadores_faltantes,
            'porcentajeRegistro' => $porcentaje_registro,
            'totalRecaudado' => $total_recaudado,
            'metaTotalCurso' => $meta_total_curso,
            'porcentajeRecaudacion' => $porcentaje_recaudacion,
            'totalCuotas' => $cantidad_cuotas,
            'cuotasConPagos' => $cuotas_con_pagos,
            'cuotasSinPagos' => $cuotas_sin_pagos,
            'totalTransacciones' => (int)$pagos_totales['total_transacciones'],
            'pagadoresUnicosQuePagaron' => (int)$pagos_totales['pagadores_unicos_que_pagaron']
        ],
        'periodo' => [
            'fechaPrimeraCuota' => $cuotas_info['fecha_primera_cuota'],
            'fechaUltimaCuota' => $cuotas_info['fecha_ultima_cuota']
        ],
        'mensaje' => [
            'titulo' => '📊 Estado General del Curso',
            'descripcion' => sprintf(
                '%d de %d personas registradas (%s%%) • %s de %s recaudado (%s%%)',
                $pagadores_registrados,
                $cantidad_personas,
                $porcentaje_registro,
                '$' . number_format($total_recaudado, 0, ',', '.'),
                '$' . number_format($meta_total_curso, 0, ',', '.'),
                $porcentaje_recaudacion
            )
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas del grupo.',
        'error' => $e->getMessage()
    ]);
}