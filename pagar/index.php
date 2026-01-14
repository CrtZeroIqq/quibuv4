<?php
/**
 * Quibu - Página de Pago Agrupado
 * Permite a los pagadores registrarse y pagar cuotas pendientes
 *
 * URL: https://www.quibu.cl/pagar/?grupo=ID
 */

require_once __DIR__ . '/../api/conexion.php';

// Obtener ID del grupo desde URL
$grupo_id = isset($_GET['grupo']) ? intval($_GET['grupo']) : 0;

if (!$grupo_id) {
    die('
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Quibu</title>
        <link rel="stylesheet" href="assets/css/style.css?v=premium2025">
        <link rel="stylesheet" href="assets/css/stats-dashboardv2.css?v=2025">
    </head>
    <body>
        <div class="container">
            <div class="alert alert-danger">
                <strong>⚠️ Error:</strong> Falta el identificador del grupo en la URL. Por favor verifica el link proporcionado por tu tesorero.
            </div>
        </div>
    </body>
    </html>
    ');
}

try {
    $pdo = getConnection();

    // Obtener información del grupo
    $stmt = $pdo->prepare("
        SELECT g.*, u.nombre as tesorero_nombre, u.email as tesorero_email
        FROM wp_grupos_cobranza g
        LEFT JOIN wp_usuarios_app u ON g.id_usuario = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$grupo_id]);
    $grupo = $stmt->fetch();

    if (!$grupo) {
        die('
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Grupo no encontrado - Quibu</title>
            <link rel="stylesheet" href="assets/css/style.css?v=premium2025">
        </head>
        <body>
            <div class="container">
                <div class="alert alert-danger">
                    <strong>❌ Grupo no encontrado:</strong> El grupo solicitado no existe.
                </div>
            </div>
        </body>
        </html>
        ');
    }

    // Variables del flujo
    $rut = isset($_POST['rut']) ? sanitize_rut($_POST['rut']) : '';
    $paso = 'ingreso_rut'; // ingreso_rut, registro, seleccion_cuotas

    // Procesamiento de formularios
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($rut) {
            // Verificar si el pagador existe
            $stmt = $pdo->prepare("SELECT * FROM wp_pagadores WHERE rut = ? AND id_grupo = ?");
            $stmt->execute([$rut, $grupo_id]);
            $pagador = $stmt->fetch();

            if (!$pagador) {
                // Si no existe y viene del formulario de registro, crear
                if (isset($_POST['nombre']) && isset($_POST['email']) && isset($_POST['telefono'])) {
                    $nombre = sanitize_text_field($_POST['nombre']);
                    $email = sanitize_email($_POST['email']);
                    $telefono = sanitize_text_field($_POST['telefono']);

                    $stmt = $pdo->prepare("
                        INSERT INTO wp_pagadores (id_grupo, rut, nombre, email, telefono)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$grupo_id, $rut, $nombre, $email, $telefono]);

                    $paso = 'seleccion_cuotas';
                    $mensaje_exito = '✅ Registro exitoso. Ya puedes revisar tus cuotas.';
                } else {
                    $paso = 'registro';
                }
            } else {
                $paso = 'seleccion_cuotas';
            }
        }
    }

} catch (Exception $e) {
    error_log("Error en pago agrupado: " . $e->getMessage());
    die('Error al procesar la solicitud.');
}

// Funciones auxiliares
function sanitize_rut($rut) {
    return strtoupper(trim($rut));
}

