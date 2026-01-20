#!/bin/bash

###############################################################################
# Script Maestro de Correcciones - Quibu V4
# Aplica todas las correcciones necesarias para adaptar a tu base de datos
###############################################################################

set -e

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "=========================================="
echo "QUIBU V4 - CORRECCIONES DE COMPATIBILIDAD"
echo "=========================================="
echo ""

# Verificar que estamos en el directorio correcto
if [ ! -f "fix-database-schema.sh" ]; then
    echo -e "${RED}Error: Debes ejecutar este script desde /var/www/html/quibuv4${NC}"
    exit 1
fi

echo -e "${YELLOW}Este script aplicará las siguientes correcciones:${NC}"
echo ""
echo "1. ✓ Copiar archivo de estadísticas corregido"
echo "2. ✓ Agregar columna 'descripcion' a wp_cuotas_definidas"
echo "3. ✓ Configurar archivo .env con credenciales correctas"
echo ""
read -p "¿Deseas continuar? (s/n): " CONFIRM

if [ "$CONFIRM" != "s" ]; then
    echo "Cancelado"
    exit 0
fi

echo ""
echo "=========================================="
echo "PASO 1: Copiar archivos corregidos"
echo "=========================================="

# Copiar archivo de estadísticas corregido
if [ -f "/var/www/html/quibuv4/api/estadisticas_publicas_grupo.php" ]; then
    echo "Copiando estadisticas_publicas_grupo.php..."
    sudo cp /var/www/html/quibuv4/api/estadisticas_publicas_grupo.php /var/www/html/api/
    sudo chown www-data:www-data /var/www/html/api/estadisticas_publicas_grupo.php
    echo -e "${GREEN}✓ Archivo copiado${NC}"
else
    echo -e "${RED}✗ Archivo fuente no encontrado${NC}"
fi

echo ""
echo "=========================================="
echo "PASO 2: Corregir esquema de base de datos"
echo "=========================================="

# Solicitar credenciales
read -p "Usuario MySQL (root): " MYSQL_USER
MYSQL_USER=${MYSQL_USER:-root}

read -sp "Contraseña MySQL: " MYSQL_PASS
echo ""

read -p "Nombre de la base de datos: " DB_NAME

echo ""
echo "Aplicando correcciones a la base de datos..."

mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" <<'EOF'

-- Verificar y agregar columna descripcion solo si no existe
SET @dbname = DATABASE();
SET @tablename = "wp_cuotas_definidas";
SET @columnname = "descripcion";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Columna descripcion ya existe' as resultado;",
  "ALTER TABLE wp_cuotas_definidas ADD COLUMN descripcion VARCHAR(255) DEFAULT NULL AFTER valor;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SELECT 'Correcciones aplicadas exitosamente' as resultado;

EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Esquema de base de datos corregido${NC}"
else
    echo -e "${RED}✗ Error al corregir base de datos${NC}"
    exit 1
fi

echo ""
echo "=========================================="
echo "PASO 3: Configurar archivo .env"
echo "=========================================="

# Verificar si existe .env
if [ ! -f "/var/www/html/.env" ]; then
    echo "Creando archivo .env..."
    sudo cp /var/www/html/.env.example /var/www/html/.env
fi

echo ""
echo "Configurando credenciales de base de datos en .env..."

# Actualizar .env con las credenciales correctas
sudo sed -i "s/^DB_NAME=.*/DB_NAME=$DB_NAME/" /var/www/html/.env
sudo sed -i "s/^DB_USER=.*/DB_USER=$MYSQL_USER/" /var/www/html/.env
sudo sed -i "s/^DB_PASS=.*/DB_PASS=$MYSQL_PASS/" /var/www/html/.env

sudo chmod 600 /var/www/html/.env
sudo chown www-data:www-data /var/www/html/.env

echo -e "${GREEN}✓ Archivo .env configurado${NC}"

echo ""
echo "=========================================="
echo "VERIFICACIÓN"
echo "=========================================="
echo ""

# Probar conexión a base de datos
echo "Probando conexión a la base de datos..."

php -r "
require '/var/www/html/api/conexion.php';
try {
    \$pdo = getConnection();
    echo '✓ Conexión exitosa a la base de datos' . PHP_EOL;
} catch (Exception \$e) {
    echo '✗ Error de conexión: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Todas las verificaciones pasaron${NC}"
else
    echo -e "${RED}✗ Algunas verificaciones fallaron${NC}"
    exit 1
fi

echo ""
echo "=========================================="
echo "¡CORRECCIONES COMPLETADAS!"
echo "=========================================="
echo ""
echo -e "${GREEN}Ahora puedes probar:${NC}"
echo ""
echo "1. Landing page:"
echo "   https://www.quibu.cl/pago-landing/"
echo ""
echo "2. Página del grupo 10:"
echo "   https://www.quibu.cl/pagar/?grupo=10"
echo ""
echo "3. API de estadísticas:"
echo "   https://www.quibu.cl/api/estadisticas_publicas_grupo.php?idGrupo=10"
echo ""
echo -e "${YELLOW}NOTA IMPORTANTE:${NC}"
echo "El método de pago 'Mercado Pago' aparecerá cuando el tesorero"
echo "vincule su cuenta de Mercado Pago desde el dashboard."
echo ""
echo "Por ahora, solo estará disponible Webpay (Transbank)."
echo ""
