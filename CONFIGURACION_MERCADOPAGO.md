# 🔐 Configuración de Mercado Pago - Credenciales OAuth

## ⚠️ Problema Actual

El flujo OAuth de Mercado Pago está fallando con el error:
```
No se recibió access token de Mercado Pago
```

**Causa:** Las credenciales `MP_CLIENT_ID` y `MP_CLIENT_SECRET` están vacías en el archivo `.env`.

Sin estas credenciales, Mercado Pago no puede autenticar la aplicación Quibu y rechaza las solicitudes de OAuth.

---

## 📋 Solución: Obtener Credenciales de Mercado Pago

### Paso 1: Acceder al Panel de Desarrolladores

1. Ve a: https://www.mercadopago.cl/developers/panel/app
2. Inicia sesión con tu cuenta de Mercado Pago (la cuenta principal de Quibu)

### Paso 2: Crear o Seleccionar una Aplicación

**Opción A: Si NO tienes una aplicación creada:**

1. Haz clic en **"Crear aplicación"**
2. Completa el formulario:
   - **Nombre:** Quibu App
   - **Descripción:** Sistema de cobro de cuotas para tesoreros
   - **Modelo de integración:** Marketplace
   - **URL del sitio web:** https://www.quibu.cl
3. Guarda la aplicación

**Opción B: Si YA tienes una aplicación:**

1. Selecciona la aplicación existente de la lista
2. Ve a la sección de credenciales

### Paso 3: Copiar las Credenciales

En el panel de tu aplicación, encontrarás:

```
🔑 Credenciales de Producción:
   - Client ID: 1234567890123456
   - Client Secret: abcdefghijklmnopqrstuvwxyz123456

🔑 Credenciales de Prueba (Sandbox):
   - Client ID: TEST-1234567890123456
   - Client Secret: TEST-abcdefghijklmnopqrstuvwxyz123456
```

**IMPORTANTE:**
- Para desarrollo/testing: usa las credenciales de **Prueba** (TEST-*)
- Para producción: usa las credenciales de **Producción**

### Paso 4: Configurar el Redirect URI

En el panel de Mercado Pago, en la sección **"Redirect URIs"**:

1. Agrega esta URL exacta:
   ```
   https://www.quibu.cl/api/mp-callback.php
   ```

2. Si estás testeando en otro dominio (ej: localhost, IP), también agrégalas:
   ```
   http://localhost/api/mp-callback.php
   http://tu-ip/api/mp-callback.php
   ```

3. **CRÍTICO:** El Redirect URI debe coincidir EXACTAMENTE con el configurado en `.env`

### Paso 5: Configurar el archivo .env

Edita el archivo `/home/user/quibuv4/.env`:

**Antes:**
```env
MP_CLIENT_ID=
MP_CLIENT_SECRET=
MP_REDIRECT_URI=https://www.quibu.cl/api/mp-callback.php
MP_MODE=sandbox
```

**Después (con credenciales de prueba):**
```env
MP_CLIENT_ID=TEST-1234567890123456
MP_CLIENT_SECRET=TEST-abcdefghijklmnopqrstuvwxyz123456
MP_REDIRECT_URI=https://www.quibu.cl/api/mp-callback.php
MP_MODE=sandbox
```

**O con credenciales de producción:**
```env
MP_CLIENT_ID=1234567890123456
MP_CLIENT_SECRET=abcdefghijklmnopqrstuvwxyz123456
MP_REDIRECT_URI=https://www.quibu.cl/api/mp-callback.php
MP_MODE=production
```

### Paso 6: Validar la Configuración

Ejecuta el script de validación desde tu navegador:

```
https://www.quibu.cl/api/validar-config-mp.php
```

Deberías ver algo como:

```json
{
  "timestamp": "2026-01-20 12:00:00",
  "validacion": {
    "MP_CLIENT_ID": "✅ Configurado (24 caracteres)",
    "MP_CLIENT_SECRET": "✅ Configurado (32 caracteres)",
    "MP_REDIRECT_URI": "✅ https://www.quibu.cl/api/mp-callback.php",
    "Base de Datos": "✅ Conexión exitosa"
  },
  "errores": [],
  "advertencias": [
    "Mercado Pago está en modo SANDBOX (pruebas)"
  ],
  "exito": true
}
```

Si ves errores, sigue las instrucciones que muestra el JSON.

---

## 🧪 Probar el Flujo OAuth

Una vez configurado, prueba el flujo completo:

### 1. Desde la App Móvil:

