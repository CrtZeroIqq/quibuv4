# 🚨 RESOLVER OAUTH DE MERCADO PAGO - PASOS EXACTOS

## ❌ PROBLEMA ACTUAL

Se vinculó la cuenta **EQUIVOCADA** de Mercado Pago:
- Se vinculó: `acelis@seidgc.cl` (cuenta de QUIBU/empresa)
- Debe vincularse: cuenta PERSONAL del tesorero (Patricio)

## ✅ SOLUCIÓN EN 3 PASOS

### PASO 1: Desvincular Cuenta Incorrecta

Ejecutar desde SSH en el servidor:

```bash
mysql -u root -p'vP7!qN$2mX#fJ9zLrE@k1WbC' wp_quibu -e "
UPDATE wp_usuarios_app
SET mp_access_token = NULL,
    mp_user_id = NULL,
    mp_public_key = NULL,
    mp_refresh_token = NULL,
    mp_linked_at = NULL
WHERE id = 27;

SELECT 'DESVINCULADO OK' as resultado,
       nombre,
       email,
       mp_user_id
FROM wp_usuarios_app
WHERE id = 27;
"
```

**Resultado esperado:** `mp_user_id` debe ser `NULL`

---

### PASO 2: Cerrar Sesión de Mercado Pago

En cualquier navegador:

1. Ir a: https://www.mercadopago.cl
2. Hacer clic en tu foto/perfil (arriba derecha)
3. Hacer clic en "Salir" o "Cerrar sesión"
4. Verificar que estés deslogueado

---

### PASO 3: Vincular Cuenta CORRECTA desde App

Desde la app móvil Quibu:

1. **Login:**
   - Email: patricio.hp.iqq@gmail.com (o el que uses)

2. **Tocar botón:** "Vincular Mercado Pago"

3. **En el navegador que se abre:**

   ⚠️ **IMPORTANTE:**
   - **NO** iniciar sesión con `acelis@seidgc.cl`
   - **SÍ** iniciar sesión con TU cuenta PERSONAL de Mercado Pago

   Si no tienes cuenta personal de MP:
   - Crear en: https://www.mercadopago.cl/registration
   - Usar tu email personal
   - Completar verificación

4. **Autorizar** la aplicación Quibu

5. **Resultado:** Deberías ver "¡Cuenta Vinculada!" con TU email (no el de Quibu)

---

### PASO 4: Verificar que Funcionó

Ejecutar desde SSH:

```bash
mysql -u root -p'vP7!qN$2mX#fJ9zLrE@k1WbC' wp_quibu -e "
SELECT
    nombre,
    email,
    mp_user_id,
    mp_linked_at
FROM wp_usuarios_app
WHERE id = 27;
"
```

**Verificar que:**
- ✅ `mp_user_id` tiene un valor (NO es NULL)
- ✅ `mp_user_id` es DIFERENTE a `3052545777` (ese es el de Quibu)
- ✅ `mp_linked_at` tiene fecha/hora actual

---

## 🎯 POR QUÉ ESTO ES IMPORTANTE

### Modelo de Split Payment:

```
Pagador paga $10,000
        ↓
   Mercado Pago divide automáticamente:
        ↓
    ┌───┴────┐
    ↓        ↓
 $9,500    $500
    ↓        ↓
TESORERO  QUIBU
(cuenta   (cuenta fija
OAuth)    en .env)
```

**Cuenta de QUIBU (empresa):**
- Email: acelis@seidgc.cl
- Recibe: FEES (comisiones de 5%)
- Configuración: FIJA en archivo `.env`
- NO se vincula por OAuth

**Cuenta del TESORERO (usuario app):**
- Email: patricio.hp.iqq@gmail.com (o tu email personal)
- Recibe: PAGOS principales (95%)
- Configuración: OAuth desde la app
- SÍ debe vincularse por OAuth

---

## 🧪 PROBAR QUE FUNCIONA

### 1. Hacer un Pago de Prueba

