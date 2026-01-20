# 📱 API de Autenticación - Quibu App Móvil

## 📋 Resumen

Endpoints de autenticación para la aplicación móvil de Quibu (tesoreros).

**Base URL:** `https://quibu.cl/`

---

## 🔐 Endpoints de Autenticación

### 1. **Registro de Usuario**

Registra un nuevo tesorero en la plataforma.

**Endpoint:** `POST /api/register.php`

**Request Body:**
```json
{
  "nombre": "Juan Pérez",
  "email": "juan.perez@gmail.com",
  "rut": "12345678-9",
  "telefono": "+56912345678",
  "ciudad": "Santiago"
}
```

**Campos:**
- `nombre` (requerido): Nombre completo del tesorero
- `email` (requerido): Email único del tesorero
- `rut` (requerido): RUT chileno válido con dígito verificador
- `telefono` (opcional): Teléfono con código de país
- `ciudad` (opcional): Ciudad de residencia

**Response 201 (Success):**
```json
{
  "success": true,
  "message": "Usuario registrado exitosamente",
  "data": {
    "usuario": {
      "id": 123,
      "nombre": "Juan Pérez",
      "email": "juan.perez@gmail.com",
      "rut": "12345678-9",
      "telefono": "+56912345678",
      "ciudad": "Santiago",
      "created_at": "2026-01-20 10:30:00"
    }
  }
}
```

**Response 400 (Error - Campos faltantes):**
```json
{
  "success": false,
  "message": "Faltan campos requeridos: nombre, email",
  "data": []
}
```

**Response 409 (Error - Email/RUT ya existe):**
```json
{
  "success": false,
  "message": "Ya existe un usuario registrado con este email.",
  "data": []
}
```

---

### 2. **Login**

Autentica un tesorero y crea una sesión.

**Endpoint:** `POST /login.php`

**Request Body:**
```json
{
  "email": "juan.perez@gmail.com"
}
```

**Campos:**
- `email` (requerido): Email del tesorero registrado

**Response 200 (Success):**
```json
{
  "success": true,
  "message": "Login exitoso",
  "data": {
    "usuario": {
      "id": 123,
      "nombre": "Juan Pérez",
      "email": "juan.perez@gmail.com",
      "rut": "12345678-9",
      "telefono": "+56912345678",
      "ciudad": "Santiago",
      "created_at": "2026-01-20 10:30:00",
      "sms_automatico": false,
      "grupos_count": 3,
      "mercadopago_vinculado": true,
      "mp_user_id": 3052545777,
      "mp_linked_at": "2026-01-15 14:20:00"
    },
    "session": {
      "token": "abc123sessionid456",
      "expires_in": 86400
    }
  }
}
```

**Response 404 (Error - Usuario no encontrado):**
```json
{
  "success": false,
  "message": "Usuario no encontrado con ese email.",
  "data": []
}
```

**Response 400 (Error - Falta email):**
```json
{
  "success": false,
  "message": "El email es requerido.",
  "data": []
}
```

---

### 3. **Verificar Sesión**

Verifica si la sesión del usuario está activa.

**Endpoint:** `GET /api/verify_session.php`

**Headers:**
```
Cookie: PHPSESSID=abc123sessionid456
```

**Response 200 (Success - Sesión activa):**
```json
{
  "success": true,
  "message": "Sesión activa",
  "data": {
    "user_id": 123,
    "email": "juan.perez@gmail.com",
    "nombre": "Juan Pérez",
    "expires_in": 82800
  }
}
```

**Response 401 (Error - Sesión expirada):**
```json
{
  "success": false,
  "message": "Sesión expirada",
  "code": "session_expired"
}
```

**Response 401 (Error - No hay sesión):**
```json
{
  "success": false,
  "message": "No hay sesión activa",
  "code": "no_session"
}
```

---

### 4. **Logout**

Cierra la sesión del usuario.

**Endpoint:** `POST /logout.php` o `GET /logout.php`

**Headers:**
```
Cookie: PHPSESSID=abc123sessionid456
```

**Response 200 (Success):**
```json
{
  "success": true,
  "message": "Sesión cerrada exitosamente"
}
```

**Response 500 (Error):**
```json
{
  "success": false,
  "message": "Error al cerrar sesión"
}
```

---

## 🔑 Manejo de Sesiones

### Duración de Sesión
- **Tiempo de expiración:** 24 horas (86400 segundos)
- **Renovación:** No automática, debe hacer login nuevamente

### Cookies
- **Nombre:** `PHPSESSID`
- **Seguridad:** HttpOnly, Secure (en HTTPS)
- **Path:** `/`

### Headers Requeridos
Para endpoints que requieren autenticación, incluir:
```
Cookie: PHPSESSID=valor_del_session_id
```

---

## 🚀 Flujo Típico de Autenticación

