#!/bin/bash
# Script para verificar que la cuenta correcta está vinculada
# Ejecutar: bash verify-oauth.sh

echo "=================================================="
echo "🔍 VERIFICAR VINCULACIÓN DE MERCADO PAGO"
echo "=================================================="
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Credenciales
DB_USER="root"
DB_PASS="vP7!qN$2mX#fJ9zLrE@k1WbC"
DB_NAME="wp_quibu"
USUARIO_ID=27
QUIBU_MP_USER_ID="3052545777"

echo "Consultando base de datos..."
echo ""

RESULT=$(mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -N -e "
SELECT
    nombre,
    email,
    COALESCE(mp_user_id, 'NULL'),
    COALESCE(mp_linked_at, 'nunca')
FROM wp_usuarios_app
WHERE id = $USUARIO_ID;
" 2>/dev/null)

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Error: No se pudo conectar a la base de datos${NC}"
    exit 1
fi

# Parsear resultado
NOMBRE=$(echo "$RESULT" | cut -f1)
EMAIL=$(echo "$RESULT" | cut -f2)
MP_USER_ID=$(echo "$RESULT" | cut -f3)
LINKED_AT=$(echo "$RESULT" | cut -f4)

echo "👤 Usuario: $NOMBRE"
echo "📧 Email: $EMAIL"
echo "🔑 MP User ID: $MP_USER_ID"
echo "📅 Vinculado: $LINKED_AT"
echo ""
echo "=================================================="

# Verificar estado
if [ "$MP_USER_ID" == "NULL" ]; then
    echo -e "${YELLOW}⚠️  NO VINCULADO${NC}"
    echo ""
    echo "La cuenta no está vinculada."
    echo ""
    echo "📋 Próximo paso:"
    echo "   1. Desde la app, tocar 'Vincular Mercado Pago'"
    echo "   2. Iniciar sesión con TU cuenta personal de MP"
    echo "   3. Ejecutar este script de nuevo para verificar"
    echo ""
    exit 0
fi

if [ "$MP_USER_ID" == "$QUIBU_MP_USER_ID" ]; then
    echo -e "${RED}❌ CUENTA INCORRECTA VINCULADA${NC}"
    echo ""
    echo "Se vinculó la cuenta de QUIBU (empresa):"
    echo "   MP User ID: $MP_USER_ID (acelis@seidgc.cl)"
    echo ""
    echo "Esto está MAL. Debe vincularse la cuenta PERSONAL del tesorero."
    echo ""
    echo "📋 Para corregir:"
    echo "   bash fix-oauth.sh"
    echo ""
    exit 1
fi

# Cuenta correcta vinculada
echo -e "${GREEN}✅ CUENTA CORRECTA VINCULADA${NC}"
echo ""
echo "Se vinculó una cuenta diferente a la de Quibu."
echo "MP User ID: $MP_USER_ID"
echo ""
echo "Esto significa que:"
echo "   • Los pagos irán a la cuenta del TESORERO"
echo "   • Los fees irán a la cuenta de QUIBU"
echo ""
echo "🎉 OAuth configurado correctamente!"
echo ""
echo "=================================================="
echo ""
echo "📋 Siguiente paso - Probar con un pago:"
echo ""
echo "1. Ir a: https://quibu.cl/pagar/?grupo=10"
echo "2. Seleccionar cuotas"
echo "3. Pagar con tarjeta de prueba:"
echo "   Número: 5031 7557 3453 0604"
echo "   CVV: 123"
echo "   Vencimiento: 11/25"
echo ""
echo "4. Verificar que el dinero llegó al tesorero"
echo ""
echo "=================================================="
echo ""
