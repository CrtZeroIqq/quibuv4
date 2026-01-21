# 🚨 LEEME PRIMERO - Quibu V4 OAuth

**Última actualización:** 2026-01-21
**Estado:** OAuth funciona pero vinculó cuenta equivocada - Solución lista

---

## ⚡ ACCIÓN RÁPIDA (7 minutos)

```bash
cd /home/user/quibuv4

# 1. Desvincular cuenta incorrecta
bash fix-oauth.sh

# 2. Cerrar sesión de MP en navegador
# Ve a mercadopago.cl y cierra sesión

# 3. Desde la app: Vincular Mercado Pago
# Iniciar sesión con TU cuenta personal (NO acelis@seidgc.cl)

# 4. Verificar
bash verify-oauth.sh
```

---

## 📚 DOCUMENTOS POR IMPORTANCIA

### 🔥 Urgente - Lee Ahora:
1. **RESOLVER_OAUTH_AHORA.md** - Pasos exactos para resolver (7 min)
2. **RESUMEN_SESION_COMPLETA.md** - Todo lo que se hizo en esta sesión

### 📖 Conceptual - Para Entender:
3. **SPLIT_PAYMENT_EXPLICACION.md** - Cómo funciona el modelo de split
4. **CONFIGURACION_MERCADOPAGO.md** - Cómo obtener credenciales de MP

### 🚀 Deployment:
5. **DESPLIEGUE_PRODUCCION.md** - Guía completa de deployment

---

## 🛠️ SCRIPTS DISPONIBLES

```bash
# Desvincular cuenta incorrecta
bash fix-oauth.sh

# Verificar vinculación
bash verify-oauth.sh

# Probar configuración
php test-mp-config.php
```

---

## 🎯 PROBLEMA Y SOLUCIÓN

### ❌ Problema:
Se vinculó la cuenta de **QUIBU (empresa)** en vez de la cuenta **PERSONAL del tesorero**

### ✅ Solución:
1. Desvincular cuenta incorrecta
2. Vincular cuenta correcta del tesorero

### 🎓 Por Qué Importa:
```
Modelo correcto de split payment:

Pagador paga $10,500
        ↓
   Mercado Pago
        ↓
    SPLIT
   ↙     ↘
$10,000  $500
   ↓       ↓
TESORERO QUIBU
```

Si se vincula cuenta de Quibu → TODO el dinero va a Quibu
Si se vincula cuenta del tesorero → Split funciona correctamente

---

## 📁 ESTRUCTURA DE ARCHIVOS

```
quibuv4/
│
├── LEEME_PRIMERO.md                 ← 🔥 ESTE ARCHIVO
├── RESOLVER_OAUTH_AHORA.md          ← Pasos exactos (léelo)
├── RESUMEN_SESION_COMPLETA.md       ← Qué se hizo en la sesión
├── SPLIT_PAYMENT_EXPLICACION.md     ← Cómo funciona split payment
│
├── fix-oauth.sh                      ← Script para desvincular
├── verify-oauth.sh                   ← Script para verificar
├── test-mp-config.php               ← Probar configuración
│
├── .env                              ← Credenciales (restaurado)
│
└── api/
    ├── mp-callback.php              ← Callback OAuth
    ├── mp-obtener-url-oauth.php     ← Generar URL OAuth
    ├── mp-desvincular.php           ← Desvincular vía API
    └── validar-config-mp.php        ← Validar config
```

---

## ✅ CHECKLIST

- [x] Código OAuth implementado
- [x] Credenciales configuradas en .env
- [x] OAuth funciona (vincula cuentas)
- [x] Scripts de solución creados
- [ ] Desvincular cuenta incorrecta ← **HACER AHORA**
- [ ] Vincular cuenta correcta ← **HACER AHORA**
- [ ] Probar pago con split

---

## 🆘 AYUDA RÁPIDA

### Ver configuración actual:
```bash
https://quibu.cl/api/validar-config-mp.php
```

### Ver cuenta vinculada:
```bash
bash verify-oauth.sh
```

### Ver logs de error:
```bash
sudo tail -50 /var/log/apache2/error.log | grep OAuth
```

---

## 📞 CREDENCIALES (Backup)

```env
# Base de Datos
DB_USER=root
DB_PASS=vP7!qN$2mX#fJ9zLrE@k1WbC
DB_NAME=wp_quibu

# Mercado Pago OAuth
MP_CLIENT_ID=8052400951224123
MP_CLIENT_SECRET=VhgrJgobTlcl9vxJ6SC8lpiN1aVHorPk
```

---

## 🎯 PRÓXIMOS PASOS

1. **AHORA:** Ejecuta `bash fix-oauth.sh`
2. **LUEGO:** Vincula cuenta correcta desde app
3. **VERIFICAR:** Ejecuta `bash verify-oauth.sh`
4. **PROBAR:** Hace un pago de prueba

**Tiempo total:** 7 minutos

---

## 💡 CONCEPTOS CLAVE

**Dos cuentas, dos roles:**
- **Quibu (acelis@seidgc.cl):** Recibe fees → Fija en .env
- **Tesorero (cuenta personal):** Recibe pagos → OAuth desde app

**Cada tesorero vincula su propia cuenta personal de Mercado Pago**

---

**¿Dudas?** Lee: `RESUMEN_SESION_COMPLETA.md`
**¿Perdiste continuidad?** Este archivo + RESOLVER_OAUTH_AHORA.md tienen todo
