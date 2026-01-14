<?php
session_start();
require_once __DIR__ . '/../api/conexion.php';
require_once __DIR__ . '/../api/mp-config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$pdo = getConnection();

// Verificar si ya tiene MP vinculado
$stmt = $pdo->prepare("
    SELECT mp_access_token, mp_user_id, mp_linked_at 
    FROM wp_usuarios_app 
    WHERE id = ?
");
$stmt->execute([$usuario_id]);
$mp_data = $stmt->fetch();

$ya_vinculado = !empty($mp_data['mp_access_token']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vincular Mercado Pago - Quibu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo img {
            max-width: 150px;
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
            font-size: 28px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
        }
        
        .info-box li {
            margin: 10px 0;
            color: #555;
        }
        
        .status-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .status-box.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .status-box h3 {
            color: #155724;
            margin-bottom: 10px;
        }
        
        .status-box.error h3 {
            color: #721c24;
        }
        
        .mp-button {
            display: block;
            width: 100%;
            padding: 18px;
            background: #00B1EA;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .mp-button:hover {
            background: #0099CC;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,177,234,0.3);
        }
        
        .mp-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .desvincular-button {
            background: #dc3545;
            margin-top: 10px;
        }
        
        .desvincular-button:hover {
            background: #c82333;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/pagar/assets/images/logo-quibu.svg" alt="Quibu">
        </div>
        
        <h1>Vincular Mercado Pago</h1>
        
        <?php if ($ya_vinculado): ?>
            <div class="status-box">
                <h3>✅ Tu cuenta ya está vinculada</h3>
                <p><strong>User ID MP:</strong> <?= htmlspecialchars($mp_data['mp_user_id']) ?></p>
                <p><strong>Fecha de vinculación:</strong> <?= date('d/m/Y H:i', strtotime($mp_data['mp_linked_at'])) ?></p>
            </div>
            
            <button onclick="confirmDesvincular()" class="mp-button desvincular-button">
                Desvincular cuenta
            </button>
        <?php else: ?>
            <div class="info-box">
                <h3>¿Por qué necesitas vincular tu cuenta?</h3>
                <ul>
                    <li>📥 Los pagos llegarán <strong>directamente</strong> a tu cuenta de Mercado Pago</li>
                    <li>🚀 Sin esperas ni transferencias manuales</li>
                    <li>🔒 100% seguro - autorizado por Mercado Pago</li>
                    <li>💰 Quibu solo retiene su comisión, el resto es tuyo</li>
                </ul>
            </div>
            
            <a href="<?= generar_url_oauth() ?>" class="mp-button">
                🔗 Conectar con Mercado Pago
            </a>
        <?php endif; ?>
        
        <a href="/dashboard/" class="back-link">← Volver al dashboard</a>
    </div>
    
    <script>
    function confirmDesvincular() {
        if (confirm('¿Estás seguro de que deseas desvincular tu cuenta de Mercado Pago?\n\nNo podrás recibir pagos hasta que vuelvas a vincularla.')) {
            window.location.href = '/api/mp-desvincular.php';
        }
    }
    </script>
</body>
</html>

<?php
/**
 * Generar URL de autorización OAuth de Mercado Pago
 */
function generar_url_oauth() {
    $params = [
        'client_id' => MP_CLIENT_ID,
        'response_type' => 'code',
        'platform_id' => 'mp',
        'redirect_uri' => MP_REDIRECT_URI,
        'state' => generar_state_token()
    ];
    
    return MP_AUTH_URL . '/authorization?' . http_build_query($params);
}

/**
 * Generar token de estado para prevenir CSRF
 */
function generar_state_token() {
    $state = bin2hex(random_bytes(16));
    $_SESSION['mp_oauth_state'] = $state;
    return $state;
}
?>
