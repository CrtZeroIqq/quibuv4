# 🔌 APIs de Quibu V4

Documentación completa de los endpoints API disponibles en el sistema.

---

## 📋 Índice de APIs

### Core / Infraestructura

1. **conexion.php** - Conexión a base de datos
2. **mp-config.php** - Configuración de Mercado Pago

### Mercado Pago (OAuth + Pagos)

3. **mp-callback.php** - Callback OAuth de Mercado Pago
4. **mp-desvincular.php** - Desvinculación de cuenta MP
5. **mp-webhook.php** - Webhook para notificaciones IPN de MP

### Procesamiento de Pagos

6. **procesar_pago.php** - Gateway unificado (Mercado Pago + Transbank)
7. **respuesta-pago.php** - Confirmación de pagos (ambos gateways)

### Estadísticas y Reportes

8. **estadisticas_publicas_grupo.php** - Estadísticas públicas de progreso del grupo

---

## 🔌 Detalle de Endpoints

### 1. `conexion.php`

**Tipo:** Librería / Helper
**Método:** N/A (require)

Proporciona conexión PDO a MySQL con soporte para variables de entorno.

**Funciones principales:**
```php
getConnection()     // Obtener conexión PDO singleton
query($sql, $params) // Ejecutar consulta preparada
beginTransaction()  // Iniciar transacción
commit()            // Confirmar transacción
rollback()          // Revertir transacción
```

---

### 2. `mp-config.php`

**Tipo:** Librería / Configuración
**Método:** N/A (require)

Define constantes de configuración para Mercado Pago.

**Constantes definidas:**
```php
MP_CLIENT_ID              // OAuth App ID
MP_CLIENT_SECRET          // OAuth App Secret
QUIBU_MP_ACCESS_TOKEN     // Token de cuenta de Quibu (collector)
QUIBU_MP_PUBLIC_KEY       // Public key de Quibu
MP_AUTH_URL               // URL de autenticación
MP_API_URL                // URL base de API
MP_REDIRECT_URI           // Callback URL OAuth
MP_SUCCESS_URL            // URL éxito de pago
MP_FAILURE_URL            // URL fallo de pago
MP_PENDING_URL            // URL pago pendiente
MP_WEBHOOK_URL            // URL webhook IPN
MP_MODE                   // sandbox | production
```

**Funciones helper:**
```php
validar_config_mp()                      // Valida configuración
obtener_access_token_usuario($usuario_id) // Obtiene token OAuth del tesorero
tiene_mp_vinculado($usuario_id)          // Verifica vinculación
```

---

### 3. `mp-callback.php`

**Tipo:** Endpoint OAuth
**Método:** GET
**URL:** `/api/mp-callback.php`

Callback OAuth 2.0 de Mercado Pago. Intercambia el código de autorización por un access token.

**Parámetros GET:**
```
code  - Código de autorización de MP (requerido)
state - Token CSRF para validación (requerido)
```

**Flujo:**
1. Valida state token (prevención CSRF)
2. Intercambia code por access_token
3. Obtiene información del usuario MP
4. Guarda tokens en `wp_usuarios_app`
5. Redirecciona al dashboard

**Respuesta:**
```
Redirect a: /dashboard/vincular-mercadopago.php?success=1
Error:      /dashboard/vincular-mercadopago.php?error=mensaje
```

---

### 4. `mp-desvincular.php`

**Tipo:** Acción
**Método:** GET/POST
**URL:** `/api/mp-desvincular.php`

Elimina la vinculación de Mercado Pago de un tesorero.

**Sesión requerida:**
```php
$_SESSION['usuario_id'] // ID del usuario logueado
```

**Flujo:**
1. Verifica sesión activa
2. Elimina tokens OAuth de la BD
3. (Opcional) Revoca token en MP
4. Redirecciona con confirmación

