<?php
/**
 * Quibu - Registro de Usuario
 * Registra un nuevo usuario (tesorero) en la plataforma
 */

require_once __DIR__ . '/conexion.php';

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Función para responder con JSON
 */
function respond($success, $data = [], $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validar RUT chileno
 */
function validar_rut($rut) {
    // Limpiar RUT
    $rut = preg_replace('/[^0-9kK]/', '', $rut);

    if (strlen($rut) < 2) {
        return false;
    }

    $dv = strtoupper(substr($rut, -1));
    $numero = substr($rut, 0, -1);

    // Calcular dígito verificador
    $suma = 0;
    $multiplo = 2;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += $numero[$i] * $multiplo;
        $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
    }

    $resto = $suma % 11;
    $dv_calculado = 11 - $resto;

    if ($dv_calculado == 11) {
        $dv_calculado = '0';
    } elseif ($dv_calculado == 10) {
        $dv_calculado = 'K';
    } else {
        $dv_calculado = (string)$dv_calculado;
    }

    return $dv === $dv_calculado;
}

try {
    // Solo aceptar POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, [], 'Método no permitido. Use POST.', 405);
    }

    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);

    // Si no viene JSON, intentar con POST normal
    if (!$input) {
        $input = $_POST;
    }

    // Validar campos requeridos
    $required_fields = ['nombre', 'email', 'rut'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        respond(false, [], 'Faltan campos requeridos: ' . implode(', ', $missing_fields), 400);
    }

    // Extraer y limpiar datos
    $nombre = trim($input['nombre']);
    $email = trim(strtolower($input['email']));
    $rut = trim($input['rut']);
    $telefono = isset($input['telefono']) ? trim($input['telefono']) : null;
    $ciudad = isset($input['ciudad']) ? trim($input['ciudad']) : null;

    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, [], 'El email no es válido.', 400);
    }

    // Validar RUT
    if (!validar_rut($rut)) {
        respond(false, [], 'El RUT no es válido.', 400);
    }

    // Conectar a base de datos
    $pdo = getConnection();

    // Verificar si el email ya existe
    $stmt = $pdo->prepare("SELECT id FROM wp_usuarios_app WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        respond(false, [], 'Ya existe un usuario registrado con este email.', 409);
    }

    // Verificar si el RUT ya existe
    $stmt = $pdo->prepare("SELECT id FROM wp_usuarios_app WHERE rut = ? LIMIT 1");
    $stmt->execute([$rut]);

    if ($stmt->fetch()) {
        respond(false, [], 'Ya existe un usuario registrado con este RUT.', 409);
    }

    // Insertar nuevo usuario
    $stmt = $pdo->prepare("
        INSERT INTO wp_usuarios_app (
            nombre,
            email,
            rut,
            telefono,
            ciudad,
            sms_automatico
        ) VALUES (?, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        $nombre,
        $email,
        $rut,
        $telefono,
        $ciudad
    ]);

    $user_id = $pdo->lastInsertId();

    // Obtener el usuario recién creado
    $stmt = $pdo->prepare("
        SELECT
            id,
            nombre,
            email,
            rut,
            telefono,
            ciudad,
            created_at
        FROM wp_usuarios_app
        WHERE id = ?
    ");

    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Responder con éxito
    respond(true, [
        'usuario' => [
            'id' => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'rut' => $usuario['rut'],
            'telefono' => $usuario['telefono'],
            'ciudad' => $usuario['ciudad'],
            'created_at' => $usuario['created_at']
        ]
    ], 'Usuario registrado exitosamente', 201);

} catch (PDOException $e) {
    error_log("Error en register.php: " . $e->getMessage());
    respond(false, [], 'Error al registrar usuario. Intente nuevamente.', 500);
} catch (Exception $e) {
    error_log("Error general en register.php: " . $e->getMessage());
    respond(false, [], 'Error inesperado. Intente nuevamente.', 500);
}
