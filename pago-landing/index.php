<?php
/**
 * Quibu - Landing Page Principal
 * Página de entrada al sistema de pagos
 */

require_once __DIR__ . '/../api/conexion.php';

$rut = '';
$error = null;
$modo = 'landing'; // landing, grupo_encontrado, grupo_no_encontrado

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rut'])) {
    try {
        $pdo = getConnection();
        $rut = strtoupper(trim($_POST['rut']));

        // Buscar si el RUT está registrado en algún grupo
        $stmt = $pdo->prepare("
            SELECT p.*, g.nombre_grupo, g.id as grupo_id, u.nombre as tesorero_nombre
            FROM wp_pagadores p
            LEFT JOIN wp_grupos_cobranza g ON p.id_grupo = g.id
            LEFT JOIN wp_usuarios_app u ON g.id_usuario = u.id
            WHERE p.rut = ?
            LIMIT 1
        ");
        $stmt->execute([$rut]);
        $pagador = $stmt->fetch();

        if ($pagador) {
            // Redirigir al pago directo
            $url_redirect = 'https://quibu.cl/pagar/directo.php?rut=' . urlencode($rut);
            header("Location: " . $url_redirect);
            exit;
        } else {
            $modo = 'grupo_no_encontrado';
        }

    } catch (Exception $e) {
        error_log("Error en pago-landing: " . $e->getMessage());
        $error = 'Error al procesar la solicitud.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quibu - Sistema de Cobranza Automatizada</title>
    <link rel="stylesheet" href="/pagar/assets/css/style.css?v=premium2025">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        /* Estilos adicionales para la landing */
        .hero {
            text-align: center;
            padding: 48px 0;
            background: linear-gradient(135deg, rgba(85, 59, 255, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: 16px;
            margin-bottom: 32px;
        }

        .hero-icon {
            font-size: 72px;
            margin-bottom: 16px;
        }

        .hero h1 {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 12px;
        }

        .hero-subtitle {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 500px;
            margin: 0 auto 32px;
            line-height: 1.6;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .feature {
            text-align: center;
            padding: 20px;
            background: var(--light-bg);
            border-radius: 12px;
            transition: transform 0.3s ease;
        }

        .feature:hover {
            transform: translateY(-4px);
        }

        .feature-icon {
            font-size: 36px;
            margin-bottom: 12px;
        }

        .feature-title {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .feature-desc {
            font-size: 13px;
            color: var(--text-muted);
        }

        .option-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
        }

        .option-card h3 {
            color: var(--primary-color);
            margin-bottom: 8px;
            font-size: 18px;
        }

        .option-card p {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
        }

        .divider {
            text-align: center;
            margin: 32px 0;
            color: var(--text-muted);
            font-size: 14px;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: var(--border-color);
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }
    </style>
</head>
<body>
    <div class="container fade-in">
        <?php if ($modo === 'landing'): ?>
            <!-- Landing Principal -->
            <div class="hero">
                <div class="logo">
                    <img src="/pagar/assets/images/logo-quibu.svg" alt="Quibu Logo" class="logo-img">
                </div>
                <h1>Bienvenido a Quibu</h1>
                <p class="hero-subtitle">
                    Sistema de cobranza automatizada para grupos. Paga tus cuotas de forma rápida y segura.
                </p>
            </div>

            <div class="features">
                <div class="feature">
                    <div class="feature-icon">⚡</div>
                    <div class="feature-title">Rápido</div>
                    <div class="feature-desc">Paga en segundos</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">🔒</div>
                    <div class="feature-title">Seguro</div>
                    <div class="feature-desc">Transbank WebPay</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">📱</div>
                    <div class="feature-title">Simple</div>
                    <div class="feature-desc">Solo tu RUT</div>
                </div>
            </div>

            <h2 style="text-align: center; margin-bottom: 24px; font-size: 20px;">¿Cómo quieres pagar?</h2>

            <!-- Opción 1: Si ya estás registrado -->
            <div class="option-card" onclick="document.getElementById('rut-section').scrollIntoView({behavior: 'smooth'})">
                <h3>✅ Ya estoy registrado</h3>
                <p>Ingresa tu RUT y accede directamente a tus cuotas pendientes</p>
            </div>

            <!-- Opción 2: Si tienes link del tesorero -->
            <div class="option-card" onclick="alert('Usa el link que te proporcionó tu tesorero.\nEjemplo: quibu.cl/pagar/?grupo=10')">
                <h3>🔗 Tengo el link de mi grupo</h3>
                <p>Si tu tesorero te envió un link específico, úsalo directamente</p>
            </div>

            <div class="divider">o ingresa tu RUT</div>

            <!-- Formulario de RUT -->
            <div id="rut-section">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="rut-form">
                    <div class="form-group">
                        <label for="rut">RUT (sin puntos, con guión)</label>
                        <input type="text"
                               id="rut"
                               name="rut"
                               placeholder="Ej: 12345678-9"
                               required
                               value="<?php echo htmlspecialchars($rut); ?>"
                               style="font-size: 18px; padding: 16px;">
                        <div class="error-message">RUT inválido</div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 16px;">
                        Buscar mis Cuotas
                    </button>
                </form>
            </div>

        <?php elseif ($modo === 'grupo_no_encontrado'): ?>
            <!-- RUT no encontrado -->
            <div class="alert alert-danger">
                <strong>⚠️ RUT no registrado:</strong> No encontramos tu RUT en nuestro sistema.
            </div>

            <div class="group-info">
                <h3 style="margin-bottom: 16px; color: var(--text-color);">¿Qué puedes hacer?</h3>

                <div style="margin-bottom: 16px;">
                    <strong>1. Usa el link de tu grupo</strong>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">
                        Solicita a tu tesorero el link específico de tu grupo.
                        Se ve así: <code style="background: var(--light-bg); padding: 2px 6px; border-radius: 4px;">quibu.cl/pagar/?grupo=10</code>
                    </p>
                </div>

                <div style="margin-bottom: 16px;">
                    <strong>2. Regístrate con el link</strong>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">
                        Al usar el link de tu grupo, podrás registrarte por primera vez ingresando tu nombre, email y teléfono.
                    </p>
                </div>

                <div>
                    <strong>3. Contacta a tu tesorero</strong>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">
                        Si tienes dudas, contacta al administrador de tu grupo de cobranza.
                    </p>
                </div>
            </div>

            <div style="margin-top: 32px; text-align: center;">
                <a href="/pago-landing/" class="btn btn-primary">
                    Intentar con otro RUT
                </a>
            </div>

        <?php endif; ?>

        <div class="footer">
            <p>Powered by <a href="https://www.quibu.cl">Quibu</a> - Sistema de Cobranza Automatizada</p>
            <p style="margin-top: 8px; font-size: 12px;">
                <a href="/pagar/directo.php">Acceso Directo</a> ·
                <a href="#">Ayuda</a> ·
                <a href="#">Términos y Condiciones</a>
            </p>
        </div>
    </div>

    <script src="/pagar/assets/js/app.js"></script>
</body>
</html>
