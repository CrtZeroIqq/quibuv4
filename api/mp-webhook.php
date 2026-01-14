<?php
/**
 * Quibu - Webhook de Mercado Pago
 *
 * Recibe notificaciones IPN de Mercado Pago sobre el estado de los pagos
 * Documentación: https://www.mercadopago.cl/developers/es/docs/your-integrations/notifications/webhooks
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mp-config.php';

use MercadoPago\SDK;

// Log de la notificación recibida
error_log("MP Webhook recibido: " . json_encode($_GET) . " | " . json_encode($_POST));

// Mercado Pago puede enviar notificaciones por GET o POST
$data = $_GET ?: $_POST;

if (!isset($data['type']) || !isset($data['data']['id'])) {
    http_response_code(400);
    die('Datos inválidos');
}

$type = $data['type'] ?? $_GET['type'] ?? null;
$data_id = $data['data']['id'] ?? $_GET['data_id'] ?? null;

if ($type === 'payment') {
    procesar_notificacion_pago($data['id']);
}

// Responder 200 OK a Mercado Pago
http_response_code(200);
echo json_encode(['status' => 'received']);
exit;

/**
 * Procesar notificación de pago
 */
function procesar_notificacion_pago($payment_id) {
    require_once __DIR__ . '/conexion.php';
    require_once __DIR__ . '/mp-config.php';

    try {
        $pdo = getConnection();

        // Buscar el pago en nuestra base de datos
        $stmt = $pdo->prepare("
            SELECT * FROM wp_pagos_cuotas
            WHERE mp_payment_id = ?
            LIMIT 1
        ");
        $stmt->execute([$payment_id]);
        $pago_existente = $stmt->fetch();

        if ($pago_pendiente) {
            // Obtener información del pago desde Mercado Pago
            $mp_payment = obtener_info_pago_mp($payment_id, $grupo['mp_access_token']);

            if ($mp_payment['status'] === 'approved') {
                // Pago aprobado, registrar en base de datos
                registrar_pago_exitoso($pago_pendiente, $mp_payment_id, 'mercadopago');
            }
        }
    }

    // Log de la notificación
    error_log("Webhook MP recibido: " . json_encode($notification_data));

    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    error_log("Error en webhook de Mercado Pago: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Obtener información de un pago de Mercado Pago
 */
function obtener_info_pago_mp($payment_id, $access_token) {
    $url = MP_API_URL . '/v1/payments/' . $payment_id;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Error al obtener info de pago MP. HTTP Code: $http_code. Response: $response");
        return null;
    }

    return json_decode($response, true);
}

/**
 * Registrar pago en base de datos
 */
function registrar_pago($pdo, $rut, $cuotas, $monto_total, $metodo_pago, $external_id) {
    try {
        beginTransaction();

        // Insertar cada cuota como pagada
        $stmt = $pdo->prepare("
            INSERT INTO wp_pagos_cuotas (id_cuota, rut, fecha_pago, monto_pagado, metodo_pago, transaction_id)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($cuotas as $cuota) {
            $stmt->execute([
                $cuota['id'],
                $rut,
                date('Y-m-d H:i:s'),
                $cuota['total'],
                $transaction_id
            ]);
        }

        commit();
        return true;

    } catch (Exception $e) {
        rollback();
        error_log("Error al registrar pago: " . $e->getMessage());
        return false;
    }
}
