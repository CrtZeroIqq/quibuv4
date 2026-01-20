<?php
/**
 * Script de Validación de Configuración de Mercado Pago
 *
 * Verifica que todas las credenciales estén configuradas correctamente
 * Ejecutar desde navegador: https://www.quibu.cl/api/validar-config-mp.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/mp-config.php';

// Validar configuración
$resultado = [
    'timestamp' => date('Y-m-d H:i:s'),
    'validacion' => [],
    'errores' => [],
    'advertencias' => [],
    'exito' => true
];

// 1. Verificar MP_CLIENT_ID
if (empty(MP_CLIENT_ID)) {
    $resultado['errores'][] = 'MP_CLIENT_ID no está configurado en el archivo .env';
    $resultado['validacion']['MP_CLIENT_ID'] = '❌ NO CONFIGURADO';
    $resultado['exito'] = false;
} else {
    $resultado['validacion']['MP_CLIENT_ID'] = '✅ Configurado (' . strlen(MP_CLIENT_ID) . ' caracteres)';
}

// 2. Verificar MP_CLIENT_SECRET
if (empty(MP_CLIENT_SECRET)) {
    $resultado['errores'][] = 'MP_CLIENT_SECRET no está configurado en el archivo .env';
    $resultado['validacion']['MP_CLIENT_SECRET'] = '❌ NO CONFIGURADO';
    $resultado['exito'] = false;
} else {
    $resultado['validacion']['MP_CLIENT_SECRET'] = '✅ Configurado (' . strlen(MP_CLIENT_SECRET) . ' caracteres)';
}

// 3. Verificar MP_REDIRECT_URI
if (empty(MP_REDIRECT_URI)) {
    $resultado['errores'][] = 'MP_REDIRECT_URI no está configurado';
    $resultado['validacion']['MP_REDIRECT_URI'] = '❌ NO CONFIGURADO';
    $resultado['exito'] = false;
} else {
    $resultado['validacion']['MP_REDIRECT_URI'] = '✅ ' . MP_REDIRECT_URI;

    // Verificar que el archivo callback exista
    $callback_path = __DIR__ . '/mp-callback.php';
    if (!file_exists($callback_path)) {
        $resultado['advertencias'][] = 'El archivo mp-callback.php no existe en ' . $callback_path;
    }
}

// 4. Verificar URLs de API
$resultado['validacion']['MP_AUTH_URL'] = MP_AUTH_URL;
$resultado['validacion']['MP_API_URL'] = MP_API_URL;

// 5. Verificar modo
$resultado['validacion']['MP_MODE'] = MP_MODE;
if (MP_MODE === 'sandbox') {
    $resultado['advertencias'][] = 'Mercado Pago está en modo SANDBOX (pruebas)';
}

// 6. Verificar conexión a base de datos
try {
    require_once __DIR__ . '/conexion.php';
    $pdo = getConnection();
    $resultado['validacion']['Base de Datos'] = '✅ Conexión exitosa';

    // Verificar tabla de usuarios
    $stmt = $pdo->query("SHOW TABLES LIKE 'wp_usuarios_app'");
    if ($stmt->rowCount() > 0) {
        $resultado['validacion']['Tabla wp_usuarios_app'] = '✅ Existe';

        // Verificar columnas necesarias
        $stmt = $pdo->query("SHOW COLUMNS FROM wp_usuarios_app");
        $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $columnas_necesarias = ['mp_access_token', 'mp_user_id', 'mp_public_key', 'mp_refresh_token', 'mp_linked_at'];
        foreach ($columnas_necesarias as $col) {
            if (in_array($col, $columnas)) {
                $resultado['validacion']["Columna $col"] = '✅ Existe';
            } else {
                $resultado['errores'][] = "Falta la columna $col en wp_usuarios_app";
                $resultado['validacion']["Columna $col"] = '❌ NO EXISTE';
                $resultado['exito'] = false;
            }
        }
    } else {
        $resultado['errores'][] = 'La tabla wp_usuarios_app no existe';
        $resultado['validacion']['Tabla wp_usuarios_app'] = '❌ NO EXISTE';
        $resultado['exito'] = false;
    }
} catch (Exception $e) {
    $resultado['errores'][] = 'Error de base de datos: ' . $e->getMessage();
    $resultado['validacion']['Base de Datos'] = '❌ ' . $e->getMessage();
    $resultado['exito'] = false;
}

// 7. Generar URL de prueba
if (!empty(MP_CLIENT_ID)) {
    $test_state = bin2hex(random_bytes(16)) . '_999';
    $test_params = [
        'client_id' => MP_CLIENT_ID,
        'response_type' => 'code',
        'platform_id' => 'mp',
        'redirect_uri' => MP_REDIRECT_URI,
        'state' => $test_state
    ];
    $resultado['url_prueba'] = MP_AUTH_URL . '/authorization?' . http_build_query($test_params);
}

// Instrucciones de configuración
if (!$resultado['exito']) {
    $resultado['instrucciones'] = [
        '1. Ve a https://www.mercadopago.cl/developers/panel/app',
        '2. Inicia sesión con tu cuenta de Mercado Pago',
        '3. Crea una nueva aplicación o selecciona una existente',
        '4. Copia el CLIENT_ID y CLIENT_SECRET',
        '5. Edita el archivo /home/user/quibuv4/.env',
        '6. Pega los valores en MP_CLIENT_ID y MP_CLIENT_SECRET',
        '7. En el panel de Mercado Pago, configura el Redirect URI como: ' . MP_REDIRECT_URI,
        '8. Guarda los cambios y vuelve a ejecutar este script'
    ];
}

// Resultado final
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
