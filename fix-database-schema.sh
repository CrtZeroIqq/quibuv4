#!/bin/bash

###############################################################################
# Script de Corrección de Esquema para Quibu V4
# Adapta la base de datos existente para compatibilidad total
###############################################################################

echo "=========================================="
echo "Corrección de Esquema - Quibu V4"
echo "=========================================="
echo ""

# Solicitar credenciales
read -p "Usuario MySQL (root): " MYSQL_USER
MYSQL_USER=${MYSQL_USER:-root}

read -sp "Contraseña MySQL: " MYSQL_PASS
echo ""

read -p "Nombre de la base de datos: " DB_NAME

echo ""
echo "Aplicando correcciones..."
echo ""

# Ejecutar el script SQL
mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" <<EOF

-- Agregar columna descripcion a wp_cuotas_definidas (opcional)
ALTER TABLE wp_cuotas_definidas
ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) DEFAULT NULL AFTER valor;

-- Verificar resultado
SELECT 'Columna descripcion agregada' as resultado;

-- Mostrar estructura actualizada
DESCRIBE wp_cuotas_definidas;

EOF

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Esquema corregido exitosamente"
else
    echo ""
    echo "❌ Error al aplicar correcciones"
    exit 1
fi
