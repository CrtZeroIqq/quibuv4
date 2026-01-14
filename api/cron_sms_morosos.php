<?php
// cron_sms_morosos.php

require_once 'conexion.php';
require_once 'twilio_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;

date_default_timezone_set('America/Santiago');

// Opcional: mostrar errores durante pruebas
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function enviarSms($telefono, $mensaje) {
    $client = new Client(TWILIO_SID, TWILIO_AUTH_TOKEN);

    try {
        $client->messages->create(
            $telefono,
            [
                'from' => TWILIO_NUMBER,
                'body' => $mensaje
            ]
        );
        return true;
    } catch (Exception $e) {
        error_log("❌ Error al enviar SMS a $telefono: " . $e->getMessage());
        return false;
    }
}

try {
    $pdo = getConnection();

    // 1. Obtener todos los tesoreros con sms_automatico = 1
    $stmt = $pdo->query("SELECT id, email FROM wp_usuarios_app WHERE sms_automatico = 1");
    $tesoreros = $stmt->fetchAll();

    foreach ($tesoreros as $t) {
        $id_usuario = $t['id'];

        // 2. Obtener grupo de cobranza
        $grupo = $pdo->prepare("SELECT id, nombre_grupo, fecha_inicio FROM wp_grupos_cobranza WHERE id_usuario = ?");
        $grupo->execute([$id_usuario]);
        $grupo = $grupo->fetch();

        if (!$grupo) continue; // ✅ Validación aquí

        $grupo_id = $grupo['id'];
        $grupo_nombre = $grupo['nombre_grupo'];
        $url_pago = "https://www.quibu.cl/pagar/?grupo=$grupo_id";

        $fecha_inicio = new DateTime($grupo['fecha_inicio']);
        $hoy = new DateTime();

        // 3. Calcular cuotas vencidas
        $meses_transcurridos = 0;
        if ($hoy >= $fecha_inicio) {
            $cursor = clone $fecha_inicio;
            while ($cursor < $hoy) {
                $cursor->modify('+1 month');
                if ($cursor <= $hoy) {
                    $meses_transcurridos++;
                }
            }
        }

        // 4. Obtener pagadores
        $pagadores = $pdo->prepare("SELECT id, nombre, telefono FROM wp_pagadores WHERE id_grupo = ?");
        $pagadores->execute([$grupo_id]);
        $pagadores = $pagadores->fetchAll();

        foreach ($pagadores as $p) {
            $pagador_id = $p['id'];
            $telefono = $p['telefono'];
            $nombre = $p['nombre'];

            if (!$telefono) continue;

            // 5. Cuotas pagadas por el usuario
            $pagadas = $pdo->prepare("SELECT COUNT(*) as total FROM wp_pagos_cuotas WHERE id_pagador = ?");
            $pagadas->execute([$pagador_id]);
            $cuotas_pagadas = intval($pagadas->fetch()['total']);

            if ($cuotas_pagadas < $meses_transcurridos) {
                $cuotas_morosas = $meses_transcurridos - $cuotas_pagadas;

                // 6. Enviar SMS
                $mensaje = "Hola $nombre 👋🏼. Tienes $cuotas_morosas cuota(s) pendiente(s) en el grupo \"$grupo_nombre\". Regulariza tu deuda aquí: $url_pago 💳";

                enviarSms($telefono, $mensaje);
            }
        }
    }

    echo json_encode(['status' => 'ok', 'message' => 'Proceso finalizado']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
