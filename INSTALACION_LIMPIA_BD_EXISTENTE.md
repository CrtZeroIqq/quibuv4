# 🔄 Instalación Limpia de Quibu V4 con Base de Datos Existente

## 📋 Resumen

Esta guía te permite instalar **Quibu V4 de forma limpia** manteniendo tu **base de datos existente** con todos los datos de versiones anteriores (quibuv2, quibuv3).

### ✅ Lo que se CONSERVA:
- ✅ Toda tu base de datos actual
- ✅ Todos los grupos existentes
- ✅ Todos los pagadores registrados
- ✅ Todas las cuotas definidas
- ✅ Todos los pagos realizados
- ✅ Configuraciones existentes

### 🔄 Lo que se REEMPLAZA:
- 🔄 Archivos PHP de la aplicación
- 🔄 Frontend (HTML/CSS/JS)
- 🔄 APIs
- 🔄 Dependencias (vendor/)

---

## 🚀 Método 1: Script Automatizado (Recomendado)

### Paso 1: Conectar al Servidor

```bash
ssh usuario@tu-servidor.com
cd /var/www/html/
```

### Paso 2: Clonar el Repositorio

```bash
# Clonar Quibu V4
git clone https://github.com/CrtZeroIqq/quibuv4.git
cd quibuv4
```

### Paso 3: Ejecutar el Script

```bash
# Hacer el script ejecutable
chmod +x install-clean-keep-db.sh

# Ejecutar como root
sudo bash install-clean-keep-db.sh
```

### Paso 4: Seguir los Prompts

El script te preguntará:
- ✅ Usuario y contraseña de MySQL
- ✅ Nombre de tu base de datos existente
- ✅ Si deseas eliminar quibuv2 y quibuv3_old
- ✅ Si deseas crear tablas faltantes
- ✅ Si deseas ejecutar migración de Mercado Pago
- ✅ Si deseas configurar Apache

### Paso 5: Configurar `.env`

El script creará el archivo `.env` automáticamente con tu base de datos, pero debes agregar:

```bash
nano /var/www/html/quibuv4/.env
```

Agregar tus credenciales:
```env
# Mercado Pago
MP_CLIENT_ID=tu_client_id
MP_CLIENT_SECRET=tu_client_secret
QUIBU_MP_ACCESS_TOKEN=tu_access_token
QUIBU_MP_PUBLIC_KEY=tu_public_key

# Transbank
TRANSBANK_MODE=integration
TRANSBANK_API_KEY=tu_api_key
TRANSBANK_COMMERCE_CODE=tu_commerce_code
```

---

## 📝 Método 2: Instalación Manual Paso a Paso

### Paso 1: Backup (CRÍTICO)

```bash
# Crear directorio de backup
BACKUP_DIR="/home/backup-quibu-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup de archivos
sudo tar -czf "$BACKUP_DIR/archivos.tar.gz" \
  /var/www/html/api \
  /var/www/html/dashboard \
  /var/www/html/pagar \
  /var/www/html/pago-landing \
  /var/www/html/quibuv2 \
  /var/www/html/quibuv3_old

# Backup de base de datos
mysqldump -u root -p tu_base_datos > "$BACKUP_DIR/database.sql"
gzip "$BACKUP_DIR/database.sql"

echo "Backup guardado en: $BACKUP_DIR"
```

### Paso 2: Verificar Base de Datos Actual

```bash
# Conectar a MySQL
mysql -u root -p

# Verificar base de datos
USE tu_base_datos;
SHOW TABLES;

# Verificar datos existentes
SELECT COUNT(*) FROM wp_grupos_cobranza;
SELECT COUNT(*) FROM wp_pagadores;
SELECT COUNT(*) FROM wp_pagos_cuotas;

EXIT;
```

### Paso 3: Instalar Archivos de Quibu V4

```bash
cd /var/www/html/

# Clonar repo
git clone https://github.com/CrtZeroIqq/quibuv4.git

# Verificar contenido
ls -la quibuv4/
```

### Paso 4: Instalar Dependencias

```bash
cd /var/www/html/quibuv4

# Instalar con Composer
composer install --no-dev --optimize-autoloader
```

### Paso 5: Configurar .env

```bash
# Copiar ejemplo
cp .env.example .env

# Editar con tus datos
nano .env
```

**Configurar con tu base de datos EXISTENTE:**

