<?php
/**
 * Script de prueba para verificar generación de URL OAuth
 */

require_once __DIR__ . '/api/conexion.php';
require_once __DIR__ . '/api/mp-config.php';

echo "=== PRUEBA DE GENERACIÓN DE URL OAUTH ===\n\n";

// Simular usuario_id = 27 (del login que probaste antes)
$usuario_id = 27;

try {
    $pdo = getConnection();

    // Verificar que el usuario exista
    $stmt = $pdo->prepare("SELECT id, email, nombre FROM wp_usuarios_app WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        echo "❌ Usuario $usuario_id no encontrado\n";
        exit(1);
    }

    echo "Usuario encontrado:\n";
    echo "  ID: {$usuario['id']}\n";
    echo "  Nombre: {$usuario['nombre']}\n";
    echo "  Email: {$usuario['email']}\n\n";

    // Generar state token
    $state = bin2hex(random_bytes(16)) . '_' . $usuario_id;
    echo "State token generado: $state\n\n";

    // Construir URL de autorización
    $params = [
        'client_id' => MP_CLIENT_ID,
        'response_type' => 'code',
        'platform_id' => 'mp',
        'redirect_uri' => MP_REDIRECT_URI,
        'state' => $state
    ];

    $auth_url = MP_AUTH_URL . '/authorization?' . http_build_query($params);

    echo "✅ URL de autorización generada:\n";
    echo "$auth_url\n\n";

    echo "Para probar:\n";
    echo "1. Copia esta URL y ábrela en tu navegador\n";
    echo "2. Inicia sesión en Mercado Pago\n";
    echo "3. Autoriza la aplicación Quibu\n";
    echo "4. Deberías ser redirigido a: " . MP_REDIRECT_URI . "\n";
    echo "5. El callback procesará el código y guardará el token\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
