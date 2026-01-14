<?php
/**
 * Quibu - Respuesta de Pago
 *
 * Procesa respuestas tanto de Mercado Pago como de Transbank
 * y confirma el pago en la base de datos
 */

session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mp-config.php';

use MercadoPago\SDK;
use MercadoPago\Payment;
use MercadoPago\Preference;
use Transbank\Webpay\WebpayPlus\Transaction;

// Verificar que haya datos de pago pendiente en sesión
if (!isset($_SESSION['pago_pendiente'])) {
    header('Location: /pago-fallido.php?error=session_expired');
    exit;
}

$pago_pendiente = $_SESSION['pago_pendiente'];
$metodo_pago = $pago_pendiente['metodo_pago'];

try {
    if ($metodo_pago === 'mercadopago') {
        // Procesar respuesta de Mercado Pago
        procesar_respuesta_mercadopago();
    } elseif ($metodo_pago === 'transbank') {
        // Procesar respuesta de Transbank
        procesar_respuesta_transbank();
    } else {
        throw new Exception('Método de pago desconocido');
    }

} catch (Exception $e) {
    error_log("Error al procesar respuesta de pago: " . $e->getMessage());
    header('Location: /pago-fallido.php?error=' . urlencode($e->getMessage()));
    exit;
}

/**
 * Procesar respuesta de Mercado Pago
 */
function procesar_respuesta_mercadopago() {
    global $pago_pendiente;

    // Mercado Pago envía estos parámetros
    $collection_id = $_GET['collection_id'] ?? null;
    $collection_status = $_GET['collection_status'] ?? null;
    $payment_id = $_GET['payment_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $external_reference = $_GET['external_reference'] ?? null;
    $preference_id = $_GET['preference_id'] ?? null;

    // El payment_id es el más confiable
    $mp_payment_id = $payment_id ?: $collection_id;

    if (!$mp_payment_id) {
        throw new Exception('No se recibió ID de pago de Mercado Pago');
    }

    // Obtener información del grupo y access token
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT u.mp_access_token
        FROM wp_grupos_cobranza g
        LEFT JOIN wp_usuarios_app u ON g.id_usuario = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$pago_pendiente['grupo_id']]);
    $grupo_data = $stmt->fetch();

    if (!$grupo_data || empty($grupo_data['mp_access_token'])) {
        throw new Exception('No se pudo obtener access token del tesorero');
    }

    // Configurar SDK con el access token del tesorero
    SDK::setAccessToken($grupo_data['mp_access_token']);

    // Obtener detalles completos del pago desde Mercado Pago
    $payment = Payment::find_by_id($mp_payment_id);

    if (!$payment) {
        throw new Exception('No se pudo obtener información del pago');
    }

    // Log para debugging
    error_log("Pago MP recibido: ID={$mp_payment_id}, Status={$payment->status}, Amount={$payment->transaction_amount}");

    // Procesar según el estado
    if ($payment->status === 'approved') {
        // Pago aprobado
        registrar_pago_exitoso($pago_pendiente, $mp_payment_id, 'mercadopago', $payment);

        // Limpiar sesión
        unset($_SESSION['pago_pendiente']);

        header('Location: /pago-exitoso.php?payment_id=' . $mp_payment_id);
        exit;

    } elseif ($payment->status === 'pending' || $payment->status === 'in_process') {
        // Pago pendiente
        header('Location: /pago-pendiente.php?payment_id=' . $mp_payment_id);
        exit;

    } else {
        // Pago rechazado o fallido
        header('Location: /pago-fallido.php?status=' . $payment->status);
        exit;
    }
}

/**
 * Procesar respuesta de Transbank
 */
function procesar_respuesta_transbank() {
    global $pago_pendiente;

    $token_ws = $_GET['token_ws'] ?? $_POST['token_ws'] ?? null;

    if (!$token_ws) {
        throw new Exception('No se recibió token de Transbank');
    }

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

    // Confirmar transacción
    $response = $transaction->commit($token_ws);

    error_log("Respuesta Transbank: " . json_encode($response));

    // Verificar estado
    if ($response->isApproved()) {
        // Pago aprobado
        registrar_pago_exitoso($pago_pendiente, $response->buy_order, 'transbank', $response);

        // Limpiar sesión
        unset($_SESSION['pago_pendiente']);

        header('Location: /pago-exitoso.php?buy_order=' . $response->buy_order);
        exit;

    } else {
        // Pago rechazado
        header('Location: /pago-fallido.php?response_code=' . $response->response_code);
        exit;
    }
}

/**
 * Registrar pago exitoso en la base de datos
 */
function registrar_pago_exitoso($pago_pendiente, $transaction_id, $metodo_pago, $payment_data = null) {
    $pdo = getConnection();

    try {
        beginTransaction();

        // Preparar statement para insertar pagos
        $stmt = $pdo->prepare("
            INSERT INTO wp_pagos_cuotas
            (id_cuota, rut, fecha_pago, monto_pagado, fee_pagado, metodo_pago, transaction_id, payment_data)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)
        ");

        // Insertar cada cuota como pagada
        foreach ($pago_pendiente['cuotas'] as $cuota) {
            $payment_info = [
                'metodo' => $metodo_pago,
                'transaction_id' => $transaction_id,
                'monto' => $cuota['valor'],
                'fee' => $cuota['fee']
            ];

            if ($payment_data) {
                if ($metodo_pago === 'mercadopago') {
                    $payment_info['mp_payment_id'] = $payment_data->id;
                    $payment_info['mp_status'] = $payment_data->status;
                    $payment_info['mp_payment_type'] = $payment_data->payment_type_id;
                } elseif ($metodo_pago === 'transbank') {
                    $payment_info['tb_authorization_code'] = $payment_data->authorization_code;
                    $payment_info['tb_payment_type_code'] = $payment_data->payment_type_code;
                }
            }

            $stmt->execute([
                $cuota['id'],
                $pago_pendiente['rut'],
                $cuota['valor'],
                $cuota['fee'],
                $metodo_pago,
                $transaction_id,
                json_encode($payment_info)
            ]);
        }

        commit();

        // Log de éxito
        error_log("Pago registrado exitosamente: RUT={$pago_pendiente['rut']}, Método={$metodo_pago}, Transaction={$transaction_id}");

        return true;

    } catch (Exception $e) {
        rollback();
        error_log("Error al registrar pago en BD: " . $e->getMessage());
        throw $e;
    }
}
