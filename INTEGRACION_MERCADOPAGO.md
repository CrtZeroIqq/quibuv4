# 🚀 Integración de Mercado Pago en Quibu V4

## Descripción General

Esta integración permite a los tesoreros conectar sus cuentas de Mercado Pago para recibir pagos directamente, sin intermediarios. Los pagos llegan automáticamente a la cuenta vinculada del tesorero, y Quibu retiene únicamente su comisión configurada.

---

## 📋 Requisitos Previos

### 1. Cuenta de Mercado Pago
- Crear una cuenta en [Mercado Pago Chile](https://www.mercadopago.cl)
- Verificar la cuenta (KYC completo)

### 2. Aplicación en el Panel de Desarrolladores
1. Ir a [Panel de Desarrolladores](https://www.mercadopago.cl/developers/panel/app)
2. Crear una nueva aplicación
3. Configurar:
   - **Nombre**: Quibu - Sistema de Cobranza
   - **Descripción**: Sistema automatizado de cobranza para grupos
   - **Redirect URI**: `https://www.quibu.cl/api/mp-callback.php`
   - **Webhooks**: `https://www.quibu.cl/api/mp-webhook.php`

4. Obtener credenciales:
   - **Client ID** (Application ID)
   - **Client Secret**

### 3. Servidor
- PHP 7.4 o superior
- Extensiones: curl, json, pdo, session
- Composer instalado
- Base de datos MySQL 5.7+

---

## 🔧 Instalación

### Paso 1: Instalar Dependencias

```bash
cd /home/user/quibuv4
composer install
```

Esto instalará:
- `mercadopago/dx-php` v2.6+ (SDK oficial)
- `transbank/transbank-sdk` v2.0+
- Otras dependencias necesarias

### Paso 2: Configurar Variables de Entorno

Editar el archivo `.env`:

```bash
nano .env
```

Configurar las siguientes variables:

```env
# Mercado Pago
MP_CLIENT_ID=TU_CLIENT_ID_AQUI
MP_CLIENT_SECRET=TU_CLIENT_SECRET_AQUI
MP_REDIRECT_URI=https://www.quibu.cl/api/mp-callback.php
MP_WEBHOOK_URL=https://www.quibu.cl/api/mp-webhook.php
MP_MODE=sandbox  # Cambiar a 'production' en producción

# URLs de retorno
MP_SUCCESS_URL=https://www.quibu.cl/pago-exitoso.php
MP_FAILURE_URL=https://www.quibu.cl/pago-fallido.php
MP_PENDING_URL=https://www.quibu.cl/pago-pendiente.php

# Base de datos
DB_HOST=localhost
DB_NAME=quibu_db
DB_USER=tu_usuario
DB_PASS=tu_contraseña

# Comisión de Quibu (opcional, 0 por defecto)
QUIBU_COMMISSION_PERCENT=0
```

### Paso 3: Actualizar Base de Datos

Ejecutar el script de migración:

```bash
mysql -u usuario -p quibu_db < database/migration_mercadopago.sql
```

O si es una instalación nueva:

```bash
mysql -u usuario -p quibu_db < database/schema.sql
```

### Paso 4: Configurar Apache/Nginx

#### Apache (.htaccess ya incluido)

Verificar que `mod_rewrite` esté habilitado:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx

Agregar a la configuración del sitio:

```nginx
location /api/ {
    try_files $uri $uri/ /api/index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### Paso 5: Configurar Webhooks en Mercado Pago

1. Ir al [Panel de tu aplicación](https://www.mercadopago.cl/developers/panel/app)
2. En la sección **Webhooks**, agregar:
   - **URL**: `https://www.quibu.cl/api/mp-webhook.php`
   - **Eventos**: Seleccionar "Pagos" (payments)

---

## 🔐 Flujo de Autenticación OAuth 2.0

### Flujo Completo

```
1. Tesorero hace clic en "Conectar con Mercado Pago"
   ↓
2. Sistema genera URL de autorización con state token (CSRF)
   ↓
3. Redirección a auth.mercadopago.cl
   ↓
4. Usuario autoriza la aplicación en MP
   ↓
5. MP redirecciona a: /api/mp-callback.php?code=XXX&state=YYY
   ↓
6. Sistema valida state token
   ↓
7. Sistema intercambia code por access_token
   ↓
8. Access token se guarda en BD (tabla wp_usuarios_app)
   ↓
9. ✅ Cuenta vinculada exitosamente
```

### Archivos Involucrados

- **`/dashboard/vincular-mercadopago.php`**: Interfaz de vinculación
- **`/api/mp-callback.php`**: Callback OAuth (recibe código)
- **`/api/mp-config.php`**: Configuración y constantes
- **`/api/mp-desvincular.php`**: Desvinculación de cuenta

---

## 💳 Flujo de Pago

### Proceso de Pago Completo

```
1. Usuario selecciona cuotas a pagar
   ↓
2. Usuario elige "Mercado Pago" como método
   ↓
3. Sistema calcula total + fees de Quibu
   ↓
4. Sistema crea Preferencia de Pago con SDK
   - Usa access_token del tesorero
   - Incluye items (cuotas + fee)
   - Configura URLs de retorno
   ↓
5. Usuario es redireccionado a Mercado Pago
   ↓
6. Usuario completa el pago en MP
   ↓
7. MP redirecciona a /api/respuesta-pago.php
   ↓
8. Sistema confirma el pago con MP API
   ↓
9. Sistema registra pago en BD
   ↓
10. MP envía notificación a webhook (async)
   ↓
11. ✅ Pago confirmado
```

### Archivos Involucrados

- **`/pagar/index.php`**: Formulario de pago agrupado
- **`/pagar/directo.php`**: Formulario de pago directo
- **`/api/procesar_pago.php`**: Crea la preferencia de pago
- **`/api/respuesta-pago.php`**: Procesa respuesta y confirma pago
- **`/api/mp-webhook.php`**: Recibe notificaciones asíncronas
- **`/pago-exitoso.php`**: Página de éxito
- **`/pago-fallido.php`**: Página de error
- **`/pago-pendiente.php`**: Página de pago pendiente

---

## 🔔 Webhooks

### Eventos Soportados

- **`payment`**: Notificación de pago creado/actualizado

### Estructura de Notificación

Mercado Pago envía:

```json
{
  "action": "payment.created",
  "api_version": "v1",
  "data": {
    "id": "1234567890"
  },
  "date_created": "2025-01-14T10:00:00Z",
  "id": 1234567890,
  "live_mode": true,
  "type": "payment",
  "user_id": "123456789"
}
```

### Validación de Webhooks

El webhook valida:
1. Que el payment_id exista
2. Que el pago esté aprobado
3. Que no esté duplicado

### Reintentos

Mercado Pago reintenta hasta 3 veces si el webhook responde con error (HTTP 5xx).

---

## 🧪 Testing

### Modo Sandbox

En `.env`:

```env
MP_MODE=sandbox
```

### Tarjetas de Prueba

| Tarjeta | Número | CVV | Expira | Resultado |
|---------|--------|-----|--------|-----------|
| Mastercard | 5031 7557 3453 0604 | 123 | 11/25 | Aprobada |
| Visa | 4509 9535 6623 3704 | 123 | 11/25 | Aprobada |
| Visa | 4074 0953 7756 8191 | 123 | 11/25 | Rechazada |

### Verificar Integración

1. Vincular cuenta de prueba de MP
2. Crear un grupo de prueba
3. Agregar cuotas de prueba
4. Realizar un pago con tarjeta de prueba
5. Verificar:
   - Redirección correcta
   - Registro en base de datos
   - Recepción de webhook

---

## 🔒 Seguridad

### Medidas Implementadas

1. **CSRF Protection**: State token en OAuth
2. **Prepared Statements**: Todas las consultas SQL usan PDO preparado
3. **Validación de Input**: Sanitización de RUT, email, teléfono
4. **HTTPS Required**: Todas las URLs de callback requieren HTTPS
5. **Token Storage**: Access tokens encriptados en BD
6. **Session Management**: Datos sensibles en sesión PHP
7. **Webhook Validation**: Verificación de firma (opcional, recomendado)

### Mejoras Recomendadas para Producción

```php
// En mp-webhook.php, agregar validación de firma
$x_signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$x_request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

// Validar firma con tu secret
if (!validar_firma_mp($x_signature, $x_request_id, $data)) {
    http_response_code(401);
    die('Firma inválida');
}
```

---

## 📊 Base de Datos

### Tablas Modificadas

#### `wp_usuarios_app`
```sql
mp_access_token TEXT        -- Access token OAuth
mp_user_id VARCHAR(100)     -- ID de usuario en MP
mp_public_key TEXT          -- Public key (opcional)
mp_refresh_token TEXT       -- Refresh token
mp_linked_at DATETIME       -- Fecha de vinculación
```

#### `wp_pagos_cuotas`
```sql
metodo_pago ENUM('mercadopago', 'transbank')  -- Método usado
payment_data TEXT                              -- JSON con detalles del pago
```

---

## 🚨 Troubleshooting

### Error: "No se recibió access token"

**Causa**: Client ID o Client Secret incorrecto

**Solución**:
1. Verificar credenciales en `.env`
2. Verificar que la aplicación esté activa en MP
3. Revisar logs: `tail -f /var/log/apache2/error.log`

### Error: "Redirect URI no coincide"

**Causa**: La URI configurada en MP no coincide con la del sistema

**Solución**:
1. En Panel de MP, configurar exactamente: `https://www.quibu.cl/api/mp-callback.php`
2. Sin trailing slash
3. Con HTTPS

### Webhook no se recibe

**Causa**: URL no accesible o firewall bloqueando

**Solución**:
1. Verificar que la URL sea pública: `curl https://www.quibu.cl/api/mp-webhook.php`
2. Verificar logs de webhook en Panel de MP
3. Verificar que Apache/Nginx esté escuchando

### Pago aprobado pero no se registra

**Causa**: Error en respuesta-pago.php

**Solución**:
1. Revisar logs PHP
2. Verificar que la sesión no haya expirado
3. Verificar conexión a BD

---

## 📱 Flujo del Usuario Final

### Vista del Pagador

1. Ingresa su RUT
2. Ve sus cuotas pendientes
3. Selecciona las que desea pagar
4. Ve el total calculado (valor + fee)
5. Elige "Mercado Pago" como método
6. Clic en "Proceder al Pago"
7. Es redirigido a Mercado Pago
8. Completa el pago con tarjeta
9. Es redirigido de vuelta a Quibu
10. Ve confirmación de pago exitoso

### Vista del Tesorero

1. Recibe el pago directamente en su cuenta MP
2. Quibu retiene su comisión automáticamente
3. Ve el estado actualizado en el dashboard
4. Puede generar reportes de pagos

---

## 🔄 Migración desde Transbank

Si ya estás usando Transbank, la migración es simple:

1. Los formularios ahora muestran AMBOS métodos de pago
2. Si el tesorero vincula MP, los usuarios verán ambas opciones
3. Si no vincula MP, solo verán Transbank
4. Los pagos existentes no se afectan
5. Compatibilidad 100% con código existente

---

## 📞 Soporte

### Documentación Oficial de Mercado Pago

- [Docs OAuth](https://www.mercadopago.cl/developers/es/docs/security/oauth/introduction)
- [Docs Checkout Pro](https://www.mercadopago.cl/developers/es/docs/checkout-pro/landing)
- [Docs Webhooks](https://www.mercadopago.cl/developers/es/docs/your-integrations/notifications/webhooks)
- [Docs API Reference](https://www.mercadopago.cl/developers/es/reference)

### Testing Credentials

- [Panel de prueba](https://www.mercadopago.cl/developers/panel/credentials)
- [Test users](https://www.mercadopago.cl/developers/es/docs/testing/test-users)

---

## ✅ Checklist de Producción

Antes de pasar a producción:

- [ ] Credenciales de producción en `.env`
- [ ] `MP_MODE=production`
- [ ] Base de datos respaldada
- [ ] HTTPS configurado y funcionando
- [ ] Certificado SSL válido
- [ ] Webhook URL accesible públicamente
- [ ] Redirect URI configurada en MP
- [ ] Logs configurados y monitoreados
- [ ] Testing completo con tarjeta de prueba
- [ ] Comisión de Quibu configurada (si aplica)
- [ ] Emails de notificación configurados
- [ ] Backup automático configurado

---

## 📈 Próximas Mejoras

- [ ] Split payments automático (Quibu + Tesorero)
- [ ] Reportes de comisiones
- [ ] Dashboard de transacciones
- [ ] Notificaciones por email
- [ ] Exportar a Excel/PDF
- [ ] Integración con boletas electrónicas

---

**Documentación actualizada:** 2026-01-14
**Versión:** 1.0
**Autor:** Claude (Anthropic AI)
