# 🔧 Correcciones de Compatibilidad - Quibu V4

## 📋 Resumen

Este documento explica cómo aplicar las correcciones necesarias para adaptar Quibu V4 a tu base de datos existente.

## 🔴 Problemas Identificados

### 1. Error en API de Estadísticas (HTTP 500)
**Causa:** El código intenta leer la columna `descripcion` en `wp_cuotas_definidas` que no existe en tu esquema.

**Síntoma:**
```
Error al conectar con el servidor: HTTP error! status: 500
Column not found: 1054 Unknown column 'cd.descripcion' in 'field list'
```

### 2. Solo aparece Webpay, no Mercado Pago
**Causa:** El tesorero del grupo 10 no tiene `mp_access_token` configurado (es NULL).

**Explicación:** Mercado Pago requiere que cada tesorero vincule su cuenta mediante OAuth. Hasta que no lo haga, solo estará disponible Webpay.

### 3. Error de conexión a base de datos
**Causa:** El archivo `.env` tiene la contraseña incorrecta de MySQL.

**Síntoma:**
```
Access denied for user 'root'@'localhost' (using password: YES)
```

## ✅ Solución Automática (Recomendada)

### Ejecuta el script maestro de correcciones:

```bash
cd /var/www/html/quibuv4
sudo bash aplicar-correcciones.sh
```

Este script:
1. ✓ Copia el archivo de estadísticas corregido
2. ✓ Agrega la columna `descripcion` a `wp_cuotas_definidas`
3. ✓ Configura el `.env` con las credenciales correctas
4. ✓ Verifica que todo funcione

## 📝 Solución Manual (Alternativa)

### Paso 1: Corregir archivo `.env`

```bash
sudo nano /var/www/html/.env
```

Busca y modifica:
```env
DB_NAME=wp_quibu
DB_USER=root
DB_PASS=TU_CONTRASEÑA_REAL_AQUI
```

Guarda y cierra (Ctrl+X, luego Y, luego Enter).

### Paso 2: Agregar columna faltante

Ejecuta en MySQL:
```sql
USE wp_quibu;

ALTER TABLE wp_cuotas_definidas
ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) DEFAULT NULL AFTER valor;
```

### Paso 3: Copiar archivo corregido

```bash
sudo cp /var/www/html/quibuv4/api/estadisticas_publicas_grupo.php /var/www/html/api/
sudo chown www-data:www-data /var/www/html/api/estadisticas_publicas_grupo.php
```

### Paso 4: Verificar

```bash
php -r "
require '/var/www/html/api/conexion.php';
try {
    \$pdo = getConnection();
    echo 'Conexión exitosa' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"
```

## 🧪 Verificar que funciona

### 1. Probar la API de estadísticas:
```
https://www.quibu.cl/api/estadisticas_publicas_grupo.php?idGrupo=10
```
Debería devolver JSON sin errores.

### 2. Probar la página del grupo:
```
https://www.quibu.cl/pagar/?grupo=10
```
Debería mostrar:
- ✓ Información del grupo
- ✓ Widget de progreso (sin error 500)
- ✓ Lista de cuotas
- ✓ Formulario de registro/pago
- ✓ Selector de método de pago (solo Webpay por ahora)

### 3. Probar búsqueda por RUT:
```
https://www.quibu.cl/pago-landing/
```
Ingresa un RUT existente en tu base de datos.

## 🔐 Sobre Mercado Pago

### ¿Por qué solo aparece Webpay?

Mercado Pago requiere que cada tesorero vincule su cuenta mediante OAuth. El sistema verifica:

```sql
SELECT mp_access_token FROM wp_usuarios_app WHERE id = 27;
-- Resultado: NULL
```

Como el tesorero del grupo 10 (Patricio Hernandez Pavez) tiene `mp_access_token = NULL`, el sistema **solo muestra Webpay** como opción de pago.

### Para habilitar Mercado Pago:

**Opción 1: Vincular cuenta del tesorero (Recomendado)**