**Respuesta:**
```
Success: Redirect a /dashboard/vincular-mercadopago.php?success=desvinculado
Error:   Redirect a /dashboard/vincular-mercadopago.php?error=...
```

---

### 5. `mp-webhook.php`

**Tipo:** Webhook IPN
**Método:** POST
**URL:** `/api/mp-webhook.php`

Receptor de notificaciones IPN de Mercado Pago.

**Parámetros POST/GET:**
```
type       - Tipo de notificación ('payment')
data[id]   - ID del pago en Mercado Pago
data_id    - ID alternativo (retrocompatibilidad)
```

**Flujo:**
1. Recibe notificación IPN de MP
2. Valida tipo de notificación
3. Obtiene detalles del pago desde MP API
4. Actualiza estado del pago en BD
5. Responde 200 OK a MP

**Respuesta:**
```json
HTTP 200 OK
{
  "status": "received"
}
```

**Reintentos:** Mercado Pago reintenta hasta 3 veces si recibe error 5xx

---

### 6. `procesar_pago.php`

**Tipo:** Gateway de pagos
**Método:** POST
**URL:** `/api/procesar_pago.php`

Procesa pagos a través de Mercado Pago o Transbank según selección del usuario.

**Parámetros POST:**
```
rut            - RUT del pagador (requerido)
grupo_id       - ID del grupo (requerido)
cuotas[]       - Array de IDs de cuotas a pagar (requerido)
metodo_pago    - 'mercadopago' | 'transbank' (requerido)
```

**Flujo:**
1. Valida datos del formulario
2. Obtiene información del grupo y tesorero
3. Verifica que el pagador exista
4. Valida cuotas seleccionadas
5. Calcula totales (subtotal + fees)
6. Guarda datos en sesión
7. **Si Mercado Pago:**
   - Crea preferencia con SDK
   - Configura `marketplace_fee` (split payment)
   - Redirecciona a checkout de MP
8. **Si Transbank:**
   - Crea transacción con SDK
   - Redirecciona a WebPay

**Split Payment (Mercado Pago):**
```php
SDK::setAccessToken($tesorero_token); // Token del tesorero
$preference->items = $items;          // Cuotas + fee
$preference->marketplace_fee = $fee;  // Fee va a Quibu
```

**Sesión guardada:**
```php
$_SESSION['pago_pendiente'] = [
    'rut' => $rut,
    'grupo_id' => $grupo_id,
    'cuotas' => [...],
    'subtotal' => 10000,
    'fee_total' => 300,
    'total' => 10300,
    'metodo_pago' => 'mercadopago',
    'timestamp' => time()
];
```

**Respuesta:**
```
Redirect a: Checkout de MP o Transbank
```

---

### 7. `respuesta-pago.php`

**Tipo:** Callback de pago
**Método:** GET
**URL:** `/api/respuesta-pago.php`

Procesa la respuesta tanto de Mercado Pago como de Transbank después del pago.

**Parámetros GET (Mercado Pago):**
```
collection_id       - ID del pago (deprecated)
payment_id          - ID del pago (actual)
status              - Estado del pago
collection_status   - Estado alternativo
external_reference  - Referencia externa
preference_id       - ID de preferencia
```

**Parámetros GET (Transbank):**
```
token_ws - Token de transacción WebPay
```

**Flujo:**
1. Lee datos de `$_SESSION['pago_pendiente']`
2. **Si Mercado Pago:**
   - Obtiene detalles del pago desde MP API
   - Valida estado (approved, pending, rejected)
3. **Si Transbank:**
   - Confirma transacción con `transaction->commit()`
   - Valida aprobación
4. Si aprobado:
   - Registra pago en `wp_pagos_cuotas`
   - Limpia sesión
   - Redirecciona a página de éxito
5. Si rechazado/pendiente:
   - Redirecciona a página correspondiente

**Registro en BD:**
```sql
INSERT INTO wp_pagos_cuotas (
    id_cuota, rut, fecha_pago, monto_pagado,
    fee_pagado, metodo_pago, transaction_id, payment_data
) VALUES (...)
```

