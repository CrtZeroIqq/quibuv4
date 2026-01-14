<?php
/**
 * Quibu - Pago Directo para Usuarios Registrados
 * Permite a pagadores ya registrados acceder directamente con su RUT
 *
 * URL: https://www.quibu.cl/pagar/directo.php
 */

require_once __DIR__ . '/../api/conexion.php';

// Aceptar RUT desde POST o GET (para redirección desde pago-landing)
$rut = isset($_POST['rut']) ? strtoupper(trim($_POST['rut'])) : '';
$rut = $rut ?: (isset($_GET['rut']) ? strtoupper(trim(urldecode($_GET['rut']))) : '');
$pagador = null;
$grupo = null;
$error = null;

if ($rut) {
    try {
        $pdo = getConnection();

        // Buscar el pagador por RUT
        $stmt = $pdo->prepare("SELECT * FROM wp_pagadores WHERE rut = ?");
        $stmt->execute([$rut]);
        $pagador = $stmt->fetch();

        if ($pagador) {
            // Obtener información del grupo
            $stmt = $pdo->prepare("
                SELECT g.*, u.nombre as tesorero_nombre
                FROM wp_grupos_cobranza g
                LEFT JOIN wp_usuarios_app u ON g.id_usuario = u.id
                WHERE g.id = ?
            ");
            $stmt->execute([$pagador['id_grupo']]);
            $grupo = $stmt->fetch();

            // Obtener cuotas del grupo
            $stmt = $pdo->prepare("
                SELECT * FROM wp_cuotas_definidas
                WHERE id_grupo = ?
                ORDER BY fecha_cuota ASC
            ");
            $stmt->execute([$pagador['id_grupo']]);
            $cuotas_totales = $stmt->fetchAll();

            // Obtener cuotas pagadas
            $stmt = $pdo->prepare("SELECT id_cuota FROM wp_pagos_cuotas WHERE rut = ?");
            $stmt->execute([$rut]);
            $cuotas_pagadas_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $cantidad_total = count($cuotas_totales);
            $cantidad_pagadas = count($cuotas_pagadas_ids);
            $cantidad_pendientes = $cantidad_total - $cantidad_pagadas;

            // Calcular cuotas pendientes
            $cuotas_pendientes = array_filter($cuotas_totales, function($cuota) use ($cuotas_pagadas_ids) {
                return !in_array($cuota['id'], $cuotas_pagadas_ids);
            });

        } else {
            $error = 'Este RUT no está registrado. Para pagar, necesitas el link con ?grupo=ID proporcionado por tu tesorero.';
        }

    } catch (Exception $e) {
        error_log("Error en pago directo: " . $e->getMessage());
        $error = 'Error al procesar la solicitud.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Directo - Quibu</title>
    <link rel="stylesheet" href="assets/css/style.css?v=premium2025">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <div class="container fade-in">
        <div class="header">
            <div class="logo">
                <img src="assets/images/logo-quibu.svg" alt="Quibu Logo" class="logo-img">
            </div>
            <h2 style="color: var(--primary-color); margin-bottom: 8px;">Pago Directo</h2>
            <p class="subtitle">Acceso rápido para pagadores registrados</p>
        </div>

        <?php if (!$rut || !$pagador): ?>
            <!-- Formulario de ingreso de RUT -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <h2 style="text-align: center; margin-bottom: 24px;">Ingresa tu RUT</h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 24px;">
                Si ya estás registrado en algún grupo, ingresa tu RUT para acceder directamente.
            </p>

            <form method="POST" id="rut-form">
                <div class="form-group">
                    <label for="rut">RUT (sin puntos, con guión)</label>
                    <input type="text" id="rut" name="rut" placeholder="Ej: 12345678-9" required value="<?php echo htmlspecialchars($rut); ?>">
                    <div class="error-message">RUT inválido</div>
                </div>
                <button type="submit" class="btn btn-primary">Continuar</button>
            </form>

            <div style="margin-top: 32px; text-align: center; padding-top: 24px; border-top: 1px solid var(--border-color);">
                <p style="color: var(--text-muted); font-size: 14px;">
                    ¿Primera vez pagando? <a href="index.php" style="color: var(--primary-color); text-decoration: none;">Usa el link proporcionado por tu tesorero</a>
                </p>
            </div>

        <?php else: ?>
            <!-- Información del pagador y sus cuotas -->
            <div class="alert alert-success">
                ✅ Bienvenido/a, <strong><?php echo htmlspecialchars($pagador['nombre']); ?></strong>
            </div>

            <div class="group-info">
                <div class="group-info-item">
                    <span class="group-info-label">Grupo:</span>
                    <span class="group-info-value"><?php echo htmlspecialchars($grupo['nombre_grupo']); ?></span>
                </div>
                <div class="group-info-item">
                    <span class="group-info-label">Tesorero:</span>
                    <span class="group-info-value"><?php echo htmlspecialchars($grupo['tesorero_nombre']); ?></span>
                </div>
                <div class="group-info-item">
                    <span class="group-info-label">Cuotas Pagadas:</span>
                    <span class="group-info-value"><?php echo $cantidad_pagadas; ?> de <?php echo $cantidad_total; ?></span>
                </div>
            </div>

            <?php if ($cantidad_pendientes > 0): ?>
                <h2 style="text-align: center; margin-bottom: 24px;">Cuotas Pendientes</h2>

                <form method="POST" action="../api/procesar_pago.php" id="form-cuotas">
                    <input type="hidden" name="rut" value="<?php echo htmlspecialchars($rut); ?>">
                    <input type="hidden" name="grupo_id" value="<?php echo $grupo['id']; ?>">

                    <div class="cuotas-list">
                        <?php
                        foreach ($cuotas_pendientes as $cuota):
                            $valor_base = floatval($cuota['valor']);

                            // Calcular fee
                            if ($valor_base >= 1000 && $valor_base <= 5000) {
                                $fee_base = 200; $fee_prog = 0.005;
                            } elseif ($valor_base >= 5100 && $valor_base <= 8000) {
                                $fee_base = 220; $fee_prog = 0.006;
                            } elseif ($valor_base >= 8100 && $valor_base <= 10000) {
                                $fee_base = 230; $fee_prog = 0.008;
                            } elseif ($valor_base >= 10100 && $valor_base <= 20000) {
                                $fee_base = 260; $fee_prog = 0.01;
                            } elseif ($valor_base >= 20100 && $valor_base <= 30000) {
                                $fee_base = 300; $fee_prog = 0.01;
                            } else {
                                $fee_base = 300; $fee_prog = 0.01;
                            }

                            $fee_total = $fee_base + ($valor_base * $fee_prog);
                            $valor_con_fee = $valor_base + $fee_total;
                        ?>
                            <div class="cuota-item">
                                <div class="cuota-info">
                                    <div class="cuota-number">Cuota <?php echo $cuota['nro_cuota']; ?></div>
                                    <div class="cuota-details">
                                        Vencimiento: <?php echo date('d-m-Y', strtotime($cuota['fecha_cuota'])); ?>
                                    </div>
                                </div>
                                <div class="cuota-amount">
                                    $<?php echo number_format($valor_base, 0, ',', '.'); ?>
                                </div>
                                <input type="checkbox"
                                       class="cuota-checkbox"
                                       name="cuotas[]"
                                       value="<?php echo $cuota['id']; ?>"
                                       data-monto="<?php echo round($valor_con_fee); ?>"
                                       data-monto-base="<?php echo round($valor_base); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="payment-summary">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal:</span>
                            <span class="summary-value">$<span id="subtotal-pagar">0</span></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Costo Servicio Quibu:</span>
                            <span class="summary-value">$<span id="fee-pagar">0</span></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total a pagar:</span>
                            <span>$<span id="total-pagar">0</span></span>
                        </div>
                    </div>

                    <?php
                    // Verificar si el tesorero tiene Mercado Pago vinculado
                    $tiene_mp = !empty($grupo['mp_access_token']);
                    ?>

                    <div class="payment-method-selector" style="margin: 24px 0;">
                        <h3 style="margin-bottom: 16px; font-size: 18px; color: var(--text-color);">Selecciona método de pago:</h3>

                        <?php if ($tiene_mp): ?>
                        <label class="payment-method-option">
                            <input type="radio" name="metodo_pago" value="mercadopago" checked>
                            <div class="payment-method-card">
                                <img src="https://http2.mlstatic.com/frontend-assets/mp-web-navigation/ui-navigation/5.21.22/mercadopago/logo__large@2x.png" alt="Mercado Pago" style="height: 30px; margin-bottom: 8px;">
                                <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Tarjetas de crédito y débito</p>
                            </div>
                        </label>
                        <?php endif; ?>

                        <label class="payment-method-option">
                            <input type="radio" name="metodo_pago" value="transbank" <?php echo !$tiene_mp ? 'checked' : ''; ?>>
                            <div class="payment-method-card">
                                <img src="https://www.transbank.cl/public/img/Logo_Webpay-3-01.svg" alt="Webpay" style="height: 30px; margin-bottom: 8px;">
                                <p style="font-size: 14px; color: var(--text-muted); margin: 0;">Webpay Plus (Transbank)</p>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-success" disabled>
                        Proceder al Pago
                    </button>
                </form>

            <?php else: ?>
                <div class="alert alert-success" style="text-align: center;">
                    <h2 style="margin-bottom: 12px;">🎉 ¡Felicitaciones!</h2>
                    <p>No tienes cuotas pendientes por pagar. Estás al día con tus obligaciones.</p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 24px; text-align: center;">
                <a href="directo.php" class="btn btn-primary" style="background: var(--text-muted);">
                    Salir
                </a>
            </div>

        <?php endif; ?>

        <div class="footer">
            <p>Powered by <a href="https://www.quibu.cl">Quibu</a> - Sistema de Cobranza Automatizada</p>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
