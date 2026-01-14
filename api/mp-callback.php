<?php
/**
 * Quibu - Callback OAuth de Mercado Pago
 *
 * Recibe el código de autorización de Mercado Pago y lo intercambia por un access token
 * Documentación: https://www.mercadopago.cl/developers/es/docs/security/oauth/introduction
 */

session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mp-config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php?error=session_expired');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar que vengan los parámetros necesarios
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    header('Location: /dashboard/vincular-mercadopago.php?error=parametros_faltantes');
    exit;
}

$code = $_GET['code'];
$state = $_GET['state'];

// Validar state token para prevenir CSRF
if (!isset($_SESSION['mp_oauth_state']) || $_SESSION['mp_oauth_state'] !== $state) {
    header('Location: /dashboard/vincular-mercadopago.php?error=invalid_state');
    exit;
}

// Limpiar el state token
unset($_SESSION['mp_oauth_state']);

try {
    // Intercambiar el código por un access token
    $token_data = intercambiar_codigo_por_token($code);

    if (!$token_data || !isset($token_data['access_token'])) {
        throw new Exception('No se recibió access token de Mercado Pago');
    }

    // Obtener información del usuario de Mercado Pago
    $mp_user_info = obtener_info_usuario_mp($token_data['access_token']);

    // Guardar el access token en la base de datos
    $pdo = getConnection();
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

    // Redireccionar con éxito
    header('Location: /dashboard/vincular-mercadopago.php?success=1');
    exit;

} catch (Exception $e) {
    error_log("Error en OAuth de Mercado Pago: " . $e->getMessage());
    header('Location: /dashboard/vincular-mercadopago.php?error=' . urlencode($e->getMessage()));
    exit;
}

/**
 * Intercambiar código de autorización por access token
 *
 * @param string $code Código de autorización
 * @return array|null Token data o null en caso de error
 */
function intercambiar_codigo_por_token($code) {
    $url = MP_API_URL . '/oauth/token';

    $data = [
        'client_id' => MP_CLIENT_ID,
        'client_secret' => MP_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => MP_REDIRECT_URI
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Error al obtener token de MP. HTTP Code: $http_code. Response: $response");
        return null;
    }

    return json_decode($response, true);
}

/**
 * Obtener información del usuario de Mercado Pago
 *
 * @param string $access_token Access token del usuario
 * @return array Información del usuario
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
