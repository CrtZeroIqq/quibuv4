# 🔄 Split Payment: Flujo de Dinero en Quibu

## 🎯 Modelo de Negocio

Quibu funciona como un **marketplace** con split automático de pagos:

```
┌─────────────────────────────────────────────┐
│         Pagador paga $10,000                │
└──────────────────┬──────────────────────────┘
                   │
        ┌──────────▼──────────┐
        │  Mercado Pago       │
        │  procesa el pago    │
        └──────────┬──────────┘
                   │
        ┌──────────▼──────────────────────┐
        │  SPLIT AUTOMÁTICO                │
        └─────────┬───────────┬────────────┘
                  │           │
         ┌────────▼─────┐   ┌─▼────────────┐
         │  $9,500      │   │  $500 (5%)   │
         │  → TESORERO  │   │  → QUIBU     │
         │  (Patricio)  │   │  (Empresa)   │
         └──────────────┘   └──────────────┘
```

## 👥 Dos Cuentas, Dos Roles Diferentes

### 1. Cuenta de QUIBU (Empresa/Marketplace)
- **Email:** acelis@seidgc.cl
- **MP User ID:** 3052545777
- **Rol:** Recibe los FEES (comisiones)
- **Configuración:** FIJA en el archivo `.env`
- **NO se vincula por OAuth**

```env
# Esta es la cuenta de QUIBU - configurada fijamente en .env
QUIBU_MP_ACCESS_TOKEN=APP_USR-8052400951224123-010519-f6108fb2837d6cc53b4f5fcb35fdf445-3052545777
QUIBU_MP_PUBLIC_KEY=APP_USR-d6456ca9-af2b-49d1-95c2-9e602b944a4a
```

### 2. Cuenta del TESORERO (Usuario de la app)
- **Email:** patricio.hp.iqq@gmail.com (o la cuenta personal de cada tesorero)
- **MP User ID:** Variable (diferente para cada tesorero)
- **Rol:** Recibe el DINERO PRINCIPAL de los pagos de su grupo
- **Configuración:** Se vincula por OAUTH desde la app
- **SÍ se vincula por OAuth**

```sql
-- Esta cuenta se guarda en la base de datos
SELECT
    id,
    nombre,
    email,
    mp_user_id,           -- ID de la cuenta personal del tesorero
    mp_access_token       -- Token de la cuenta personal del tesorero
FROM wp_usuarios_app
WHERE id = 27;
```

## ❌ El Problema Actual

En la captura de pantalla veo que se vinculó:

```
Usuario MP: acelis@seidgc.cl
ID de Usuario: 3052545777
```

**Esto está MAL** porque:
- Se vinculó la cuenta de QUIBU (la empresa)
- Esta cuenta ya está configurada en `.env` y no debe vincularse por OAuth
- Cuando alguien pague, TODO el dinero iría a Quibu, no al tesorero

## ✅ La Solución

Patricio (el tesorero) debe:

### Opción A: Si tiene cuenta propia de Mercado Pago

1. **Desvincular la cuenta actual:**
   ```sql
   UPDATE wp_usuarios_app
   SET
       mp_access_token = NULL,
       mp_user_id = NULL,
       mp_public_key = NULL,
       mp_refresh_token = NULL,
       mp_linked_at = NULL
   WHERE id = 27;
   ```

2. **Cerrar sesión de Mercado Pago** en el navegador:
   - Ve a https://www.mercadopago.cl
   - Cierra sesión de `acelis@seidgc.cl`

3. **Vincular de nuevo desde la app:**
   - Abre la app Quibu
   - Toca "Vincular Mercado Pago"
   - En la página de autorización, **inicia sesión con TU cuenta personal** (patricio.hp.iqq@gmail.com)
   - Autoriza

4. **Verificar que se vinculó la cuenta correcta:**
   ```sql
   SELECT
       nombre,
       email,
       mp_user_id,
       mp_linked_at
   FROM wp_usuarios_app
   WHERE id = 27;
   ```

   Debería mostrar un `mp_user_id` DIFERENTE a 3052545777

### Opción B: Si NO tiene cuenta de Mercado Pago

1. **Crear cuenta en Mercado Pago:**
   - Ve a https://www.mercadopago.cl
   - Regístrate con tu email personal (patricio.hp.iqq@gmail.com)
   - Completa la verificación de identidad

2. **Luego sigue los pasos de la Opción A**

## 📊 Flujo Correcto de Pago

Una vez que el tesorero vincule SU PROPIA cuenta:

```
1. Usuario pagador va a: https://quibu.cl/pagar/?grupo=10

2. Selecciona cuotas a pagar: $10,000

3. Sistema calcula split:
   - Subtotal: $10,000
   - Fee Quibu (5%): $500
   - Total a pagar: $10,500

4. Sistema crea preferencia MP usando:
   - access_token del TESORERO (de wp_usuarios_app.mp_access_token)
   - marketplace_fee: $500

5. Pagador paga en Mercado Pago

6. Mercado Pago divide automáticamente:
   ┌─────────────────────┐
   │ $10,000             │
   │ → Cuenta Tesorero   │ ← mp_user_id del tesorero
   │ (Patricio)          │
   └─────────────────────┘

   ┌─────────────────────┐
   │ $500 (fee)          │
   │ → Cuenta Quibu      │ ← Dueño del CLIENT_ID
   │ (acelis@seidgc.cl)  │
   └─────────────────────┘

7. Webhook notifica a Quibu y marca cuotas como pagadas
```

## 🔐 Cómo Mercado Pago Sabe Quién Recibe el Fee

El `marketplace_fee` SIEMPRE va al **dueño del CLIENT_ID** usado en el OAuth.

En tu caso:
```env
MP_CLIENT_ID=8052400951224123    ← Este CLIENT_ID pertenece a acelis@seidgc.cl
```

Por eso no necesitas configurar nada más - Mercado Pago automáticamente envía el fee a la cuenta dueña del CLIENT_ID.

## 🧪 Probar el Split Payment

### 1. Desvincular cuenta incorrecta

Desde MySQL:
```sql
USE wp_quibu;

UPDATE wp_usuarios_app
SET
    mp_access_token = NULL,
    mp_user_id = NULL,
    mp_public_key = NULL,
    mp_refresh_token = NULL,
    mp_linked_at = NULL
WHERE id = 27;
```

### 2. Verificar desvinculación

```sql
SELECT
    id,
    nombre,
    email,
    mp_user_id,
    mp_linked_at
FROM wp_usuarios_app
WHERE id = 27;
```

Debe mostrar:
```
mp_user_id: NULL
mp_linked_at: NULL
```

### 3. Vincular cuenta correcta

Desde la app:
1. Login con: patricio.hp.iqq@gmail.com
2. Tocar "Vincular Mercado Pago"
3. **IMPORTANTE:** Cerrar sesión de acelis@seidgc.cl en el navegador primero
4. Iniciar sesión con cuenta PERSONAL del tesorero
5. Autorizar

### 4. Verificar vinculación correcta

```sql
SELECT
    nombre,
    email,
    mp_user_id
FROM wp_usuarios_app
WHERE id = 27;
```

El `mp_user_id` debe ser DIFERENTE a 3052545777

### 5. Hacer pago de prueba

1. Ir a: https://quibu.cl/pagar/?grupo=10
2. Ingresar RUT del pagador
3. Seleccionar cuotas
4. Pagar con tarjeta de prueba de Mercado Pago

**Tarjetas de prueba (Modo test):**
```
APRO (Aprobada):
  Número: 5031 7557 3453 0604
  CVV: 123
  Vencimiento: 11/25

OTHE (Rechazada):
  Número: 5031 4332 1540 6351
  CVV: 123
  Vencimiento: 11/25
```

### 6. Verificar que el dinero llegó al tesorero

Después del pago exitoso:

1. **En la cuenta del tesorero (Patricio):**
   - Login en https://www.mercadopago.cl con cuenta personal
   - Ver en "Actividad" → Debe aparecer el pago de $10,000

2. **En la cuenta de Quibu (acelis@seidgc.cl):**
   - Login en https://www.mercadopago.cl
   - Ver en "Actividad" → Debe aparecer el fee de $500

## 📝 Resumen

| Concepto | Cuenta Quibu | Cuenta Tesorero |
|----------|--------------|-----------------|
| Email | acelis@seidgc.cl | patricio.hp.iqq@gmail.com |
| MP User ID | 3052545777 | (Diferente) |
| Rol | Recibe FEES | Recibe PAGOS |
| Configuración | Fija en `.env` | OAuth desde app |
| Recibe | $500 (5%) | $10,000 (95%) |

## ❓ FAQ

**P: ¿Por qué el pagador paga $10,500 si las cuotas suman $10,000?**
R: Porque el fee de Quibu ($500) se suma al total. El tesorero recibe $10,000 completos.

**P: ¿Puedo cambiar el porcentaje del fee?**
R: Sí, en el archivo `.env`:
```env
QUIBU_COMMISSION_PERCENT=5   # 5% de fee
```

**P: ¿Cada tesorero necesita su propia cuenta de Mercado Pago?**
R: Sí, cada tesorero debe tener y vincular su propia cuenta para recibir los pagos de su grupo.

**P: ¿La cuenta de Quibu recibe pagos automáticamente?**
R: Sí, el `marketplace_fee` se transfiere automáticamente a la cuenta dueña del CLIENT_ID (Quibu).

**P: ¿Qué pasa si el tesorero no vincula su cuenta?**
R: No se pueden procesar pagos. El sistema muestra: "El tesorero del grupo no tiene vinculada su cuenta de Mercado Pago."
