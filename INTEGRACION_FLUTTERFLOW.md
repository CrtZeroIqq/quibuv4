# 📱 Integración con FlutterFlow - Quibu App

## 📋 Resumen

Guía para integrar correctamente los endpoints de Quibu con FlutterFlow.

---

## 🔐 Endpoint: Login

**API Call Name:** `loginUsuario`

**Method:** POST

**URL:** `https://quibu.cl/login.php`

**Request Body (JSON):**
```json
{
  "email": "[email_variable]"
}
```

**Variables:**
- `email` (String) - Email del usuario

**Response:**
```json
{
  "success": true,
  "message": "Login exitoso",
  "data": {
    "usuario": {
      "id": 27,
      "nombre": "Patricio Hernandez Pavez",
      "email": "patricio.hp.iqq@gmail.com",
      "grupos_count": 3,
      "mercadopago_vinculado": false
    },
    "session": {
      "token": "abc123",
      "expires_in": 86400
    }
  }
}
```

**En FlutterFlow:**
1. Crear API Call "loginUsuario"
2. Response & Test → Parse as Data Type: ON
3. JSON Paths:
   - `$.success` → Boolean
   - `$.data.usuario.id` → Integer (guardar en App State)
   - `$.data.usuario.nombre` → String (guardar en App State)
   - `$.data.usuario.email` → String (guardar en App State)
   - `$.data.usuario.grupos_count` → Integer
   - `$.data.usuario.mercadopago_vinculado` → Boolean

---

## 🔗 Endpoint: Obtener URL OAuth de Mercado Pago

**API Call Name:** `mpObtenerURLOAuth`

**Method:** GET

**URL:** `https://quibu.cl/api/mp-obtener-url-oauth.php?usuario_id=[usuario_id]`

**Query Parameters:**
- `usuario_id` (Integer) - ID del usuario desde App State

**Response:**
```json
{
  "success": true,
  "ya_vinculado": false,
  "auth_url": "https://auth.mercadopago.cl/authorization?...",
  "open_in_browser": true,
  "action": "open_browser",
  "instrucciones": "Abre esta URL en un navegador web..."
}
```

### ⚠️ IMPORTANTE: Abrir en Navegador

Después de recibir la respuesta, **debes abrir** `auth_url` en un navegador.

**En FlutterFlow:**

1. Crear API Call "mpObtenerURLOAuth"
2. Response & Test → Parse as Data Type: ON
3. JSON Paths:
   - `$.success` → Boolean
   - `$.ya_vinculado` → Boolean
   - `$.auth_url` → String
   - `$.open_in_browser` → Boolean
   - `$.action` → String

4. **En el Action después del API Call:**
   ```
   Conditional:
     IF $.ya_vinculado == false
     THEN:
       Action: Launch URL
       URL: $.auth_url
       Launch Type: External Browser
   ```

### Alternativa con Custom Action:

Si FlutterFlow no puede abrir el navegador directamente, usa Custom Action:

```dart
import 'package:url_launcher/url_launcher.dart';

Future<void> abrirMercadoPago(String authUrl) async {
  final Uri url = Uri.parse(authUrl);

  if (await canLaunchUrl(url)) {
    await launchUrl(
      url,
      mode: LaunchMode.externalApplication
    );
  } else {
    throw 'No se pudo abrir $authUrl';
  }
}
```

**Dependencia necesaria** en `pubspec.yaml`:
```yaml
dependencies:
  url_launcher: ^6.2.1
```

---

## 📊 Endpoint: Listar Grupos del Usuario

**API Call Name:** `listarGrupos`

**Method:** GET

**URL:** `https://quibu.cl/api/listar_grupos.php?usuario_id=[usuario_id]`

**Query Parameters:**
- `usuario_id` (Integer) - ID del usuario desde App State

**Response:**
```json
{
  "success": true,
  "grupos": [
    {
      "id": 10,
      "nombre_grupo": "Cuotas Primero A",
      "tipo_grupo": "...",
      "cantidad_personas": 30,
      "valor_cuota": 5000,
      "fecha_inicio": "2025-06-01",
      "cantidadCuotas": 10
    }
  ]
}
```

**En FlutterFlow:**
1. Crear API Call "listarGrupos"
2. Response & Test → Parse as Data Type: ON
3. JSON Path para lista: `$.grupos`
4. Usar en un ListView o Column

---

## 🔄 Flujo Completo: Vincular Mercado Pago

### Paso 1: Usuario presiona "Vincular Mercado Pago"

**Action Flow:**
```
1. API Call: mpObtenerURLOAuth
   - Parámetro: usuario_id (del App State)

2. Conditional:
   IF API Response $.ya_vinculado == true:
     - Show Snackbar: "Ya tienes Mercado Pago vinculado"
   ELSE:
     - Launch URL: $.auth_url
     - Mode: External Browser

3. Show Snackbar:
   "Serás redirigido a Mercado Pago para autorizar..."
```

### Paso 2: Usuario autoriza en Mercado Pago

El usuario será redirigido a:
```
https://auth.mercadopago.cl/authorization?client_id=...&state=...
```

Allí ingresa sus credenciales de Mercado Pago y autoriza.

