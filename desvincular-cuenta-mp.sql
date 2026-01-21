-- ====================================================
-- Script para desvincular cuenta de Mercado Pago
-- ====================================================
--
-- IMPORTANTE: Este script desvincula la cuenta de MP de un usuario
-- para que pueda vincular una cuenta diferente.
--
-- Ejecutar desde MySQL:
-- mysql -u root -p wp_quibu < desvincular-cuenta-mp.sql
--
-- ====================================================

USE wp_quibu;

-- 1. Ver estado actual de la cuenta vinculada
SELECT
    '=== CUENTA ACTUALMENTE VINCULADA ===' as info;

SELECT
    id,
    nombre,
    email,
    mp_user_id,
    LEFT(mp_access_token, 20) as token_preview,
    mp_linked_at
FROM wp_usuarios_app
WHERE id = 27;

-- 2. Desvincular cuenta de Mercado Pago
SELECT
    '=== DESVINCULANDO CUENTA ===' as info;

UPDATE wp_usuarios_app
SET
    mp_access_token = NULL,
    mp_user_id = NULL,
    mp_public_key = NULL,
    mp_refresh_token = NULL,
    mp_linked_at = NULL
WHERE id = 27;

-- 3. Verificar desvinculación
SELECT
    '=== CUENTA DESVINCULADA EXITOSAMENTE ===' as info;

SELECT
    id,
    nombre,
    email,
    mp_user_id,
    mp_access_token,
    mp_linked_at
FROM wp_usuarios_app
WHERE id = 27;

SELECT
    '
    ✅ La cuenta ha sido desvinculada.

    PRÓXIMOS PASOS:

    1. Cerrar sesión de Mercado Pago en el navegador:
       - Ve a https://www.mercadopago.cl
       - Cierra sesión de acelis@seidgc.cl

    2. Desde la app móvil:
       - Login con patricio.hp.iqq@gmail.com
       - Tocar "Vincular Mercado Pago"
       - Iniciar sesión con TU cuenta PERSONAL de Mercado Pago
       - Autorizar la aplicación

    3. Verificar vinculación correcta:
       SELECT nombre, email, mp_user_id, mp_linked_at
       FROM wp_usuarios_app
       WHERE id = 27;

    El mp_user_id debe ser DIFERENTE a 3052545777
    (que es el ID de la cuenta de Quibu)

    ' as instrucciones;
