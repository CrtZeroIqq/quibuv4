<?php
require_once 'conexion.php';

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
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

    // Obtener información del grupo
    $stmt = $pdo->prepare("
        SELECT 
            g.id,
            g.id_usuario,
            g.nombre_grupo,
            g.tipo_grupo,
            g.cantidad_personas,
            g.valor_cuota,
            g.cantidadCuotas,
            g.temporalidad,
            g.fecha_inicio,
            g.banco,
            g.tipo_cuenta,
            g.numero_cuenta,
            g.fecha_creacion,
            u.nombre as nombre_usuario,
            u.email as email_usuario
        FROM wp_grupos_cobranza g
        LEFT JOIN wp_usuarios_app u ON g.id_usuario = u.id
        WHERE g.id = :id_grupo
    ");
    
    $stmt->execute(['id_grupo' => $id_grupo]);
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Grupo no encontrado.'
        ]);
        exit;
    }

    // Obtener estadísticas de cuotas
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_cuotas,
            SUM(valor) as total_esperado
        FROM wp_cuotas_definidas 
        WHERE id_grupo = :id_grupo
    ");
    $stmt_stats->execute(['id_grupo' => $id_grupo]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Por ahora, sin columna estado, asumimos todas pendientes
    $total_cuotas = (int)$stats['total_cuotas'];
    $total_esperado = (int)$stats['total_esperado'];
    $cuotas_pagadas = 0;
    $cuotas_pendientes = $total_cuotas;
    $total_recaudado = 0;

    // Obtener lista de cuotas
    $stmt_cuotas = $pdo->prepare("
        SELECT 
            id,
            nro_cuota,
            fecha_cuota,
            valor
        FROM wp_cuotas_definidas 
        WHERE id_grupo = :id_grupo
        ORDER BY nro_cuota ASC
    ");
    $stmt_cuotas->execute(['id_grupo' => $id_grupo]);
    $cuotas = $stmt_cuotas->fetchAll(PDO::FETCH_ASSOC);

    // URL del grupo
    $url_grupo = "https://www.quibu.cl/pagar/?grupo=$id_grupo";

    echo json_encode([
        'success' => true,
        'grupo' => [
            'id' => (int)$grupo['id'],
            'idUsuario' => (int)$grupo['id_usuario'],
            'nombreGrupo' => $grupo['nombre_grupo'],
            'tipoGrupo' => $grupo['tipo_grupo'],
            'cantidadPersonas' => (int)$grupo['cantidad_personas'],
            'valorCuota' => (int)$grupo['valor_cuota'],
            'cantidadCuotas' => (int)$grupo['cantidadCuotas'],
            'temporalidad' => (int)$grupo['temporalidad'],
            'fechaInicio' => $grupo['fecha_inicio'],
            'banco' => $grupo['banco'],
            'tipoCuenta' => $grupo['tipo_cuenta'],
            'numeroCuenta' => $grupo['numero_cuenta'],
            'fechaCreacion' => $grupo['fecha_creacion'],
            'nombreUsuario' => $grupo['nombre_usuario'],
            'emailUsuario' => $grupo['email_usuario'],
            'urlGrupo' => $url_grupo
        ],
        'estadisticas' => [
            'totalCuotas' => $total_cuotas,
            'cuotasPagadas' => $cuotas_pagadas,
            'cuotasPendientes' => $cuotas_pendientes,
            'totalRecaudado' => $total_recaudado,
            'totalEsperado' => $total_esperado,
            'porcentajeRecaudado' => $total_esperado > 0 
                ? round(($total_recaudado / $total_esperado) * 100, 2) 
                : 0
        ],
        'cuotas' => array_map(function($cuota) {
            return [
                'id' => (int)$cuota['id'],
                'nroCuota' => (int)$cuota['nro_cuota'],
                'fechaCuota' => $cuota['fecha_cuota'],
                'valor' => (int)$cuota['valor'],
                'estado' => 'pendiente' // Por defecto, ya que no hay columna estado
            ];
        }, $cuotas)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener detalles del grupo.',
        'error' => $e->getMessage()
    ]);
}