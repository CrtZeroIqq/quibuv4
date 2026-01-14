<?php
session_start();
unset($_SESSION['quibu_pago_mp']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Fallido - Quibu</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .error-box {
            background: white;
            border-radius: 20px;
            padding: 50px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .error-icon {
            font-size: 80px;
            color: #f5576c;
            margin-bottom: 20px;
        }
        h1 {
            color: #f5576c;
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
            background: #f5576c;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .button:hover {
            background: #e44857;
            transform: translateY(-2px);
        }
        .button-secondary {
            background: #667eea;
            margin-left: 10px;
        }
        .button-secondary:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-icon">✗</div>
        <h1>Pago Rechazado</h1>
        <p>No pudimos procesar tu pago</p>
        <p style="font-size: 16px; color: #999;">
            Puede deberse a fondos insuficientes, error del banco o cancelación del proceso.
        </p>
        <p style="font-size: 14px; color: #999;">
            Intenta nuevamente o contacta a soporte si el problema persiste.
        </p>
        <a href="/pagar/" class="button">Intentar nuevamente</a>
        <a href="https://quibu.cl" class="button button-secondary">Volver al inicio</a>
    </div>
</body>
</html>