```env
# ======================
# BASE DE DATOS (LA QUE YA TIENES)
# ======================
DB_HOST=localhost
DB_NAME=tu_base_datos_existente    # ← IMPORTANTE: tu BD actual
DB_USER=tu_usuario                 # ← Tu usuario MySQL actual
DB_PASS=tu_contraseña              # ← Tu contraseña MySQL actual

# ======================
# MERCADO PAGO - OAUTH
# ======================
MP_CLIENT_ID=tu_client_id
MP_CLIENT_SECRET=tu_client_secret
MP_REDIRECT_URI=https://www.quibu.cl/quibuv4/api/mp-callback.php
MP_WEBHOOK_URL=https://www.quibu.cl/quibuv4/api/mp-webhook.php
MP_MODE=sandbox

# URLs de retorno
MP_SUCCESS_URL=https://www.quibu.cl/quibuv4/pago-exitoso.php
MP_FAILURE_URL=https://www.quibu.cl/quibuv4/pago-fallido.php
MP_PENDING_URL=https://www.quibu.cl/quibuv4/pago-pendiente.php

# ======================
# MERCADO PAGO - QUIBU (COLLECTOR)
# ======================
QUIBU_MP_ACCESS_TOKEN=tu_access_token_quibu
QUIBU_MP_PUBLIC_KEY=tu_public_key_quibu

# ======================
# TRANSBANK
# ======================
TRANSBANK_MODE=integration
TRANSBANK_API_KEY=579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C
TRANSBANK_COMMERCE_CODE=597055555532
TRANSBANK_RETURN_URL=https://www.quibu.cl/quibuv4/api/respuesta-pago.php

# ======================
# GENERAL
# ======================
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.quibu.cl/quibuv4
```

### Paso 6: Verificar Schema de BD

**⚠️ IMPORTANTE: No ejecutes `schema.sql` completo si ya tienes datos**

En su lugar, verifica si faltan tablas o columnas:

```bash
# Conectar a MySQL
mysql -u root -p tu_base_datos

# Verificar si existen las tablas requeridas
SHOW TABLES LIKE 'wp_%';

# Verificar si wp_usuarios_app tiene columnas de Mercado Pago
DESCRIBE wp_usuarios_app;
```

**Si FALTAN columnas de Mercado Pago:**

```sql
-- Solo ejecutar si las columnas NO existen
ALTER TABLE wp_usuarios_app
ADD COLUMN IF NOT EXISTS mp_access_token TEXT COMMENT 'Access token OAuth de Mercado Pago',
ADD COLUMN IF NOT EXISTS mp_user_id VARCHAR(100) COMMENT 'ID de usuario en Mercado Pago',
ADD COLUMN IF NOT EXISTS mp_public_key TEXT COMMENT 'Public key de Mercado Pago',
ADD COLUMN IF NOT EXISTS mp_refresh_token TEXT COMMENT 'Refresh token',
ADD COLUMN IF NOT EXISTS mp_linked_at DATETIME COMMENT 'Fecha de vinculación';

-- Verificar
DESCRIBE wp_usuarios_app;
EXIT;
```

**O ejecutar el script de migración:**

```bash
mysql -u root -p tu_base_datos < /var/www/html/quibuv4/database/migration_mercadopago.sql
```

### Paso 7: Configurar Permisos

```bash
# Permisos de archivos
sudo chown -R www-data:www-data /var/www/html/quibuv4
sudo find /var/www/html/quibuv4 -type d -exec chmod 755 {} \;
sudo find /var/www/html/quibuv4 -type f -exec chmod 644 {} \;

# Proteger .env
sudo chmod 600 /var/www/html/quibuv4/.env

# Logs (si existe)
sudo mkdir -p /var/www/html/quibuv4/logs
sudo chmod 775 /var/www/html/quibuv4/logs
```

### Paso 8: Configurar Apache (Opcional)

**Opción A: VirtualHost Dedicado**

```bash
sudo nano /etc/apache2/sites-available/quibuv4.conf
```

```apache
<VirtualHost *:80>
    ServerName www.quibu.cl

    DocumentRoot /var/www/html/quibuv4

    <Directory /var/www/html/quibuv4>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/quibuv4-error.log
    CustomLog ${APACHE_LOG_DIR}/quibuv4-access.log combined

    <FilesMatch "^\.env$">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

```bash
# Activar sitio
sudo a2ensite quibuv4.conf
sudo a2enmod rewrite headers
sudo apache2ctl configtest
sudo systemctl reload apache2
```

**Opción B: Usar .htaccess (ya incluido)**

Si ya tienes Apache configurado, el `.htaccess` incluido funcionará automáticamente.

### Paso 9: Configurar SSL

```bash
# Instalar Certbot si no lo tienes
sudo apt update
sudo apt install certbot python3-certbot-apache

