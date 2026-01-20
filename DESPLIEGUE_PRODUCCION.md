# 🚀 Despliegue a Producción - Quibu V4

## ✅ Estado Actual

El código está listo y las credenciales de Mercado Pago están configuradas correctamente:

- ✅ MP_CLIENT_ID: 8052400951224123 (16 caracteres)
- ✅ MP_CLIENT_SECRET: VhgrJgobTlcl9vxJ6SC8lpiN1aVHorPk (32 caracteres)
- ✅ MP_MODE: test (para pruebas)
- ✅ MP_REDIRECT_URI: https://quibu.cl/api/mp-callback.php
- ✅ Todos los archivos OAuth creados y probados localmente

## 📋 Pasos para Despliegue

### 1. Conectarse al Servidor de Producción

```bash
ssh usuario@quibu.cl
# O usar el método que normalmente usas para acceder al servidor
```

### 2. Hacer Backup del Código Actual

```bash
cd /var/www/html
sudo tar -czf /home/backup-quibu-v3-$(date +%Y%m%d-%H%M%S).tar.gz .
```

### 3. Subir el Código de Quibu V4

**Opción A: Usando Git (Recomendado)**

En el servidor:
```bash
cd /var/www/html
sudo git pull origin claude/review-project-WKPJL
```

**Opción B: Usando SCP/FTP**

Desde tu máquina local:
```bash
cd /home/user/quibuv4
scp -r api/ usuario@quibu.cl:/var/www/html/
scp -r .env usuario@quibu.cl:/var/www/html/
scp login.php usuario@quibu.cl:/var/www/html/
```

### 4. Configurar el Archivo .env en Producción

```bash
sudo nano /var/www/html/.env
```

Asegúrate de que tenga **EXACTAMENTE** este contenido:

```env
# ======================
# BASE DE DATOS
# ======================
DB_HOST=localhost
DB_NAME=wp_quibu
DB_USER=root
DB_PASS=vP7!qN$2mX#fJ9zLrE@k1WbC
DB_CHARSET=utf8mb4

# ======================
# APLICACIÓN
# ======================
APP_ENV=production
APP_DEBUG=false
APP_URL=https://quibu.cl

# ======================
# MERCADO PAGO - OAUTH
# ======================
MP_CLIENT_ID=8052400951224123
MP_CLIENT_SECRET=VhgrJgobTlcl9vxJ6SC8lpiN1aVHorPk

# ======================
# MERCADO PAGO - MODO
# ======================
MP_MODE=test

# ======================
# MERCADO PAGO - CREDENCIALES TEST
# ======================
MP_PUBLIC_KEY=TEST-5121232a-5956-4cbf-a871-f6a788b005a9
MP_ACCESS_TOKEN=TEST-8052400951224123-010519-15b941f8acf16573b9016ba4acbd841a-3052545777

# ======================
# MERCADO PAGO - QUIBU (MARKETPLACE/COLLECTOR)
# ======================
QUIBU_MP_ACCESS_TOKEN=APP_USR-8052400951224123-010519-f6108fb2837d6cc53b4f5fcb35fdf445-3052545777
QUIBU_MP_PUBLIC_KEY=APP_USR-d6456ca9-af2b-49d1-95c2-9e602b944a4a

# ======================
# MERCADO PAGO - URLs DE CALLBACK
# ======================
MP_REDIRECT_URI=https://quibu.cl/api/mp-callback.php
MP_WEBHOOK_URL=https://quibu.cl/api/mp-webhook.php

# ======================
# MERCADO PAGO - URLs DE RETORNO
# ======================
MP_SUCCESS_URL=https://quibu.cl/pago-exitoso.php
MP_FAILURE_URL=https://quibu.cl/pago-fallido.php
MP_PENDING_URL=https://quibu.cl/pago-pendiente.php

# ======================
# TRANSBANK
# ======================
TRANSBANK_MODE=integration
TRANSBANK_API_KEY=579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C
TRANSBANK_COMMERCE_CODE=597055555532
TRANSBANK_RETURN_URL=https://quibu.cl/api/respuesta-pago.php
```

### 5. Configurar Permisos

```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod 640 /var/www/html/.env
sudo chmod 644 /var/www/html/api/*.php
sudo chmod 644 /var/www/html/*.php
```

### 6. Verificar Configuración

Desde el navegador, abre:

```
https://quibu.cl/api/validar-config-mp.php
```

Deberías ver:

```json
{
  "timestamp": "2026-01-20 ...",
  "validacion": {
    "MP_CLIENT_ID": "✅ Configurado (16 caracteres)",
    "MP_CLIENT_SECRET": "✅ Configurado (32 caracteres)",
    "MP_REDIRECT_URI": "✅ https://quibu.cl/api/mp-callback.php",
    "Base de Datos": "✅ Conexión exitosa"
  },
  "errores": [],
  "exito": true
}
```

Si ves errores, corrige lo que indique el JSON.

### 7. Verificar el Panel de Mercado Pago

Ve a: https://www.mercadopago.cl/developers/panel/app

En tu aplicación, verifica que en **"Redirect URIs"** esté agregada:

```
https://quibu.cl/api/mp-callback.php
```

Si no está, agrégala y guarda.

## 🧪 Pruebas

### Prueba 1: Login desde la App

Desde la app Quibu, haz login:

```
Email: patricio.hp.iqq@gmail.com
```

Deberías recibir:

