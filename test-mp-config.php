<?php
/**
 * Script de prueba para verificar que mp-config.php lee correctamente el .env
 */

require_once __DIR__ . '/api/mp-config.php';

echo "=== VERIFICACIÓN DE CONFIGURACIÓN MERCADO PAGO ===\n\n";

echo "MP_CLIENT_ID: " . (empty(MP_CLIENT_ID) ? "❌ VACÍO" : "✅ Configurado (" . strlen(MP_CLIENT_ID) . " caracteres)") . "\n";
echo "MP_CLIENT_SECRET: " . (empty(MP_CLIENT_SECRET) ? "❌ VACÍO" : "✅ Configurado (" . strlen(MP_CLIENT_SECRET) . " caracteres)") . "\n";
echo "MP_REDIRECT_URI: " . MP_REDIRECT_URI . "\n";
echo "MP_AUTH_URL: " . MP_AUTH_URL . "\n";
echo "MP_API_URL: " . MP_API_URL . "\n";
echo "MP_MODE: " . MP_MODE . "\n\n";

$errores = validar_config_mp();

if (empty($errores)) {
    echo "✅ Configuración válida - OAuth debería funcionar\n";
} else {
    echo "❌ Errores encontrados:\n";
    foreach ($errores as $error) {
        echo "  - $error\n";
    }
}
