<?php
/**
 * Quibu - Configuración de Mercado Pago
 *
 * Configuración para OAuth 2.0 y procesamiento de pagos
 * Documentación: https://www.mercadopago.cl/developers/
 */

// Cargar variables de entorno
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envFile as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = normalizar_valor_env($value);
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

/**
 * Normalizar valores de entorno cargados desde .env
 */
function normalizar_valor_env($value) {
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
        $value = substr($value, 1, -1);
    }

    $hashPos = strpos($value, ' #');
    if ($hashPos !== false) {
        $value = substr($value, 0, $hashPos);
    }

    return trim($value);
}

/**
 * Obtener valor de entorno desde distintas fuentes.
 */
function obtener_valor_env($key) {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    return null;
}

/**
 * CREDENCIALES DE APLICACIÓN MERCADO PAGO
 *
 * Obtener en: https://www.mercadopago.cl/developers/panel/app
 * Se necesita crear una aplicación en el panel de desarrolladores
 */
define('MP_CLIENT_ID', obtener_valor_env('MP_CLIENT_ID') ?? '');
define('MP_CLIENT_SECRET', obtener_valor_env('MP_CLIENT_SECRET') ?? '');

/**
 * CREDENCIALES DE QUIBU (MARKETPLACE/COLLECTOR)
 *
 * Access token de la cuenta de Mercado Pago de Quibu
 * Esta cuenta recibirá los fees (comisiones) de cada transacción
 */
define('QUIBU_MP_ACCESS_TOKEN', obtener_valor_env('QUIBU_MP_ACCESS_TOKEN') ?? '');
define('QUIBU_MP_PUBLIC_KEY', obtener_valor_env('QUIBU_MP_PUBLIC_KEY') ?? '');

/**
 * URLs OAUTH 2.0
 */
define('MP_AUTH_URL', 'https://auth.mercadopago.cl');
define('MP_API_URL', 'https://api.mercadopago.com');

/**
 * URL DE RETORNO OAUTH (Redirect URI)
 * Debe estar configurada en el panel de Mercado Pago
 */
define('MP_REDIRECT_URI', obtener_valor_env('MP_REDIRECT_URI') ?? 'https://www.quibu.cl/api/mp-callback.php');

/**
 * URLs DE RETORNO PARA PAGOS
 */
define('MP_SUCCESS_URL', obtener_valor_env('MP_SUCCESS_URL') ?? 'https://www.quibu.cl/pago-exitoso.php');
define('MP_FAILURE_URL', obtener_valor_env('MP_FAILURE_URL') ?? 'https://www.quibu.cl/pago-fallido.php');
define('MP_PENDING_URL', obtener_valor_env('MP_PENDING_URL') ?? 'https://www.quibu.cl/pago-pendiente.php');

/**
 * WEBHOOK URL
 * URL donde Mercado Pago enviará notificaciones de pagos
 */
define('MP_WEBHOOK_URL', obtener_valor_env('MP_WEBHOOK_URL') ?? 'https://www.quibu.cl/api/mp-webhook.php');

/**
 * MODO DE OPERACIÓN
 * 'sandbox' para testing, 'production' para producción
 */
define('MP_MODE', obtener_valor_env('MP_MODE') ?? 'sandbox');

/**
 * CONFIGURACIÓN DE COMISIONES QUIBU
 * Porcentaje que Quibu retiene de cada transacción
 */
define('QUIBU_COMMISSION_PERCENT', floatval(obtener_valor_env('QUIBU_COMMISSION_PERCENT') ?? 0));

/**
 * Validar configuración
 */
function validar_config_mp() {
    $errores = [];

    if (empty(MP_CLIENT_ID)) {
        $errores[] = 'MP_CLIENT_ID no está configurado';
    }

    if (empty(MP_CLIENT_SECRET)) {
        $errores[] = 'MP_CLIENT_SECRET no está configurado';
    }

    if (empty(MP_REDIRECT_URI)) {
        $errores[] = 'MP_REDIRECT_URI no está configurado';
    }

    return $errores;
}

/**
 * Obtener access token del tesorero desde la base de datos
 *
 * @param int $usuario_id ID del tesorero/usuario
 * @return string|null Access token o null si no está vinculado
 */
function obtener_access_token_usuario($usuario_id) {
    require_once __DIR__ . '/conexion.php';

    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT mp_access_token FROM wp_usuarios_app WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $result = $stmt->fetch();

    return $result ? $result['mp_access_token'] : null;
}

/**
 * Verificar si un usuario tiene Mercado Pago vinculado
 *
 * @param int $usuario_id ID del usuario
 * @return bool
 */
function tiene_mp_vinculado($usuario_id) {
    $token = obtener_access_token_usuario($usuario_id);
    return !empty($token);
}