**Respuesta:**
```
Success:  Redirect a /pago-exitoso.php?payment_id=...
Pending:  Redirect a /pago-pendiente.php?payment_id=...
Failure:  Redirect a /pago-fallido.php?status=...
```

---

### 8. `estadisticas_publicas_grupo.php`

**Tipo:** API REST (JSON)
**Método:** GET
**URL:** `/api/estadisticas_publicas_grupo.php`

Devuelve estadísticas de progreso y recaudación de un grupo.

**Parámetros GET:**
```
idGrupo - ID del grupo (requerido)
```

**Headers:**
```
Content-Type: application/json
Access-Control-Allow-Origin: *
```

**Respuesta Success (HTTP 200):**
```json
{
  "success": true,
  "grupo": {
    "id": 1,
    "nombre": "Grupo Ejemplo",
    "descripcion": "...",
    "cantidadPersonas": 30,
    "tipoGrupo": "curso",
    "tesorero": {
      "nombre": "Juan Pérez",
      "email": "juan@example.com"
    }
  },
  "periodo": {
    "fechaPrimeraCuota": "2025-01-01",
    "fechaUltimaCuota": "2025-12-31",
    "totalCuotas": 12
  },
  "estadisticasGenerales": {
    "pagadoresRegistrados": 25,
    "porcentajeRegistro": 83.3,
    "totalRecaudado": 150000,
    "metaTotalCurso": 360000,
    "porcentajeRecaudacion": 41.7,
    "cuotasPagadas": 50,
    "cuotasTotales": 360
  },
  "cuotaActual": {
    "nroCuota": 3,
    "fecha": "2025-03-01",
    "valor": 10000,
    "descripcion": "Cuota Marzo"
  }
}
```

**Respuesta Error (HTTP 400/404/500):**
```json
{
  "success": false,
  "message": "Mensaje de error",
  "error": "Detalles técnicos (solo en debug mode)"
}
```

**Uso:**
Llamado desde JavaScript para mostrar dashboard público de progreso del grupo.

```javascript
fetch(`/api/estadisticas_publicas_grupo.php?idGrupo=${grupoId}`)
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      renderStats(data);
    }
  });
```

---

## 🔐 Seguridad

### Autenticación

- **OAuth 2.0**: Endpoints de MP usan OAuth con CSRF protection (state token)
- **Sesión PHP**: Dashboard y vinculación requieren `$_SESSION['usuario_id']`
- **Sin autenticación**: APIs públicas (estadísticas) no requieren auth

### Validación

- **Prepared Statements**: Todas las consultas SQL usan PDO preparado
- **Sanitización**: RUT, email, texto sanitizados antes de BD
- **Validación de estado**: Pagos se validan con APIs de gateway antes de registrar

### Prevención de Ataques

- **SQL Injection**: Prepared statements en todas las consultas
- **CSRF**: State tokens en OAuth
- **XSS**: htmlspecialchars() en todos los outputs
- **Rate Limiting**: Pendiente de implementar

---

## 📊 Tablas de Base de Datos

### wp_usuarios_app
```sql
id                INT PRIMARY KEY
nombre            VARCHAR(255)
email             VARCHAR(255)
mp_access_token   TEXT              -- OAuth token del tesorero
mp_user_id        VARCHAR(100)      -- ID de usuario en MP
mp_public_key     TEXT
mp_refresh_token  TEXT
mp_linked_at      DATETIME
```

### wp_grupos_cobranza
```sql
id                INT PRIMARY KEY
nombre_grupo      VARCHAR(255)
descripcion       TEXT
tipo_grupo        VARCHAR(50)
cantidad_personas INT
id_usuario        INT               -- FK a wp_usuarios_app
```

