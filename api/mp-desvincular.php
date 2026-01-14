<?php
/**
 * Quibu - Desvincular cuenta de Mercado Pago
 *
 * Elimina la vinculación de Mercado Pago de un tesorero
 */

session_start();
require_once __DIR__ . '/conexion.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php?error=session_expired');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

try {
    $pdo = getConnection();

    // Obtener información antes de eliminar (para logging)
    $stmt = $pdo->prepare("SELECT mp_user_id, mp_access_token FROM wp_usuarios_app WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $mp_data = $stmt->fetch();

    if ($mp_data && !empty($mp_data['mp_access_token'])) {
        // Opcional: Revocar el access token en Mercado Pago
        // Esto es opcional, ya que el token seguirá siendo válido en MP
        // pero ya no lo usaremos en nuestra aplicación
        // revocar_token_mp($mp_data['mp_access_token']);

        // Eliminar tokens de la base de datos
        $stmt = $pdo->prepare("
            UPDATE wp_usuarios_app
            SET mp_access_token = NULL,
                mp_user_id = NULL,
                mp_public_key = NULL,
                mp_refresh_token = NULL,
                mp_linked_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$usuario_id]);

        // Log de desvinculación exitosa
        error_log("Usuario $usuario_id desvinculó su cuenta de Mercado Pago. MP User ID: " . ($mp_data['mp_user_id'] ?? 'N/A'));

        header('Location: /dashboard/vincular-mercadopago.php?success=desvinculado');
        exit;
    } else {
        // No había cuenta vinculada
        header('Location: /dashboard/vincular-mercadopago.php?error=no_vinculado');
        exit;
    }

} catch (Exception $e) {
    error_log("Error al desvincular Mercado Pago: " . $e->getMessage());
    header('Location: /dashboard/vincular-mercadopago.php?error=error_desvinculacion');
    exit;
}

/**
 * Revocar token en Mercado Pago (opcional)
 *
 * @param string $access_token Token a revocar
 * @return bool
 */
function revocar_token_mp($access_token) {
    require_once __DIR__ . '/mp-config.php';

    $url = MP_API_URL . '/oauth/token';

    $data = [
        'client_id' => MP_CLIENT_ID,
        'client_secret' => MP_CLIENT_SECRET,
        'token' => $access_token
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Error al revocar token MP. HTTP Code: $http_code. Response: $response");
        return false;
    }

    return true;
}