```json
{
  "success": true,
  "data": {
    "usuario": {
      "id": 27,
      "nombre": "Patricio Hernandez Pavez",
      "mercadopago_vinculado": false
    }
  }
}
```

### Prueba 2: Obtener URL OAuth

Desde la app, toca **"Vincular Mercado Pago"**.

La app llamará a:
```
https://quibu.cl/api/mp-obtener-url-oauth.php?usuario_id=27
```

Respuesta esperada:
```json
{
  "success": true,
  "ya_vinculado": false,
  "auth_url": "https://auth.mercadopago.cl/authorization?client_id=8052400951224123&...",
  "open_in_browser": true
}
```

### Prueba 3: Autorizar en Mercado Pago

La app abrirá el navegador con la `auth_url`. Allí:

1. Inicia sesión en Mercado Pago (con tu cuenta de prueba)
2. Autoriza la aplicación Quibu
3. Serás redirigido a: `https://quibu.cl/api/mp-callback.php?code=...&state=...`

### Prueba 4: Verificar Vinculación Exitosa

Después de autorizar, deberías ver:

```
✅ ¡Cuenta Vinculada!

Hola Patricio Hernandez Pavez, tu cuenta de Mercado Pago se vinculó exitosamente.

Usuario MP: tu-email@example.com
ID de Usuario: 123456789
Estado: ✓ Activo
```

### Prueba 5: Verificar en Base de Datos

```bash
mysql -u root -p wp_quibu
```

```sql
SELECT
    id,
    nombre,
    email,
    mp_user_id,
    mp_linked_at,
    LENGTH(mp_access_token) as token_length
FROM wp_usuarios_app
WHERE id = 27;
```

Deberías ver:
```
id | nombre                    | email                      | mp_user_id | mp_linked_at        | token_length
27 | Patricio Hernandez Pavez  | patricio.hp.iqq@gmail.com  | 123456789  | 2026-01-20 10:30:00 | 120
```

### Prueba 6: Login de Nuevo para Verificar Estado

Desde la app, haz login de nuevo:

```json
{
  "success": true,
  "data": {
    "usuario": {
      "mercadopago_vinculado": true  // ✅ Ahora debería ser true
    }
  }
}
```

## 🐛 Si algo falla

### Error: "No se recibió access token de Mercado Pago"

**Revisa los logs de Apache:**

```bash
sudo tail -f /var/log/apache2/error.log
```

Busca líneas que digan:
```
Error intercambiando código OAuth. Response: {"error":"...","message":"..."}
```

**Causas comunes:**

1. **invalid_client**: Client ID o Secret incorrectos
   - Verifica que el .env tenga las credenciales correctas
   - Verifica que estés usando credenciales de TEST si MP_MODE=test

2. **redirect_uri_mismatch**: El redirect URI no coincide
   - Verifica que en el panel de MP esté exactamente: `https://quibu.cl/api/mp-callback.php`
   - Verifica que el .env tenga la misma URL

3. **code_already_used**: El código ya fue usado
   - Genera una nueva URL OAuth desde la app
   - No intentes reutilizar el mismo link

4. **code_expired**: El código expiró (10 minutos)
   - Genera una nueva URL OAuth
   - Completa el flujo más rápido

### Error: "State token inválido"

**Causa:** El state no tiene el formato correcto

**Solución:**
- Verifica que `mp-obtener-url-oauth.php` esté generando el state correctamente
- El formato debe ser: `random_hex_32_caracteres_usuario_id`
- Ejemplo: `a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6_27`

### Error de Conexión a Base de Datos

**Revisa el .env:**
```bash
cat /var/www/html/.env | grep DB_
```

Debe mostrar:
```
DB_HOST=localhost
DB_NAME=wp_quibu
DB_USER=root
DB_PASS=vP7!qN$2mX#fJ9zLrE@k1WbC
```

**Prueba la conexión:**
```bash
mysql -h localhost -u root -p'vP7!qN$2mX#fJ9zLrE@k1WbC' wp_quibu -e "SELECT COUNT(*) FROM wp_usuarios_app;"
```

## 📊 Verificar Logs

Para ver qué está pasando en cada solicitud:

```bash
# Logs de Apache
sudo tail -f /var/log/apache2/error.log

# Logs de PHP (si están configurados)
sudo tail -f /var/log/php_errors.log

# Logs de MySQL
sudo tail -f /var/log/mysql/error.log
```

## ✅ Checklist de Despliegue

- [ ] Código subido al servidor
- [ ] Archivo .env configurado con credenciales correctas
- [ ] Permisos de archivos configurados
- [ ] Script validar-config-mp.php ejecutado sin errores
- [ ] Redirect URI configurado en panel de Mercado Pago
- [ ] Prueba de login exitosa
- [ ] Prueba de obtener URL OAuth exitosa
- [ ] Prueba de autorización en MP exitosa
- [ ] Token guardado en BD correctamente
- [ ] Estado mercadopago_vinculado = true en login

## 🎯 Siguiente Paso

Una vez que el OAuth funcione en producción, el sistema estará listo para:

1. ✅ Crear grupos de cobranza
2. ✅ Generar links de pago
3. ✅ Recibir pagos con split automático (Quibu recibe fee)
4. ✅ Notificaciones de pagos vía webhook

---

## 📞 Contacto

Si encuentras problemas durante el despliegue, envía:

1. La salida de `https://quibu.cl/api/validar-config-mp.php`
2. Los logs de Apache (últimas 50 líneas)
3. Captura de pantalla del error que ves