# Obtener certificado
sudo certbot --apache -d www.quibu.cl -d quibu.cl

# Verificar renovación automática
sudo certbot renew --dry-run
```

### Paso 10: Verificar Instalación

```bash
# Verificar archivos
ls -la /var/www/html/quibuv4/

# Verificar .env
cat /var/www/html/quibuv4/.env | grep DB_NAME

# Verificar conexión a BD
php -r "
require '/var/www/html/quibuv4/api/conexion.php';
try {
    \$pdo = getConnection();
    echo 'Conexión exitosa' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"

# Ver logs de Apache
sudo tail -f /var/log/apache2/quibuv4-error.log
```

---

## 🧪 Testing con Datos Existentes

### Verificar que los Datos se Muestran

1. **Probar Landing Page:**
   ```
   https://www.quibu.cl/quibuv4/pago-landing/
   ```

2. **Buscar un RUT existente:**
   - Ir a la landing page
   - Ingresar un RUT que ya existe en tu BD
   - Debería redirigir a pago directo

3. **Ver grupo existente:**
   ```
   https://www.quibu.cl/quibuv4/pagar/?grupo=1
   ```
   (Reemplaza `1` con un ID de grupo que ya tengas)

4. **Probar API de estadísticas:**
   ```
   https://www.quibu.cl/quibuv4/api/estadisticas_publicas_grupo.php?idGrupo=1
   ```

### Verificar Datos en MySQL

```sql
USE tu_base_datos;

-- Ver grupos existentes
SELECT * FROM wp_grupos_cobranza;

-- Ver pagadores existentes
SELECT * FROM wp_pagadores LIMIT 10;

-- Ver cuotas existentes
SELECT * FROM wp_cuotas_definidas LIMIT 10;

-- Ver pagos realizados
SELECT * FROM wp_pagos_cuotas ORDER BY fecha_pago DESC LIMIT 10;

-- Estadísticas rápidas
SELECT
    (SELECT COUNT(*) FROM wp_grupos_cobranza) AS total_grupos,
    (SELECT COUNT(*) FROM wp_pagadores) AS total_pagadores,
    (SELECT COUNT(*) FROM wp_cuotas_definidas) AS total_cuotas,
    (SELECT COUNT(*) FROM wp_pagos_cuotas) AS total_pagos,
    (SELECT SUM(monto_pagado) FROM wp_pagos_cuotas) AS total_recaudado;
```

---

## 🔄 Migración de Datos (Si es Necesario)

Si tus tablas tienen nombres diferentes o estructura diferente:

### Paso 1: Identificar Diferencias

```sql
-- Ver estructura de tus tablas actuales
SHOW TABLES;
DESCRIBE nombre_de_tu_tabla_grupos;
DESCRIBE nombre_de_tu_tabla_pagadores;
```

### Paso 2: Crear Script de Migración Personalizado

Ejemplo si tus tablas se llaman diferente:

```sql
-- Migrar grupos
INSERT INTO wp_grupos_cobranza (nombre_grupo, tipo_grupo, cantidad_personas, id_usuario)
SELECT nombre, tipo, cantidad, usuario_id
FROM tu_tabla_grupos_antigua;

-- Migrar pagadores
INSERT INTO wp_pagadores (id_grupo, rut, nombre, email, telefono)
SELECT grupo_id, rut_pagador, nombre_completo, correo, telefono_contacto
FROM tu_tabla_pagadores_antigua;

-- Verificar migración
SELECT COUNT(*) FROM wp_grupos_cobranza;
SELECT COUNT(*) FROM wp_pagadores;
```

---

## ⚠️ Problemas Comunes y Soluciones

### Error: "Table doesn't exist"

**Causa:** Faltan tablas en tu BD

**Solución:**
```bash
# Solo crear tablas faltantes (no elimina datos)
mysql -u root -p tu_base_datos < /var/www/html/quibuv4/database/schema.sql
```

### Error: "Column not found: mp_access_token"

**Causa:** Faltan columnas de Mercado Pago

**Solución:**
```bash
mysql -u root -p tu_base_datos < /var/www/html/quibuv4/database/migration_mercadopago.sql
```

### Datos Antiguos No se Muestran

**Causa:** Nombres de columnas diferentes

**Solución:**
```sql
-- Verificar nombres de columnas
DESCRIBE wp_grupos_cobranza;
DESCRIBE wp_pagadores;

-- Comparar con lo que espera el código
-- Adaptar nombres si es necesario
ALTER TABLE wp_grupos_cobranza CHANGE nombre_viejo nombre_grupo VARCHAR(255);
```

### Error: "Access denied for user"

**Causa:** Credenciales incorrectas en `.env`

**Solución:**
```bash
# Verificar .env
cat /var/www/html/quibuv4/.env | grep DB_

# Probar conexión
mysql -u tu_usuario -p tu_base_datos -e "SELECT 1"
```

---

## 📊 Comparación de Versiones

### Compatibilidad de Tablas

| Tabla | Quibu V2/V3 | Quibu V4 | Cambios |
|-------|-------------|----------|---------|
| Grupos | ✅ Compatible | ✅ Compatible | Agregado: mp_* columns en usuarios |
| Pagadores | ✅ Compatible | ✅ Compatible | Sin cambios |
| Cuotas | ✅ Compatible | ✅ Compatible | Sin cambios |
| Pagos | ⚠️ Parcial | ✅ Extendido | Agregado: metodo_pago, payment_data |
| Usuarios | ⚠️ Parcial | ✅ Extendido | Agregado: mp_access_token, mp_user_id, etc. |

### Columnas Nuevas en V4

**wp_usuarios_app:**
- `mp_access_token` - Token OAuth de Mercado Pago
- `mp_user_id` - ID de usuario en MP
- `mp_public_key` - Public key de MP
- `mp_refresh_token` - Refresh token
- `mp_linked_at` - Fecha de vinculación

**wp_pagos_cuotas:**
- `metodo_pago` - ENUM('mercadopago', 'transbank')
- `payment_data` - JSON con detalles del pago

---

## ✅ Checklist Final

Después de la instalación, verifica:

- [ ] Archivos de Quibu V4 instalados en `/var/www/html/quibuv4/`
- [ ] Dependencias instaladas con Composer
- [ ] Archivo `.env` configurado con tu BD existente
- [ ] Columnas de Mercado Pago agregadas a `wp_usuarios_app`
- [ ] Permisos configurados (755/644/600)
- [ ] Apache configurado y funcionando
- [ ] SSL habilitado (recomendado)
- [ ] Landing page carga correctamente
- [ ] RUT existente busca y redirige correctamente
- [ ] Grupos existentes se muestran en pagar/?grupo=ID
- [ ] Datos antiguos visibles en la interfaz
- [ ] Webhook de Mercado Pago configurado
- [ ] Flujo de pago probado con Transbank/MP
- [ ] Backup guardado de forma segura

---

## 🔐 Seguridad

### Post-Instalación

```bash
# Proteger .env
sudo chmod 600 /var/www/html/quibuv4/.env

# Verificar que Apache bloquea .env
curl https://www.quibu.cl/quibuv4/.env
# Debería dar 403 Forbidden

# Desactivar debug en producción
nano /var/www/html/quibuv4/.env
# Cambiar: APP_DEBUG=false
```

---

## 📞 Soporte

Si tienes problemas:

1. **Revisar logs:**
   ```bash
   sudo tail -f /var/log/apache2/quibuv4-error.log
   sudo tail -f /var/log/apache2/error.log
   ```

2. **Verificar conexión BD:**
   ```bash
   php -r "require '/var/www/html/quibuv4/api/conexion.php'; getConnection();"
   ```

3. **Verificar permisos:**
   ```bash
   ls -la /var/www/html/quibuv4/
   ```

4. **Restaurar backup si es necesario:**
   ```bash
   # Restaurar archivos
   tar -xzf /home/backup-quibu-FECHA/archivos.tar.gz -C /

   # Restaurar BD
   gunzip < /home/backup-quibu-FECHA/database.sql.gz | mysql -u root -p tu_base_datos
   ```

---

## 🎉 ¡Listo!

Tu instalación de Quibu V4 está completa con todos tus datos conservados.

**URLs de acceso:**
- Landing: `https://www.quibu.cl/quibuv4/pago-landing/`
- Pagar: `https://www.quibu.cl/quibuv4/pagar/?grupo=ID`
- Dashboard: `https://www.quibu.cl/quibuv4/dashboard/`

**Próximos pasos:**
1. Configurar webhooks en Mercado Pago
2. Probar flujo de pago completo
3. Migrar a credenciales de producción
4. Monitorear logs durante los primeros días