function sanitize_text_field($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

function sanitize_email($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar Cuotas - <?php echo htmlspecialchars($grupo['nombre_grupo']); ?> - Quibu</title>
    <link rel="stylesheet" href="assets/css/style.css?v=premium2025">
    <link rel="stylesheet" href="assets/css/stats-dashboardv2.css?v=2025">
</head>
<body>
    <div class="container fade-in">
        <div class="header">
            <div class="logo">
                <img src="assets/images/logo-quibu.svg" alt="Quibu Logo" class="logo-img">
            </div>
            <p class="subtitle">Sistema de Cobranza Automatizada</p>
        </div>

        <?php if (isset($mensaje_exito)): ?>
            <div class="alert alert-success">
                <?php echo $mensaje_exito; ?>
            </div>
        <?php endif; ?>

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
                <span class="group-info-label">Tipo:</span>
                <span class="group-info-value"><?php echo htmlspecialchars($grupo['tipo_grupo']); ?></span>
            </div>
        </div>

        <!-- 🆕 NUEVO: Dashboard de Progreso del Grupo -->
        <?php if ($paso === 'ingreso_rut'): ?>
        <div class="grupo-stats-card">
            <div class="stats-header">
                <h2 class="stats-title">
                    <span class="stats-icon">📊</span>
                    Progreso General del Grupo
                </h2>
                <p class="stats-subtitle"><?php echo htmlspecialchars($grupo['nombre_grupo']); ?></p>
            </div>

            <div class="stats-body" id="grupoStatsContainer" data-grupo-id="<?php echo $grupo_id; ?>">
                <div class="stats-loading">
                    <div class="spinner"></div>
                    <p>Cargando estadísticas...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- FIN: Dashboard de Progreso del Grupo -->

        <?php if ($paso === 'ingreso_rut'): ?>
            <!-- PASO 1: Ingreso de RUT -->
            <h2 style="text-align: center; margin-bottom: 24px;">Ingresa tu RUT</h2>
            <form method="POST" id="rut-form">
                <div class="form-group">
                    <label for="rut">RUT (sin puntos, con guión)</label>
                    <input type="text" id="rut" name="rut" placeholder="Ej: 12345678-9" required>
                    <div class="error-message">RUT inválido</div>
                </div>
                <button type="submit" class="btn btn-primary">Continuar</button>
            </form>

        <?php elseif ($paso === 'registro'): ?>
            <!-- PASO 2: Registro de nuevo pagador -->
            <div class="alert alert-info">
                <strong>⚠️ No estás registrado:</strong> Completa tus datos para continuar.
            </div>
            <h2 style="text-align: center; margin-bottom: 24px;">Completa tu Registro</h2>
            <form method="POST" id="registro-form">
                <input type="hidden" name="rut" value="<?php echo htmlspecialchars($rut); ?>">

                <div class="form-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>

                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="telefono">Teléfono (incluye +56)</label>
                    <input type="tel" id="telefono" name="telefono" placeholder="+56912345678" required>
                </div>

                <button type="submit" class="btn btn-success">Registrarme y Continuar</button>
            </form>

        <?php elseif ($paso === 'seleccion_cuotas'): ?>
            <!-- PASO 3: Selección de cuotas -->
            <?php
            // Obtener cuotas del grupo
            $stmt = $pdo->prepare("
                SELECT * FROM wp_cuotas_definidas
                WHERE id_grupo = ?
                ORDER BY fecha_cuota ASC
            ");
            $stmt->execute([$grupo_id]);
            $cuotas_totales = $stmt->fetchAll();

            // Obtener cuotas ya pagadas por este RUT
            $stmt = $pdo->prepare("SELECT id_cuota FROM wp_pagos_cuotas WHERE rut = ?");
            $stmt->execute([$rut]);
            $cuotas_pagadas_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $cantidad_total = count($cuotas_totales);
            $cantidad_pagadas = count($cuotas_pagadas_ids);
            $cantidad_pendientes = $cantidad_total - $cantidad_pagadas;
            ?>

            <div class="alert alert-success">
                ✅ Has pagado <strong><?php echo $cantidad_pagadas; ?></strong> de <strong><?php echo $cantidad_total; ?></strong> cuotas.
            </div>

            <?php if ($cantidad_pendientes > 0): ?>
                <h2 style="text-align: center; margin-bottom: 24px;">Selecciona las Cuotas a Pagar</h2>

                <form method="POST" action="../api/procesar_pago.php" id="form-cuotas">
                    <input type="hidden" name="rut" value="<?php echo htmlspecialchars($rut); ?>">
                    <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">

                    <div class="cuotas-list">
                        <?php
                        foreach ($cuotas_totales as $cuota):
                            $esta_pagada = in_array($cuota['id'], $cuotas_pagadas_ids);
                            $valor_base = floatval($cuota['valor']);

                            // Calcular fee por cuota
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
                            <div class="cuota-item <?php echo $esta_pagada ? 'paid' : ''; ?>">
                                <div class="cuota-info">
                                    <div class="cuota-number">Cuota <?php echo $cuota['nro_cuota']; ?></div>
                                    <div class="cuota-details">
                                        Vencimiento: <?php echo date('d-m-Y', strtotime($cuota['fecha_cuota'])); ?>
                                    </div>
                                </div>
                                <div class="cuota-amount">
                                    $<?php echo number_format($valor_base, 0, ',', '.'); ?>
                                </div>
                                <?php if ($esta_pagada): ?>
                                    <span class="status-badge status-paid">✅ Pagada</span>
                                <?php else: ?>
                                    <input type="checkbox"
                                           class="cuota-checkbox"
                                           name="cuotas[]"
                                           value="<?php echo $cuota['id']; ?>"
                                           data-monto="<?php echo round($valor_con_fee); ?>"
                                           data-monto-base="<?php echo round($valor_base); ?>">
                                <?php endif; ?>
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

        <?php endif; ?>

        <div class="footer">
            <p>Powered by <a href="https://www.quibu.cl">Quibu</a> - Sistema de Cobranza Automatizada</p>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script src="assets/js/stats-dashboardv3.js?v=2025"></script> 
</body>
</html>