```
1. Ir a: https://quibu.cl/pagar/?grupo=10
2. Ingresar RUT del pagador
3. Seleccionar cuotas (ej: $10,000)
4. Sistema suma fee: $10,000 + $500 = $10,500 total
5. Pagar con tarjeta de prueba
```

**Tarjeta de prueba (modo test):**
```
Número: 5031 7557 3453 0604
CVV: 123
Vencimiento: 11/25
Nombre: APRO
```

### 2. Verificar Dinero Llegó Correctamente

**En cuenta del TESORERO (tu cuenta personal MP):**
- Login en mercadopago.cl con TU cuenta
- Ver "Actividad" → Debe aparecer: **$10,000 recibidos**

**En cuenta de QUIBU (acelis@seidgc.cl):**
- Login en mercadopago.cl con acelis@seidgc.cl
- Ver "Actividad" → Debe aparecer: **$500 recibidos** (fee)

---

## 📱 URLs ÚTILES

- Panel MP: https://www.mercadopago.cl/developers/panel/app
- Validar config: https://quibu.cl/api/validar-config-mp.php
- Verificar vinculación: https://quibu.cl/login.php?email=patricio.hp.iqq@gmail.com

---

## 🆘 SI ALGO FALLA

### Error: "State token inválido"
→ El OAuth ahora es simple, no debería pasar. Si pasa, revisar logs:
```bash
sudo tail -50 /var/log/apache2/error.log | grep OAuth
```

### Error: "No se recibió access token"
→ Verificar credenciales en .env:
```bash
cat /var/www/html/.env | grep MP_CLIENT
```

Deben estar:
```
MP_CLIENT_ID=8052400951224123
MP_CLIENT_SECRET=VhgrJgobTlcl9vxJ6SC8lpiN1aVHorPk
```

### Error: "redirect_uri_mismatch"
→ Verificar en panel de MP que esté configurada:
```
https://quibu.cl/api/mp-callback.php
```

---

## 📝 CHECKLIST

- [ ] Paso 1: Desvincular cuenta incorrecta (SQL ejecutado)
- [ ] Paso 2: Cerrar sesión de MP en navegador
- [ ] Paso 3: Vincular cuenta correcta desde app
- [ ] Paso 4: Verificar mp_user_id es diferente a 3052545777
- [ ] Paso 5: Hacer pago de prueba
- [ ] Paso 6: Verificar dinero llegó al tesorero ($10,000)
- [ ] Paso 7: Verificar fee llegó a Quibu ($500)

---

## 🎓 CONCEPTOS CLAVE

1. **Dos cuentas, dos roles:**
   - Quibu = recibe fees (fija en .env)
   - Tesorero = recibe pagos (OAuth desde app)

2. **Cada tesorero necesita su propia cuenta MP personal**

3. **El fee se suma al pagador, NO se descuenta al tesorero:**
   - Cuotas: $10,000
   - Fee: $500
   - Pagador paga: $10,500 TOTAL
   - Tesorero recibe: $10,000 completos
   - Quibu recibe: $500

4. **El split es AUTOMÁTICO por Mercado Pago:**
   - No hay que hacer nada manualmente
   - MP divide el dinero al instante del pago

---

## 🔧 ARCHIVOS DE SOPORTE

Si necesitas más info:

- `SPLIT_PAYMENT_EXPLICACION.md` - Explicación detallada del modelo
- `DESPLIEGUE_PRODUCCION.md` - Guía completa de deployment
- `CONFIGURACION_MERCADOPAGO.md` - Setup de credenciales
- `api/mp-desvincular.php` - Endpoint para desvincular desde API
- `desvincular-cuenta-mp.sql` - Script SQL completo

---

## ⏱️ TIEMPO ESTIMADO

- Desvincular: 30 segundos
- Vincular correctamente: 2 minutos
- Verificar: 1 minuto
- Prueba de pago: 3 minutos

**TOTAL: ~7 minutos**

---

**ESTADO:** ✅ Código listo, solo falta ejecutar estos 4 pasos
