<?php
/**
 * Quibu - Procesar Pago
 *
 * Procesa pagos a través de Mercado Pago o Transbank según configuración del grupo
 */

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mp-config.php';

use MercadoPago\SDK;
use MercadoPago\Preference;
use MercadoPago\Item;
use Transbank\Webpay\WebpayPlus\Transaction;

// Validar datos del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Método no permitido');
}

if (!isset($_POST['rut']) || !isset($_POST['grupo_id']) || !isset($_POST['cuotas']) || !is_array($_POST['cuotas'])) {
    die('Faltan datos requeridos');
}

$rut = strtoupper(trim($_POST['rut']));
$grupo_id = intval($_POST['grupo_id']);
$cuotas_ids = array_map('intval', $_POST['cuotas']);
$metodo_pago = $_POST['metodo_pago'] ?? 'mercadopago'; // Por defecto Mercado Pago

if (empty($cuotas_ids)) {
    die('Debes seleccionar al menos una cuota');
}

try {
    $pdo = getConnection();

    // Obtener información del grupo y tesorero
    $stmt = $pdo->prepare("
        SELECT g.*, u.id as tesorero_id, u.nombre as tesorero_nombre,
               u.mp_access_token, u.mp_user_id
        FROM wp_grupos_cobranza g
        LEFT JOIN wp_usuarios_app u ON g.id_usuario = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$grupo_id]);
    $grupo = $stmt->fetch();

    if (!$grupo) {
        die('Grupo no encontrado');
    }

    // Verificar que el pagador existe
    $stmt = $pdo->prepare("SELECT * FROM wp_pagadores WHERE rut = ? AND id_grupo = ?");
    $stmt->execute([$rut, $grupo_id]);
    $pagador = $stmt->fetch();

    if (!$pagador) {
        die('Pagador no encontrado');
    }

    // Obtener información de las cuotas seleccionadas
    $placeholders = implode(',', array_fill(0, count($cuotas_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT * FROM wp_cuotas_definidas
        WHERE id IN ($placeholders) AND id_grupo = ?
    ");
    $stmt->execute([...$cuotas_ids, $grupo_id]);
    $cuotas = $stmt->fetchAll();

    if (count($cuotas) !== count($cuotas_ids)) {
        die('Algunas cuotas no son válidas');
    }

    // Verificar que ninguna cuota ya esté pagada
    $stmt = $pdo->prepare("
        SELECT id_cuota FROM wp_pagos_cuotas
        WHERE id_cuota IN ($placeholders) AND rut = ?
    ");
    $stmt->execute([...$cuotas_ids, $rut]);
    $cuotas_pagadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($cuotas_pagadas)) {
        die('Algunas cuotas ya están pagadas');
    }

    // Calcular totales
    $subtotal = 0;
    $fee_total = 0;
    $detalles_cuotas = [];

    foreach ($cuotas as $cuota) {
        $valor_base = floatval($cuota['valor']);
        $subtotal += $valor_base;

        // Calcular fee según tabla progresiva
        $fee = calcular_fee($valor_base);
        $fee_total += $fee;

        $detalles_cuotas[] = [
            'id' => $cuota['id'],
            'nro_cuota' => $cuota['nro_cuota'],
            'valor' => $valor_base,
            'fee' => $fee,
            'total' => $valor_base + $fee
        ];
    }

    $total = $subtotal + $fee_total;

    // Guardar datos en sesión para confirmar después del pago
    $_SESSION['pago_pendiente'] = [
        'rut' => $rut,
        'grupo_id' => $grupo_id,
        'cuotas' => $detalles_cuotas,
        'subtotal' => $subtotal,
        'fee_total' => $fee_total,
        'total' => $total,
        'metodo_pago' => $metodo_pago,
        'timestamp' => time()
    ];

    // Procesar según método de pago seleccionado
    if ($metodo_pago === 'mercadopago') {
        // Verificar que el tesorero tenga Mercado Pago vinculado
        if (empty($grupo['mp_access_token'])) {
            die('El tesorero de este grupo no ha vinculado su cuenta de Mercado Pago. Por favor contacta al tesorero.');
        }

        // Procesar con Mercado Pago
        $init_point = procesar_mercadopago($grupo, $pagador, $detalles_cuotas, $subtotal, $fee_total, $total);

        // Redireccionar a Mercado Pago
        header('Location: ' . $init_point);
        exit;

    } elseif ($metodo_pago === 'transbank') {
        // Procesar con Transbank
        $url_pago = procesar_transbank($grupo, $pagador, $detalles_cuotas, $total);

        // Redireccionar a Transbank
        header('Location: ' . $url_pago);
        exit;

    } else {
        die('Método de pago no válido');
    }

} catch (Exception $e) {
    error_log("Error al procesar pago: " . $e->getMessage());
    die('Error al procesar el pago. Por favor intenta nuevamente.');
}

/**
 * Procesar pago con Mercado Pago usando modelo de Marketplace
 *
 * Split Payment:
 * - El TESORERO recibe el monto de las cuotas (subtotal)
 * - QUIBU recibe el fee como marketplace_fee
 * - El usuario paga el total (subtotal + fee)
 */
function procesar_mercadopago($grupo, $pagador, $detalles_cuotas, $subtotal, $fee_total, $total) {
    // Configurar SDK con el access token del tesorero
    // El pago irá a la cuenta del tesorero, y Quibu recibirá el marketplace_fee
    SDK::setAccessToken($grupo['mp_access_token']);

    // Crear preferencia de pago
    $preference = new Preference();

    // Items - SOLO las cuotas (sin el fee)
    $items = [];

    // Agregar cuotas como items
    foreach ($detalles_cuotas as $detalle) {
        $item = new Item();
        $item->title = "Cuota #{$detalle['nro_cuota']} - " . htmlspecialchars($grupo['nombre_grupo']);
        $item->description = "Cuota #{$detalle['nro_cuota']} del grupo " . htmlspecialchars($grupo['nombre_grupo']);
        $item->quantity = 1;
        $item->unit_price = $detalle['valor'];
        $item->currency_id = 'CLP';
        $items[] = $item;
    }

    // Agregar el FEE de Quibu también como item para que el usuario lo vea
    // Pero será cobrado como marketplace_fee a nivel de preferencia
    if ($fee_total > 0) {
        $item_fee = new Item();
        $item_fee->title = "Servicio Quibu";
        $item_fee->description = "Costo del servicio de cobranza automatizada";
        $item_fee->quantity = 1;
        $item_fee->unit_price = $fee_total;
        $item_fee->currency_id = 'CLP';
        $items[] = $item_fee;
    }

    $preference->items = $items;

    // ============================================
    // MARKETPLACE FEE (SPLIT PAYMENT)
    // ============================================
    // Esto hace que Quibu reciba el fee automáticamente
    // y el tesorero reciba solo el monto de las cuotas
    if ($fee_total > 0) {
        $preference->marketplace_fee = $fee_total;
    }

    // Información del pagador
    $preference->payer = [
        'name' => $pagador['nombre'],
        'email' => $pagador['email'],
        'phone' => [
            'number' => $pagador['telefono']
        ],
        'identification' => [
            'type' => 'RUT',
            'number' => $pagador['rut']
        ]
    ];

    // URLs de retorno
    $preference->back_urls = [
        'success' => MP_SUCCESS_URL,
        'failure' => MP_FAILURE_URL,
        'pending' => MP_PENDING_URL
    ];

    $preference->auto_return = 'approved';

    // Configuración adicional
    $preference->statement_descriptor = 'QUIBU';
    $preference->external_reference = $grupo['id'] . '-' . $pagador['rut'] . '-' . time();

    // Notification URL (webhook)
    $preference->notification_url = MP_WEBHOOK_URL;

    // Métodos de pago excluidos (opcional)
    // $preference->payment_methods = [
    //     'excluded_payment_types' => [['id' => 'ticket']], // Excluir pagos en efectivo
    // ];

    // Guardar preferencia
    $preference->save();

    if (!$preference->id) {
        throw new Exception('Error al crear preferencia de Mercado Pago');
    }

    // Guardar ID de preferencia en sesión
    $_SESSION['pago_pendiente']['mp_preference_id'] = $preference->id;

    // Retornar URL de pago
    return $preference->init_point;
}

/**
 * Procesar pago con Transbank
 */
function procesar_transbank($grupo, $pagador, $detalles_cuotas, $total) {
    // Configurar Transbank
    $ambiente = $_ENV['TRANSBANK_MODE'] ?? 'integration';

    if ($ambiente === 'production') {
        \Transbank\Webpay\WebpayPlus::configureForProduction(
            $_ENV['TRANSBANK_COMMERCE_CODE'],
            $_ENV['TRANSBANK_API_KEY']
        );
    } else {
        \Transbank\Webpay\WebpayPlus::configureForIntegration(
            $_ENV['TRANSBANK_COMMERCE_CODE'] ?? '597055555532',
            $_ENV['TRANSBANK_API_KEY'] ?? '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C'
        );
    }

    $transaction = new Transaction();

    // Generar orden de compra única
    $buy_order = 'QUIBU-' . $grupo['id'] . '-' . time();
    $session_id = session_id();
    $amount = round($total);
    $return_url = $_ENV['TRANSBANK_RETURN_URL'] ?? 'https://www.quibu.cl/api/respuesta-pago.php';

    // Guardar buy_order en sesión
    $_SESSION['pago_pendiente']['transbank_buy_order'] = $buy_order;

    // Crear transacción
    $response = $transaction->create($buy_order, $session_id, $amount, $return_url);

    if (!isset($response->url) || !isset($response->token)) {
        throw new Exception('Error al crear transacción en Transbank');
    }

    // Guardar token de transacción
    $_SESSION['pago_pendiente']['transbank_token'] = $response->token;

    // Retornar URL de pago (con redirección POST)
    return $response->url . '?token_ws=' . $response->token;
}

/**
 * Calcular fee según tabla progresiva
 */
function calcular_fee($valor) {
    if ($valor >= 1000 && $valor <= 5000) {
        return 200 + ($valor * 0.005);
    } elseif ($valor >= 5100 && $valor <= 8000) {
        return 220 + ($valor * 0.006);
    } elseif ($valor >= 8100 && $valor <= 10000) {
        return 230 + ($valor * 0.008);
    } elseif ($valor >= 10100 && $valor <= 20000) {
        return 260 + ($valor * 0.01);
    } elseif ($valor >= 20100 && $valor <= 30000) {
        return 300 + ($valor * 0.01);
    } else {
        return 300 + ($valor * 0.01);
    }
}