### Primer Uso (Registro)
```
1. Usuario completa formulario de registro
2. App → POST /api/register.php
3. Server crea usuario en BD
4. App recibe datos del usuario creado
5. App → POST /login.php (con email)
6. Server crea sesión y devuelve token
7. App guarda session token localmente
```

### Uso Subsecuente (Login)
```
1. Usuario ingresa email
2. App → POST /login.php
3. Server valida usuario y crea sesión
4. App recibe session token
5. App guarda token para requests futuros
```

### Verificación de Sesión
```
1. App inicia o vuelve del background
2. App → GET /api/verify_session.php (con cookie)
3. Server valida sesión
4. Si válida: continúa operación normal
5. Si expirada: redirige a login
```

### Logout
```
1. Usuario presiona "Cerrar sesión"
2. App → POST /logout.php (con cookie)
3. Server destruye sesión
4. App elimina token local
5. App redirige a pantalla de login
```

---

## 🧪 Testing

### Con cURL

**Registro:**
```bash
curl -X POST https://quibu.cl/api/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Test User",
    "email": "test@example.com",
    "rut": "12345678-9",
    "telefono": "+56912345678",
    "ciudad": "Santiago"
  }'
```

**Login:**
```bash
curl -X POST https://quibu.cl/login.php \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{
    "email": "test@example.com"
  }'
```

**Verificar Sesión:**
```bash
curl -X GET https://quibu.cl/api/verify_session.php \
  -b cookies.txt
```

**Logout:**
```bash
curl -X POST https://quibu.cl/logout.php \
  -b cookies.txt
```

---

## 🔒 Seguridad

### Validaciones Implementadas

**Email:**
- Formato válido (filter_var con FILTER_VALIDATE_EMAIL)
- Único en la base de datos
- Convertido a minúsculas

**RUT:**
- Validación de dígito verificador
- Formato chileno estándar
- Único en la base de datos

**Sesiones:**
- Timeout de 24 horas
- Destrucción automática al expirar
- HttpOnly cookies en producción

### Recomendaciones para la App

1. **HTTPS Obligatorio:** Todas las requests deben usar HTTPS
2. **Almacenamiento Seguro:** Guardar session token en almacenamiento seguro del dispositivo
3. **Validación en Cliente:** Validar formato de email y RUT antes de enviar
4. **Manejo de Errores:** Mostrar mensajes claros al usuario
5. **Timeout Visual:** Advertir al usuario cuando la sesión esté por expirar
6. **Retry Logic:** Implementar reintentos con backoff exponencial

---

## 📊 Códigos de Estado HTTP

| Código | Significado | Uso |
|--------|-------------|-----|
| 200 | OK | Login exitoso, sesión válida |
| 201 | Created | Usuario creado exitosamente |
| 400 | Bad Request | Datos faltantes o inválidos |
| 401 | Unauthorized | Sesión expirada o no existe |
| 404 | Not Found | Usuario no encontrado |
| 405 | Method Not Allowed | Método HTTP incorrecto |
| 409 | Conflict | Email/RUT ya existe |
| 500 | Internal Server Error | Error del servidor |

---

## 🐛 Troubleshooting

### Error: "session_expired"
**Causa:** La sesión expiró (más de 24 horas)
**Solución:** Hacer login nuevamente

### Error: "no_session"
**Causa:** No hay cookie de sesión en el request
**Solución:** Verificar que se envíe la cookie PHPSESSID

### Error: "Usuario no encontrado"
**Causa:** El email no existe en la base de datos
**Solución:** Verificar email o registrar usuario nuevo

### Error: "Ya existe un usuario registrado"
**Causa:** Email o RUT duplicado
**Solución:** Usar datos diferentes o hacer login con cuenta existente

### Error: "RUT no es válido"
**Causa:** Dígito verificador incorrecto
**Solución:** Verificar que el RUT esté bien escrito

---

## 📝 Changelog

### v1.0.0 (2026-01-20)
- ✅ Endpoint de registro de usuarios
- ✅ Endpoint de login
- ✅ Endpoint de verificación de sesión
- ✅ Endpoint de logout
- ✅ Validación de RUT chileno
- ✅ Manejo de sesiones con timeout de 24h
- ✅ CORS habilitado para app móvil

---

## 📞 Soporte

Para reportar problemas con la API de autenticación:
1. Verificar los logs del servidor: `/var/log/apache2/quibu-error.log`
2. Revisar formato de los requests
3. Validar que el usuario exista en la BD

---

## 🎯 Próximos Pasos

Una vez autenticado, el usuario puede acceder a:
- `/api/listar_grupos.php` - Listar sus grupos de cobranza
- `/api/crear_grupo.php` - Crear nuevos grupos
- `/api/detalle_grupo.php` - Ver detalles de un grupo
- Y todas las demás APIs documentadas en `/api/README.md`
