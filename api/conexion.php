<?php
/**
 * Quibu - Conexión a Base de Datos
 *
 * Archivo centralizado para la conexión PDO a MySQL
 * Utiliza variables de entorno para credenciales seguras
 */

// Cargar variables de entorno si existe el archivo .env
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envFile as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Saltar comentarios
        }
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Configuración de la base de datos
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'quibu_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtener conexión PDO a la base de datos
 *
 * @return PDO Conexión PDO activa
 * @throws Exception Si la conexión falla
 */
function getConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            error_log("Error de conexión a base de datos: " . $e->getMessage());
            throw new Exception("Error al conectar con la base de datos. Por favor contacta al administrador.");
        }
    }

    return $pdo;
}

/**
 * Ejecutar una consulta preparada de forma segura
 *
 * @param string $sql Consulta SQL con placeholders
 * @param array $params Parámetros para la consulta
 * @return PDOStatement
 */
function query($sql, $params = []) {
    $pdo = getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Iniciar una transacción
 */
function beginTransaction() {
    return getConnection()->beginTransaction();
}

/**
 * Confirmar una transacción
 */
function commit() {
    return getConnection()->commit();
}

/**
 * Revertir una transacción
 */
function rollback() {
    return getConnection()->rollBack();
}
