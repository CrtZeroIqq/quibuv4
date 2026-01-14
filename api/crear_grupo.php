<?php
require_once 'conexion.php';

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $input = $_POST;

    // Validación
    if (
        empty($input['idUsuario']) ||
        empty($input['nombreGrupo']) ||
        empty($input['valorCuota']) ||
        !isset($input['temporalidad']) ||  // puede ser 0
        empty($input['campoFecha']) ||
        empty($input['bancoElige']) ||
        empty($input['tipoCuenta']) ||
        empty($input['numeroCuenta']) ||
        empty($input['cantidadCuotas'])
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Faltan campos obligatorios.'
        ]);
        exit;
    }

    // Variables en camelCase desde FlutterFlow
    $id_usuario        = (int)$input['idUsuario'];
    $nombre_grupo      = $input['nombreGrupo'];
    $tipo_grupo        = !empty($input['otroUso']) ? $input['otroUso'] : $input['cuotasDrop'];
    $cantidad_personas = !empty($input['cantidadPersonas']) ? (int)$input['cantidadPersonas'] : 0;
    $valor_cuota       = (int)$input['valorCuota'];
    $intervalo_dias    = (int)$input['temporalidad']; // ahora es directamente un número: 7, 15, 30 o 0
    $cantidad_cuotas   = (int)$input['cantidadCuotas'];
    $fecha_inicio      = date('Y-m-d', strtotime($input['campoFecha']));
    $banco             = $input['bancoElige'];
    $tipo_cuenta       = $input['tipoCuenta'];
    $numero_cuenta     = $input['numeroCuenta'];

    $pdo = getConnection();

    // Insertar grupo
    // Insertar grupo (ahora con cantidadCuotas)
$stmt = $pdo->prepare("
    INSERT INTO wp_grupos_cobranza (
        id_usuario, nombre_grupo, tipo_grupo, cantidad_personas,
        valor_cuota, temporalidad, cantidadCuotas, fecha_inicio,
        banco, tipo_cuenta, numero_cuenta
    ) VALUES (
        :id_usuario, :nombre_grupo, :tipo_grupo, :cantidad_personas,
        :valor_cuota, :temporalidad, :cantidadCuotas, :fecha_inicio,
        :banco, :tipo_cuenta, :numero_cuenta
    )
");
$stmt->execute([
    'id_usuario'        => $id_usuario,
    'nombre_grupo'      => $nombre_grupo,
    'tipo_grupo'        => $tipo_grupo,
    'cantidad_personas' => $cantidad_personas,
    'valor_cuota'       => $valor_cuota,
    'temporalidad'      => $intervalo_dias,
    'cantidadCuotas'    => $cantidad_cuotas,
    'fecha_inicio'      => $fecha_inicio,
    'banco'             => $banco,
    'tipo_cuenta'       => $tipo_cuenta,
    'numero_cuenta'     => $numero_cuenta,
]);


    $id_grupo = (int)$pdo->lastInsertId();

    // Insertar cuotas
    $fecha = new DateTime($fecha_inicio);
    $ins_cuota = $pdo->prepare("
        INSERT INTO wp_cuotas_definidas (id_grupo, nro_cuota, fecha_cuota, valor)
        VALUES (:id_grupo, :nro_cuota, :fecha_cuota, :valor)
    ");

    for ($i = 1; $i <= $cantidad_cuotas; $i++) {
        $ins_cuota->execute([
            'id_grupo'    => $id_grupo,
            'nro_cuota'   => $i,
            'fecha_cuota' => $fecha->format('Y-m-d'),
            'valor'       => $valor_cuota,
        ]);

        if ($intervalo_dias > 0) {
    if ($intervalo_dias === 30) {
        $fecha->modify('+1 month'); // caso mensual en Chile
    } else {
        $fecha->modify("+{$intervalo_dias} days"); // semanal o quincenal
    }
}

    }

	$url_grupo = "https://www.quibu.cl/pagar/?grupo=$id_grupo";

    echo json_encode([
        'success' => true,
        'idGrupo' => $id_grupo,
        'cuotasGeneradas' => $cantidad_cuotas,
    	'url' => $url_grupo
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar grupo o cuotas.',
        'error' => $e->getMessage()
    ]);
}