1. Abre la app Quibu
2. Toca el botón **"Vincular Mercado Pago"**
3. Serás redirigido al navegador
4. Inicia sesión en Mercado Pago
5. Autoriza a Quibu
6. Deberías ver: **"¡Cuenta Vinculada!"**

### 2. Desde Postman/curl:

**A. Obtener URL de autorización:**

```bash
curl "https://www.quibu.cl/api/mp-obtener-url-oauth.php?usuario_id=27"
```

Respuesta:
```json
{
  "success": true,
  "ya_vinculado": false,
  "auth_url": "https://auth.mercadopago.cl/authorization?...",
  "open_in_browser": true
}
```

**B. Abrir auth_url en el navegador:**

Copia el `auth_url` y ábrelo en tu navegador. Autoriza la aplicación.

**C. Verificar vinculación:**

```bash
curl "https://www.quibu.cl/login.php?email=test@example.com"
```

Respuesta:
```json
{
  "success": true,
  "data": {
    "usuario": {
      "mercadopago_vinculado": true
    }
  }
}
```

---

## 🐛 Solución de Problemas

### Error: "invalid_client"

**Causa:** Client ID o Client Secret incorrectos

**Solución:**
1. Verifica que copiaste las credenciales correctamente (sin espacios)
2. Asegúrate de usar credenciales de Prueba si MP_MODE=sandbox
3. Asegúrate de usar credenciales de Producción si MP_MODE=production

### Error: "redirect_uri_mismatch"

**Causa:** El Redirect URI no coincide con el configurado en Mercado Pago

**Solución:**
1. Ve al panel de Mercado Pago
2. En "Redirect URIs", verifica que esté agregada EXACTAMENTE:
   ```
   https://www.quibu.cl/api/mp-callback.php
   ```
3. Verifica que `.env` tenga el mismo valor en `MP_REDIRECT_URI`
4. NO debe tener espacios, barras extras, ni mayúsculas/minúsculas diferentes

### Error: "code_already_used"

**Causa:** El código OAuth ya fue intercambiado por un token

**Solución:**
- Los códigos OAuth son de un solo uso
- Genera una nueva URL de autorización con `mp-obtener-url-oauth.php`
- Autoriza nuevamente

### Error: "code_expired"

**Causa:** El código OAuth expiró (tienen validez de ~10 minutos)

**Solución:**
- Genera una nueva URL de autorización
- Completa el flujo más rápido

### La página se queda en blanco después de autorizar

**Causa:** Error 500 en mp-callback.php

**Solución:**
1. Revisa los logs de Apache:
   ```bash
   sudo tail -f /var/log/apache2/error.log
   ```
2. Revisa los logs de PHP:
   ```bash
   sudo tail -f /var/log/php_errors.log
   ```
3. Verifica que la conexión a BD funcione
4. Ejecuta `validar-config-mp.php` para ver qué falta

---

## 📊 Verificar Tokens en Base de Datos

Después de vincular exitosamente, verifica que se guardó el token:

```sql
SELECT
    id,
    nombre,
    email,
    mp_user_id,
    mp_linked_at,
    LENGTH(mp_access_token) as token_length,
    LENGTH(mp_refresh_token) as refresh_token_length
FROM wp_usuarios_app
WHERE mp_user_id IS NOT NULL;
```

Deberías ver:
```
id | nombre        | email              | mp_user_id | mp_linked_at        | token_length | refresh_token_length
27 | Juan Pérez    | juan@example.com   | 123456789  | 2026-01-20 10:30:00 | 120          | 90
```

---

## 📝 Checklist

- [ ] Cuenta de Mercado Pago creada
- [ ] Aplicación creada en panel de desarrolladores
- [ ] Client ID y Client Secret copiados
- [ ] Redirect URI configurado en panel de MP
- [ ] Archivo `.env` actualizado con credenciales
- [ ] Script `validar-config-mp.php` ejecutado sin errores
- [ ] Flujo OAuth probado desde la app
- [ ] Token guardado correctamente en BD

---

## 🎯 Siguiente Paso

Una vez que el OAuth funcione, el flujo completo será:

```
Usuario en app → "Vincular MP" → Navegador → Autoriza → Callback → Token guardado → ✅ Listo para recibir pagos
```

---

## 📞 Soporte Mercado Pago

Si tienes problemas con las credenciales o el panel:

- **Documentación:** https://www.mercadopago.cl/developers/es/docs
- **Soporte:** https://www.mercadopago.cl/developers/es/support
- **Forum:** https://www.mercadopago.cl/developers/es/community
