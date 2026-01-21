#!/bin/bash
# Script para desvincular cuenta incorrecta de Mercado Pago
# Ejecutar: bash fix-oauth.sh

echo "=================================================="
echo "🔧 DESVINCULAR CUENTA INCORRECTA DE MERCADO PAGO"
echo "=================================================="
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Credenciales de BD
DB_USER="root"
DB_PASS="vP7!qN$2mX#fJ9zLrE@k1WbC"
DB_NAME="wp_quibu"
USUARIO_ID=27

echo "1️⃣  Verificando cuenta actual..."
echo ""

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT
    CONCAT('👤 Usuario: ', nombre) as info,
    CONCAT('📧 Email: ', email) as email,
    CONCAT('🔑 MP User ID: ', COALESCE(mp_user_id, 'NULL (no vinculado)')) as mp_id,
    CONCAT('📅 Vinculado: ', COALESCE(mp_linked_at, 'nunca')) as vinculado
FROM wp_usuarios_app
WHERE id = $USUARIO_ID;
" 2>/dev/null

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Error: No se pudo conectar a la base de datos${NC}"
    echo "Verifica que MySQL esté corriendo y las credenciales sean correctas"
    exit 1
fi

echo ""
echo "2️⃣  Desvinculando cuenta..."
echo ""

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
UPDATE wp_usuarios_app
SET
    mp_access_token = NULL,
    mp_user_id = NULL,
    mp_public_key = NULL,
    mp_refresh_token = NULL,
    mp_linked_at = NULL
WHERE id = $USUARIO_ID;
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Cuenta desvinculada exitosamente${NC}"
else
    echo -e "${RED}❌ Error al desvincular${NC}"
    exit 1
fi

echo ""
echo "3️⃣  Verificando resultado..."
echo ""

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SELECT
    nombre,
    email,
    COALESCE(mp_user_id, 'NULL - ✅ DESVINCULADO') as mp_user_id,
    COALESCE(mp_linked_at, 'NULL') as mp_linked_at
FROM wp_usuarios_app
WHERE id = $USUARIO_ID;
" 2>/dev/null

echo ""
echo "=================================================="
echo -e "${GREEN}✅ PASO 1 COMPLETADO${NC}"
echo "=================================================="
echo ""
echo "📋 PRÓXIMOS PASOS:"
echo ""
echo "1. Cerrar sesión de Mercado Pago en el navegador:"
echo "   → Ve a https://www.mercadopago.cl"
echo "   → Haz clic en tu perfil (arriba derecha)"
echo "   → Haz clic en 'Salir'"
echo ""
echo "2. Desde la app móvil Quibu:"
echo "   → Login con tu email"
echo "   → Tocar 'Vincular Mercado Pago'"
echo "   → Iniciar sesión con TU cuenta PERSONAL de MP"
echo "   → NO usar acelis@seidgc.cl (esa es de la empresa)"
echo "   → Autorizar"
echo ""
echo "3. Verificar que funcionó:"
echo "   bash verify-oauth.sh"
echo ""
echo "=================================================="
echo ""
