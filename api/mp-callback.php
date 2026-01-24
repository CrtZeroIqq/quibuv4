<?php
/**
 * Quibu - Callback OAuth de Mercado Pago v2
 *
 * Recibe el código de autorización de Mercado Pago y lo intercambia por un access token
 * Versión mejorada que no depende de sesiones (compatible con app móvil)
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mp-config.php';

// Verificar que vengan los parámetros necesarios
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    mostrar_error('Parámetros faltantes en el callback de Mercado Pago');
}

$code = $_GET['code'];
$state = $_GET['state'];

try {
    $pdo = getConnection();

    // Extraer usuario_id del state token (formato: random_hex_usuario_id)
    $state_parts = explode('_', $state);
    $usuario_id = end($state_parts); // Último elemento es el usuario_id

    if (!is_numeric($usuario_id) || $usuario_id <= 0) {
        throw new Exception('State token inválido');
    }

    // Verificar que el usuario exista
    $stmt = $pdo->prepare("SELECT id, nombre, email FROM wp_usuarios_app WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }

    // Intercambiar el código por un access token
    $token_data = intercambiar_codigo_por_token($code);

    if (isset($token_data['error']) && should_retry_redirect_uri($token_data)) {
        $token_data = intercambiar_codigo_por_token($code, obtener_redirect_uri_actual());
    }

    if (!$token_data || !isset($token_data['access_token'])) {
        // Loguear la respuesta completa para debugging
        error_log("Error intercambiando código OAuth. Response: " . json_encode($token_data));

        $error_msg = 'No se recibió access token de Mercado Pago.';
        if (isset($token_data['message'])) {
            $error_msg .= ' Mensaje: ' . $token_data['message'];
        }
        if (isset($token_data['error'])) {
            $error_msg .= ' Error: ' . $token_data['error'];
        }

        throw new Exception($error_msg);
    }

    // Obtener información del usuario de Mercado Pago
    $mp_user_info = obtener_info_usuario_mp($token_data['access_token']);

    // Guardar el access token en la base de datos
    $stmt = $pdo->prepare("
        UPDATE wp_usuarios_app
        SET mp_access_token = ?,
            mp_user_id = ?,
            mp_public_key = ?,
            mp_refresh_token = ?,
            mp_linked_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $token_data['access_token'],
        $mp_user_info['id'] ?? null,
        $token_data['public_key'] ?? null,
        $token_data['refresh_token'] ?? null,
        $usuario_id
    ]);

    // Log exitoso
    error_log("Usuario $usuario_id vinculó Mercado Pago exitosamente. MP User ID: " . ($mp_user_info['id'] ?? 'N/A'));

    // Mostrar página de éxito (usuario ya fue obtenido arriba)
    mostrar_exito($usuario['nombre'] ?? 'Usuario', $mp_user_info);

} catch (Exception $e) {
    error_log("Error en OAuth de Mercado Pago: " . $e->getMessage());
    mostrar_error($e->getMessage());
}

/**
 * Intercambiar código de autorización por access token
 */
function intercambiar_codigo_por_token($code, $redirect_uri = null) {
    $url = MP_API_URL . '/oauth/token';

    $data = [
        'client_id' => MP_CLIENT_ID,
        'client_secret' => MP_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri ?: MP_REDIRECT_URI
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $curl_error = curl_error($ch);
        curl_close($ch);
        error_log("Error de cURL al obtener token de MP: $curl_error");
        return ['error' => 'curl_error', 'message' => $curl_error];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($http_code < 200 || $http_code >= 300) {
        error_log("Error al obtener token de MP. HTTP Code: $http_code. Response: $response");
        return is_array($decoded) ? $decoded : ['error' => 'http_error', 'message' => $response];
    }

    return is_array($decoded) ? $decoded : [];
}

/**
 * Determinar si conviene reintentar con la URL real del callback.
 */
function should_retry_redirect_uri($token_data) {
    if (!is_array($token_data)) {
        return false;
    }

    $error = strtolower($token_data['error'] ?? '');
    $message = strtolower($token_data['message'] ?? '');

    return $error === 'invalid_grant' || strpos($message, 'redirect_uri') !== false;
}

/**
 * Obtener la URL real del callback (sin querystring).
 */
function obtener_redirect_uri_actual() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'www.quibu.cl';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/api/mp-callback.php', PHP_URL_PATH);

    return $scheme . '://' . $host . $path;
}

/**
 * Obtener información del usuario de Mercado Pago
 */
function obtener_info_usuario_mp($access_token) {
    $url = MP_API_URL . '/users/me';

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
        error_log("Error al obtener info de usuario MP. HTTP Code: $http_code. Response: $response");
        return [];
    }

    return json_decode($response, true);
}

/**
 * Mostrar página de éxito
 */
function mostrar_exito($nombre_usuario, $mp_user_info) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>¡Mercado Pago Vinculado! - Quibu</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #00A650 0%, #009EE3 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 20px;
                padding: 40px;
                max-width: 500px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            .icon {
                font-size: 80px;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 28px;
            }
            .subtitle {
                color: #666;
                margin-bottom: 30px;
                font-size: 16px;
            }
            .info-box {
                background: #f5f5f5;
                border-radius: 12px;
                padding: 20px;
                margin: 20px 0;
                text-align: left;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                margin: 10px 0;
                font-size: 14px;
            }
            .info-label {
                color: #666;
                font-weight: 600;
            }
            .info-value {
                color: #333;
            }
            .btn {
                display: inline-block;
                padding: 14px 30px;
                background: #00A650;
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
                margin-top: 20px;
                transition: transform 0.2s;
            }
            .btn:hover {
                transform: translateY(-2px);
            }
            .instructions {
                background: #e3f2fd;
                border-left: 4px solid #2196F3;
                padding: 15px;
                margin-top: 20px;
                text-align: left;
                border-radius: 8px;
            }
            .instructions p {
                color: #1976D2;
                font-size: 14px;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">✅</div>
            <h1>¡Cuenta Vinculada!</h1>
            <p class="subtitle">Hola <?php echo htmlspecialchars($nombre_usuario); ?>, tu cuenta de Mercado Pago se vinculó exitosamente.</p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Usuario MP:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mp_user_info['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">ID de Usuario:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mp_user_info['id'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Estado:</span>
                    <span class="info-value" style="color: #00A650; font-weight: 600;">✓ Activo</span>
                </div>
            </div>

            <div class="instructions">
                <p><strong>📱 Si viniste desde la app móvil:</strong></p>
                <p>Puedes cerrar esta ventana y volver a la aplicación Quibu. Tu cuenta ya está vinculada y lista para recibir pagos.</p>
            </div>

            <a href="javascript:window.close();" class="btn">Cerrar esta Ventana</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Mostrar página de error
 */
function mostrar_error($mensaje) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Quibu</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 20px;
                padding: 40px;
                max-width: 500px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            .icon {
                font-size: 80px;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 28px;
            }
            .error-message {
                background: #ffebee;
                border-left: 4px solid #f44336;
                padding: 15px;
                margin: 20px 0;
                text-align: left;
                border-radius: 8px;
            }
            .error-message p {
                color: #c62828;
                font-size: 14px;
            }
            .btn {
                display: inline-block;
                padding: 14px 30px;
                background: #f44336;
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">❌</div>
            <h1>Error al Vincular</h1>
            <p>No se pudo completar la vinculación con Mercado Pago.</p>

            <div class="error-message">
                <p><strong>Detalle del error:</strong></p>
                <p><?php echo htmlspecialchars($mensaje); ?></p>
            </div>

            <a href="javascript:window.close();" class="btn">Cerrar</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
