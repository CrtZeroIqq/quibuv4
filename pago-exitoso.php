<?php
session_start();

$datos_pago = $_SESSION['quibu_pago_mp'] ?? null;

// Limpiar sesión
unset($_SESSION['quibu_pago_mp']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Exitoso - Quibu</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .success-box {
            background: white;
            border-radius: 20px;
            padding: 50px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .success-icon {
            font-size: 80px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #4CAF50;
            margin-bottom: 10px;
        }
        
        p {
            color: #666;
            font-size: 18px;
            margin: 15px 0;
        }
        
        .detalle {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        
        .detalle-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detalle-item:last-child {
            border-bottom: none;
        }
        
        .detalle-label {
            font-weight: bold;
            color: #333;
        }
        
        .detalle-value {
            color: #666;
        }
        
        .button {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 40px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="success-box">
        <div class="success-icon">✓</div>
        <h1>¡Pago Exitoso!</h1>
        <p>Tu pago ha sido procesado correctamente</p>
        
        <?php if ($datos_pago): ?>
        <div class="detalle">
            <div class="detalle-item">
                <span class="detalle-label">Grupo:</span>
                <span class="detalle-value">#<?= htmlspecialchars($datos_pago['grupo_id']) ?></span>
            </div>
            <div class="detalle-item">
                <span class="detalle-label">Total Pagado:</span>
                <span class="detalle-value">$<?= number_format($datos_pago['total'], 0, ',', '.') ?></span>
            </div>
            <div class="detalle-item">
                <span class="detalle-label">Cuotas:</span>
                <span class="detalle-value"><?= count($datos_pago['cuotas']) ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <p style="font-size: 14px; color: #999;">
            Recibirás una confirmación por email
        </p>
        
        <a href="https://quibu.cl" class="button">Volver al inicio</a>
    </div>
</body>
</html>
