<?php
session_start();
$datos_pago = $_SESSION['quibu_pago_mp'] ?? null;
unset($_SESSION['quibu_pago_mp']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Pendiente - Quibu</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .pending-box {
            background: white;
            border-radius: 20px;
            padding: 50px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .pending-icon {
            font-size: 80px;
            color: #ff9800;
            margin-bottom: 20px;
        }
        h1 {
            color: #ff9800;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            font-size: 18px;
            margin: 15px 0;
        }
        .button {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 40px;
            background: #ff9800;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .button:hover {
            background: #e68900;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="pending-box">
        <div class="pending-icon">⏳</div>
        <h1>Pago Pendiente</h1>
        <p>Tu pago está siendo procesado</p>
        <p style="font-size: 16px; color: #999;">
            Te notificaremos cuando se confirme el pago.
        </p>
        <p style="font-size: 14px; color: #999;">
            Esto puede tomar algunos minutos.
        </p>
        <a href="https://quibu.cl" class="button">Volver al inicio</a>
    </div>
</body>
</html>