### Paso 3: Mercado Pago redirige de vuelta

Mercado Pago redirige a:
```
https://quibu.cl/api/mp-callback.php?code=ABC123&state=xyz
```

El backend de Quibu procesa el callback automáticamente y guarda los tokens.

### Paso 4: Usuario vuelve a la app

Cuando el usuario vuelva a la app:

**Action Flow:**
```
1. API Call: loginUsuario
   - Refrescar datos del usuario

2. Update App State:
   - mercadopago_vinculado = $.data.usuario.mercadopago_vinculado

3. Conditional:
   IF mercadopago_vinculado == true:
     - Show Snackbar: "¡Mercado Pago vinculado exitosamente!"
     - Update UI: Mostrar badge "✅ MP Vinculado"
```

---

## 🎨 UI Recomendada

### Botón "Vincular Mercado Pago"

```
Container:
  - Background: #009EE3 (azul de Mercado Pago)
  - Padding: 16px
  - Border Radius: 12px

  Row:
    - Image: Logo de Mercado Pago
    - Text: "Vincular Mercado Pago"
    - Icon: arrow_forward

  OnTap:
    - Action Flow (ver arriba)
```

### Estado Vinculado

```
Container:
  - Background: #00A650 (verde)
  - Padding: 12px
  - Border Radius: 8px

  Row:
    - Icon: check_circle (verde)
    - Text: "Mercado Pago Vinculado"
    - Text (secundario): "Vinculado el [fecha]"
```

---

## 🐛 Troubleshooting

### Error: "No se pudo abrir la URL"

**Causa:** La app no tiene permisos para abrir URLs externas

**Solución:**

**Android** - `android/app/src/main/AndroidManifest.xml`:
```xml
<manifest ...>
  <queries>
    <intent>
      <action android:name="android.intent.action.VIEW" />
      <data android:scheme="https" />
    </intent>
  </queries>
</manifest>
```

**iOS** - `ios/Runner/Info.plist`:
```xml
<key>LSApplicationQueriesSchemes</key>
<array>
  <string>https</string>
  <string>http</string>
</array>
```

### Error: "Usuario no vuelve a la app"

**Causa:** El redirect_uri no está configurado para deep link

**Solución:**

**Opción 1:** Mostrar mensaje al usuario

```
"Después de autorizar en Mercado Pago, vuelve a la app y
refresca la pantalla para ver tu cuenta vinculada."
```

**Opción 2:** Implementar Deep Link (avanzado)

Configurar deep link para que `https://quibu.cl/api/mp-callback.php`
pueda redirigir de vuelta a la app con `myapp://mp-success`.

### Error: "auth_url es null"

**Causa:** El API Call no parseó correctamente el JSON

**Solución:**
1. Verificar que "Parse as Data Type" esté ON
2. Verificar JSON Path: `$.auth_url`
3. Test API Call y revisar Response

### Usuario ya vinculado pero la app no lo muestra

**Causa:** App State no se actualizó

**Solución:**
1. En onPageLoad, hacer API Call a login o verificar sesión
2. Actualizar App State con `mercadopago_vinculado`
3. Usar Conditional para mostrar UI correcta

---

## 📝 Checklist de Integración

- [ ] API Call "loginUsuario" creado y probado
- [ ] API Call "mpObtenerURLOAuth" creado y probado
- [ ] JSON Paths configurados correctamente
- [ ] App State variables creadas (usuario_id, nombre, email, mercadopago_vinculado)
- [ ] Action Flow para "Vincular MP" implementado
- [ ] Launch URL configurado para abrir navegador externo
- [ ] Permisos de URL agregados (Android/iOS)
- [ ] UI para estado vinculado/no vinculado
- [ ] Snackbar de confirmación después de vincular
- [ ] Testing con usuario real
- [ ] Verificación de estado después de volver del navegador

---

## 🎯 Flujo Visual

```
[Dashboard App]
     |
     | Usuario toca "Vincular MP"
     ↓
[API Call: mpObtenerURLOAuth]
     |
     | Respuesta con auth_url
     ↓
[Launch URL: auth_url]
     |
     | Abre navegador externo
     ↓
[Mercado Pago - Login y Autorización]
     |
     | Usuario autoriza
     ↓
[Redirect: mp-callback.php]
     |
     | Backend guarda tokens
     ↓
[Usuario cierra navegador y vuelve a app]
     |
     | Usuario refresca o abre la app
     ↓
[API Call: loginUsuario para actualizar datos]
     |
     | Estado actualizado: mercadopago_vinculado = true
     ↓
[UI muestra "✅ Mercado Pago Vinculado"]
```

---

## 📞 Soporte

Si tienes problemas con la integración:

1. Verifica los logs de FlutterFlow
2. Prueba los endpoints con Postman o curl
3. Revisa que las variables de App State estén configuradas
4. Verifica los JSON Paths en cada API Call

---

## 🚀 Próximos Pasos

Una vez que la vinculación funcione:

1. Implementar creación de grupos
2. Implementar lista de pagadores
3. Implementar envío de links de pago
4. Implementar informes y estadísticas
5. Implementar notificaciones de pagos recibidos

¡Todo está listo en el backend! Solo falta conectar con FlutterFlow.
