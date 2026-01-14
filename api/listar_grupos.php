<?php
require_once 'conexion.php';

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    if (empty($_GET['idUsuario'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Falta el parámetro idUsuario.'
        ]);
        exit;
    }

    $id_usuario = (int)$_GET['idUsuario'];
    $pdo = getConnection();

    // Obtener todos los grupos del usuario ordenados por fecha de creación descendente
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nombre_grupo,
            tipo_grupo,
            cantidad_personas,
            valor_cuota,
            cantidadCuotas,
            temporalidad,
            fecha_inicio,
            banco,
            tipo_cuenta,
            numero_cuenta,
            fecha_creacion
        FROM wp_grupos_cobranza 
        WHERE id_usuario = :id_usuario 
        ORDER BY fecha_creacion DESC
    ");
    
    $stmt->execute(['id_usuario' => $id_usuario]);
    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar cuotas por grupo
    $grupos_con_detalle = [];
    foreach ($grupos as $grupo) {
        $id_grupo = (int)$grupo['id'];
        
        // Contar cuotas del grupo
        $stmt_cuotas = $pdo->prepare("
            SELECT 
                COUNT(*) as total_cuotas
            FROM wp_cuotas_definidas 
            WHERE id_grupo = :id_grupo
        ");
        $stmt_cuotas->execute(['id_grupo' => $id_grupo]);
        $stats = $stmt_cuotas->fetch(PDO::FETCH_ASSOC);
        
        // Por ahora todas las cuotas son pendientes (no hay columna estado)
        $total_cuotas = (int)$stats['total_cuotas'];
        $cuotas_pagadas = 0;
        $cuotas_pendientes = $total_cuotas;

        // URL del grupo
        $url_grupo = "https://www.quibu.cl/pagar/?grupo=$id_grupo";

        $grupos_con_detalle[] = [
            'id' => (int)$grupo['id'],
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
            'urlGrupo' => $url_grupo,
            'estadisticas' => [
                'totalCuotas' => $total_cuotas,
                'cuotasPagadas' => $cuotas_pagadas,
                'cuotasPendientes' => $cuotas_pendientes
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'grupos' => $grupos_con_detalle,
        'totalGrupos' => count($grupos_con_detalle)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener grupos.',
        'error' => $e->getMessage()
    ]);
}