El tesorero debe:
1. Ingresar al Dashboard: `https://www.quibu.cl/dashboard/`
2. Ir a "Configuración de Mercado Pago"
3. Click en "Vincular mi cuenta de Mercado Pago"
4. Autorizar la aplicación Quibu
5. El sistema guardará automáticamente los tokens

**Opción 2: Configurar tokens manualmente (Temporal)**

Si necesitas probar urgentemente, puedes insertar tokens manualmente:

```sql
UPDATE wp_usuarios_app
SET
    mp_access_token = 'APP_USR-8052400951224123-010519-f6108fb2837d6cc53b4f5fcb35fdf445-3052545777',
    mp_public_key = 'APP_USR-d6456ca9-af2b-49d1-95c2-9e602b944a4a',
    mp_user_id = 3052545777,
    mp_linked_at = NOW()
WHERE id = 27;
```

**⚠️ ADVERTENCIA:** Los tokens de arriba son de ejemplo. Usa tus propias credenciales reales de Mercado Pago.

## 📊 Mapeo de Columnas

Tu base de datos vs lo que esperaba Quibu V4:

| Tabla | Tu columna | Quibu V4 esperaba | Estado |
|-------|------------|-------------------|--------|
| wp_grupos_cobranza | `id` | `id` | ✅ OK |
| wp_grupos_cobranza | `id_usuario` | `id_usuario` | ✅ OK |
| wp_grupos_cobranza | - | `descripcion` | ⚠️ Falta (se usa tipo_grupo) |
| wp_cuotas_definidas | `id_grupo` | `id_grupo` | ✅ OK |
| wp_cuotas_definidas | - | `descripcion` | ⚠️ Agregada por script |
| wp_usuarios_app | `mp_access_token` | `mp_access_token` | ✅ OK |

## ✅ Checklist Post-Corrección

Después de aplicar las correcciones, verifica:

- [ ] Conexión a base de datos funciona
- [ ] API de estadísticas devuelve JSON válido
- [ ] Página de grupo carga sin errores
- [ ] Widget de progreso se muestra correctamente
- [ ] Lista de cuotas aparece
- [ ] Formulario de registro funciona
- [ ] Selector de método de pago muestra Webpay
- [ ] Se pueden procesar pagos con Webpay
- [ ] (Opcional) Mercado Pago aparece después de vincular cuenta

## 🆘 Troubleshooting

### Error: "Access denied for user"
```bash
# Verifica las credenciales en .env
cat /var/www/html/.env | grep DB_

# Prueba conectarte manualmente
mysql -u root -p wp_quibu
```

### Error: "Column not found: cd.descripcion"
```bash
# Ejecuta el script de corrección
cd /var/www/html/quibuv4
sudo bash fix-database-schema.sh
```

### No aparece Mercado Pago
```sql
-- Verifica si el tesorero tiene tokens
SELECT mp_access_token, mp_public_key
FROM wp_usuarios_app
WHERE id = 27;

-- Si es NULL, necesitas vincular la cuenta
```

## 📞 Soporte

Si después de aplicar estas correcciones sigues teniendo problemas:

1. **Revisa los logs:**
   ```bash
   sudo tail -f /var/log/apache2/quibu-error.log
   ```

2. **Verifica permisos:**
   ```bash
   ls -la /var/www/html/.env
   # Debe ser: -rw------- (600) www-data
   ```

3. **Prueba la conexión PHP:**
   ```bash
   php -r "require '/var/www/html/api/conexion.php'; var_dump(getConnection());"
   ```

---

## 🎯 Próximos Pasos

Una vez que todo funcione:

1. **Probar flujo completo de pago con Webpay**
2. **Vincular cuenta de Mercado Pago del tesorero**
3. **Probar flujo de split payments con MP**
4. **Configurar webhook de Mercado Pago**
5. **Poner en producción** (cambiar MP_MODE=production)

¡Éxito! 🚀
