<?php
/**
 * Script de debugging para el callback de Mercado Pago
 * Ejecutar desde: https://quibu.cl/debug-mp-callback.php?test=1
 */

require_once __DIR__ . '/api/mp-config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Debug Mercado Pago OAuth</h1>";
echo "<h2>1. Configuración Cargada</h2>";
echo "<pre>";
echo "MP_CLIENT_ID: " . (empty(MP_CLIENT_ID) ? "❌ VACÍO" : "✅ " . MP_CLIENT_ID) . "\n";
echo "MP_CLIENT_SECRET: " . (empty(MP_CLIENT_SECRET) ? "❌ VACÍO" : "✅ " . substr(MP_CLIENT_SECRET, 0, 8) . "...") . "\n";
echo "MP_REDIRECT_URI: " . MP_REDIRECT_URI . "\n";
echo "MP_API_URL: " . MP_API_URL . "\n";
echo "</pre>";

echo "<h2>2. Probar Intercambio de Código (con código de prueba)</h2>";
echo "<p><strong>IMPORTANTE:</strong> Necesitas un código OAuth real para esta prueba.</p>";

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    echo "<p>Intentando intercambiar código: <code>$code</code></p>";

    $url = MP_API_URL . '/oauth/token';

    $data = [
        'client_id' => MP_CLIENT_ID,
        'client_secret' => MP_CLIENT_SECRET,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => MP_REDIRECT_URI
    ];

    echo "<h3>Request a Mercado Pago:</h3>";
    echo "<pre>";
    echo "URL: $url\n";
    echo "Datos:\n";
    print_r([
        'client_id' => MP_CLIENT_ID,
        'client_secret' => substr(MP_CLIENT_SECRET, 0, 8) . '...',
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => MP_REDIRECT_URI
    ]);
    echo "</pre>";

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
    $curl_error = curl_error($ch);
    curl_close($ch);

    echo "<h3>Respuesta de Mercado Pago:</h3>";
    echo "<pre>";
    echo "HTTP Code: $http_code\n";
    if ($curl_error) {
        echo "cURL Error: $curl_error\n";
    }
    echo "Response:\n";
    $response_data = json_decode($response, true);
    if ($response_data) {
        print_r($response_data);
    } else {
        echo $response;
    }
    echo "</pre>";

    if ($http_code === 200 && isset($response_data['access_token'])) {
        echo "<p style='color: green; font-weight: bold;'>✅ ¡TOKEN RECIBIDO EXITOSAMENTE!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ ERROR: No se recibió access_token</p>";

        if (isset($response_data['error'])) {
            echo "<h3>Análisis del Error:</h3>";
            echo "<table border='1' cellpadding='10'>";
            echo "<tr><th>Error</th><td>" . htmlspecialchars($response_data['error']) . "</td></tr>";
            if (isset($response_data['message'])) {
                echo "<tr><th>Mensaje</th><td>" . htmlspecialchars($response_data['message']) . "</td></tr>";
            }
            if (isset($response_data['error_description'])) {
                echo "<tr><th>Descripción</th><td>" . htmlspecialchars($response_data['error_description']) . "</td></tr>";
            }
            echo "</table>";

            echo "<h3>Posibles Causas:</h3><ul>";

            if ($response_data['error'] === 'invalid_client') {
                echo "<li><strong>invalid_client:</strong> El CLIENT_ID o CLIENT_SECRET son incorrectos</li>";
                echo "<li>Verifica que las credenciales en el .env coincidan con las del panel de Mercado Pago</li>";
                echo "<li>Asegúrate de usar credenciales TEST si MP_MODE=test</li>";
            } elseif ($response_data['error'] === 'invalid_grant' || $response_data['error'] === 'code_already_used') {
                echo "<li><strong>Código ya usado o inválido:</strong> Los códigos OAuth son de un solo uso</li>";
                echo "<li>Genera una nueva URL OAuth desde la app y vuelve a autorizar</li>";
            } elseif ($response_data['error'] === 'redirect_uri_mismatch') {
                echo "<li><strong>redirect_uri_mismatch:</strong> El redirect_uri no coincide</li>";
                echo "<li>Verifica que en el panel de MP esté: " . MP_REDIRECT_URI . "</li>";
            }

            echo "</ul>";
        }
    }
} else {
    echo "<p>Para probar con un código real:</p>";
    echo "<ol>";
    echo "<li>Ve a la app y genera una URL OAuth</li>";
    echo "<li>Autoriza en Mercado Pago</li>";
    echo "<li>Cuando seas redirigido al callback y veas el error, copia el parámetro <code>code</code> de la URL</li>";
    echo "<li>Pégalo aquí: <form method='get'>Código: <input type='text' name='code' size='60'> <button>Probar</button></form></li>";
    echo "</ol>";
}

echo "<h2>3. Verificar Logs de Apache</h2>";
echo "<p>Para ver el error exacto, ejecuta en el servidor:</p>";
echo "<pre>sudo tail -50 /var/log/apache2/error.log | grep 'OAuth'</pre>";

echo "<h2>4. Información del Servidor</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "cURL Enabled: " . (function_exists('curl_init') ? "✅ Yes" : "❌ No") . "\n";
echo "Date/Time: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";
