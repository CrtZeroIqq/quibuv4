<?php
/**
 * Quibu - Procesar Pago con Mercado Pago Split
 * Versión: 2.0
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mp-config.php';

session_start();

// Limpiar sesión anterior
unset($_SESSION['quibu_pago_mp']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pagar/');
    exit;
}

try {
    $pdo = getConnection();

    // ========================================
    // 1. SANITIZAR Y VALIDAR INPUTS
    // ========================================
    
    $rut = isset($_POST['rut']) ? strtoupper(trim($_POST['rut'])) : '';
    $grupo_id = isset($_POST['grupo_id']) ? intval($_POST['grupo_id']) : 0;
    $cuotas_seleccionadas = isset($_POST['cuotas']) ? array_map('intval', $_POST['cuotas']) : [];

    if (!$rut || !$grupo_id || empty($cuotas_seleccionadas)) {
        throw new Exception('Faltan datos obligatorios.');
    }

    // ========================================
    // 2. VERIFICAR PAGADOR
    // ========================================
    
    $stmt = $pdo->prepare("SELECT * FROM wp_pagadores WHERE rut = ? AND id_grupo = ?");
    $stmt->execute([$rut, $grupo_id]);
    $pagador = $stmt->fetch();

    if (!$pagador) {
        throw new Exception('Pagador no encontrado.');
    }

    // ========================================
    // 3. OBTENER CUOTAS Y VERIFICAR VALIDEZ
    // ========================================
    
    $placeholders = implode(',', array_fill(0, count($cuotas_seleccionadas), '?'));
    $stmt = $pdo->prepare("
        SELECT * FROM wp_cuotas_definidas
        WHERE id IN ($placeholders) AND id_grupo = ?
    ");
    $stmt->execute(array_merge($cuotas_seleccionadas, [$grupo_id]));
    $cuotas = $stmt->fetchAll();

    if (count($cuotas) !== count($cuotas_seleccionadas)) {
        throw new Exception('Algunas cuotas seleccionadas no son válidas.');
    }

    // Verificar que ninguna cuota ya esté pagada
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM wp_pagos_cuotas
        WHERE rut = ? AND id_cuota IN ($placeholders)
    ");
    $stmt->execute(array_merge([$rut], $cuotas_seleccionadas));
    $cuotas_ya_pagadas = $stmt->fetch()['total'];

    if ($cuotas_ya_pagadas > 0) {
        throw new Exception('Algunas de las cuotas seleccionadas ya están pagadas.');
    }

    // ========================================
    // 4. CALCULAR MONTOS
    // ========================================
    
    $subtotal = 0;
    $detalles_cuotas = [];

    foreach ($cuotas as $cuota) {
        $valor_cuota = floatval($cuota['valor']);
        $subtotal += $valor_cuota;
        
        $detalles_cuotas[] = [
            'id' => $cuota['id'],
            'nombre' => $cuota['nombre'],
            'valor' => $valor_cuota
        ];
    }

    // Calcular fee de Quibu
    $quibu_fee = calcular_quibu_fee($subtotal);
    $total = $subtotal + $quibu_fee;

    // Validar monto mínimo
    if ($total < MP_MIN_AMOUNT) {
        throw new Exception('El monto a pagar debe ser mayor a $' . MP_MIN_AMOUNT);
    }

    // ========================================
    // 5. OBTENER DATOS DEL TESORERO (MP USER ID)
    // ========================================
    
    $stmt = $pdo->prepare("
        SELECT u.mp_access_token, u.mp_user_id, u.email
        FROM wp_usuarios_app u
        INNER JOIN wp_grupos g ON g.id_usuario = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$grupo_id]);
    $tesorero = $stmt->fetch();

    if (!$tesorero || empty($tesorero['mp_user_id'])) {
        throw new Exception('El tesorero del grupo no tiene vinculada su cuenta de Mercado Pago. Por favor contacta al administrador del grupo.');
    }

    // ========================================
    // 6. CREAR PREFERENCIA EN MERCADO PAGO
    // ========================================
    
    $preference_data = [
        'items' => [
            [
                'id' => 'cuotas_' . $grupo_id,
                'title' => 'Cuotas Grupo ' . $grupo_id . ' - ' . count($cuotas) . ' cuota(s)',
                'description' => implode(', ', array_column($detalles_cuotas, 'nombre')),
                'quantity' => 1,
                'unit_price' => floatval($total),
                'currency_id' => MP_CURRENCY
            ]
        ],
        'marketplace_fee' => floatval($quibu_fee),
        'back_urls' => [
            'success' => MP_SUCCESS_URL,
            'failure' => MP_FAILURE_URL,
            'pending' => MP_PENDING_URL
        ],
        'auto_return' => 'approved',
        'external_reference' => 'QUIBU_' . $grupo_id . '_' . time(),
        'notification_url' => MP_WEBHOOK_URL,
        'metadata' => [
            'grupo_id' => $grupo_id,
            'rut_pagador' => $rut,
            'cuotas_ids' => implode(',', $cuotas_seleccionadas),
            'subtotal' => $subtotal,
            'fee' => $quibu_fee
        ],
        'payer' => [
            'name' => $pagador['nombre'] ?? 'Pagador',
            'email' => $pagador['email'] ?? 'pago@quibu.cl'
        ]
    ];

    // Llamar a MP API
    $preference = crear_preferencia_mp($preference_data, $tesorero['mp_access_token']);

    if (!$preference || !isset($preference['id'])) {
        throw new Exception('Error al crear la preferencia de pago en Mercado Pago.');
    }

    // ========================================
    // 7. GUARDAR EN TABLA DE TRANSACCIONES
    // ========================================
    
    $stmt = $pdo->prepare("
        INSERT INTO wp_mp_transactions 
        (preference_id, grupo_id, pagador_rut, total_amount, marketplace_fee, net_amount, cuotas_ids, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $preference['id'],
        $grupo_id,
        $rut,
        $total,
        $quibu_fee,
        $subtotal,
        implode(',', $cuotas_seleccionadas)
    ]);

    // ========================================
    // 8. GUARDAR EN SESIÓN
    // ========================================
    
    $_SESSION['quibu_pago_mp'] = [
        'preference_id' => $preference['id'],
        'cuotas' => $cuotas_seleccionadas,
        'rut' => $rut,
        'grupo_id' => $grupo_id,
        'total' => $total,
        'subtotal' => $subtotal,
        'fee' => $quibu_fee
    ];

    // Log
    mp_log('Preferencia creada exitosamente', [
        'preference_id' => $preference['id'],
        'grupo_id' => $grupo_id,
        'rut' => $rut,
        'total' => $total
    ], 'info');

    // ========================================
    // 9. REDIRIGIR A MP CHECKOUT
    // ========================================
    
    header('Location: ' . $preference['init_point']);
    exit;

} catch (PDOException $e) {
    mp_log('Error DB en procesar_pago_mp', ['error' => $e->getMessage()], 'error');
    die('Error de base de datos al procesar el pago: ' . htmlspecialchars($e->getMessage()));
} catch (Exception $e) {
    mp_log('Error en procesar_pago_mp', ['error' => $e->getMessage()], 'error');
    die('Error al iniciar el pago: ' . htmlspecialchars($e->getMessage()));
}

/**
 * Crear preferencia en Mercado Pago usando el access_token del seller
 */
function crear_preferencia_mp($data, $seller_access_token) {
    $url = MP_API_URL . '/checkout/preferences';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, get_mp_headers(true, $seller_access_token));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 201 && $http_code !== 200) {
        mp_log('Error al crear preferencia', [
            'http_code' => $http_code,
            'response' => $response,
            'data' => $data
        ], 'error');
        return false;
    }
    
    return json_decode($response, true);
}