### wp_pagadores
```sql
id        INT PRIMARY KEY
id_grupo  INT                       -- FK a wp_grupos_cobranza
rut       VARCHAR(12)
nombre    VARCHAR(255)
email     VARCHAR(255)
telefono  VARCHAR(20)
```

### wp_cuotas_definidas
```sql
id          INT PRIMARY KEY
id_grupo    INT                     -- FK a wp_grupos_cobranza
nro_cuota   INT
fecha_cuota DATE
valor       DECIMAL(10,2)
descripcion TEXT
```

### wp_pagos_cuotas
```sql
id             INT PRIMARY KEY AUTO_INCREMENT
id_cuota       INT                     -- FK a wp_cuotas_definidas
rut            VARCHAR(12)             -- RUT del pagador
fecha_pago     DATETIME
monto_pagado   DECIMAL(10,2)
fee_pagado     DECIMAL(10,2)
metodo_pago    ENUM('mercadopago', 'transbank')
transaction_id VARCHAR(255)
payment_data   TEXT                    -- JSON con detalles del pago
```

---

## 🚀 Próximas APIs (Roadmap)

Las siguientes APIs podrían agregarse en futuras versiones:

### Autenticación
- `POST /api/login.php` - Login de tesoreros
- `POST /api/logout.php` - Cerrar sesión
- `POST /api/register.php` - Registro de tesoreros

### Dashboard Administrativo
- `GET /api/dashboard/grupos.php` - Listar grupos del tesorero
- `GET /api/dashboard/pagadores.php` - Listar pagadores de un grupo
- `GET /api/dashboard/reportes.php` - Reportes financieros
- `GET /api/dashboard/pagos-pendientes.php` - Cuotas pendientes

### Gestión de Grupos
- `POST /api/grupos/crear.php` - Crear nuevo grupo
- `PUT /api/grupos/actualizar.php` - Actualizar grupo
- `DELETE /api/grupos/eliminar.php` - Eliminar grupo

### Gestión de Cuotas
- `POST /api/cuotas/crear.php` - Crear cuota
- `PUT /api/cuotas/actualizar.php` - Actualizar cuota
- `DELETE /api/cuotas/eliminar.php` - Eliminar cuota

### Notificaciones
- `POST /api/notificaciones/enviar-recordatorio.php` - Enviar SMS/Email
- `GET /api/notificaciones/morosos.php` - Listar morosos del grupo

### Exportación
- `GET /api/exportar/excel.php` - Exportar pagos a Excel
- `GET /api/exportar/pdf.php` - Generar reporte PDF

---

## 📝 Notas de Desarrollo

### Variables de Entorno

Todas las APIs leen configuración desde `.env`:

```env
DB_HOST=localhost
DB_NAME=quibu_db
DB_USER=root
DB_PASS=secretpassword

MP_CLIENT_ID=...
MP_CLIENT_SECRET=...
QUIBU_MP_ACCESS_TOKEN=...
QUIBU_MP_PUBLIC_KEY=...

TRANSBANK_MODE=integration
TRANSBANK_API_KEY=...
TRANSBANK_COMMERCE_CODE=...
```

### Logging

Todos los errores se registran con `error_log()`:

```php
error_log("Error en procesar_pago.php: " . $e->getMessage());
```

Los logs se guardan en:
- `/var/log/apache2/error.log` (Apache)
- `/var/log/nginx/error.log` (Nginx)
- O según configuración PHP `error_log`

### Modo Debug

Algunas APIs respetan `APP_DEBUG=true` para mostrar detalles de errores:

```php
'error' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : null
```

**⚠️ Siempre desactivar en producción.**

---

## 🔗 Enlaces Útiles

- [Documentación Mercado Pago](https://www.mercadopago.cl/developers/)
- [Documentación Transbank](https://www.transbankdevelopers.cl/)
- [PHP PDO](https://www.php.net/manual/es/book.pdo.php)

---

**Última actualización:** 2026-01-14
**Versión del sistema:** Quibu V4.0
