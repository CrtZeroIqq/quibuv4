# 🚀 Guía de Instalación de Quibu V4 en Servidor Existente

## 📋 Situación Actual del Servidor

Basado en la estructura visible, tu servidor tiene:

```
/var/www/html/
├── wordpress/                # Instalación WordPress existente
│   ├── wp-admin/
│   ├── wp-content/
│   └── wp-includes/
├── quibuv2/                 # Versión anterior de Quibu
├── quibuv3_old/             # Otra versión anterior
├── api/                     # APIs (pueden ser de WordPress o Quibu)
├── dashboard/
├── pagar/
├── pago-landing/
├── vendor/                  # Dependencias PHP (Composer)
└── [otros archivos PHP]
```

---

## 🎯 Objetivo

Instalar **Quibu V4** de forma limpia sin afectar las instalaciones existentes.

---

## 📦 Opción 1: Instalación en Subdirectorio (Recomendado para Testing)

Instalar Quibu V4 en un subdirectorio separado para no interferir con lo existente.

### Paso 1: Conectarse al Servidor

```bash
# SSH al servidor
ssh usuario@tu-servidor.com

# Navegar al directorio web
cd /var/www/html/
```

### Paso 2: Crear Directorio para Quibu V4

```bash
# Crear directorio limpio para Quibu V4
sudo mkdir -p quibuv4
cd quibuv4
```

### Paso 3: Clonar el Repositorio

```bash
# Si tienes Git configurado
git clone https://github.com/CrtZeroIqq/quibuv4.git .

# O usar la rama específica
git clone -b claude/review-project-WKPJL https://github.com/CrtZeroIqq/quibuv4.git .
```

### Paso 4: Instalar Dependencias

```bash
# Verificar que Composer esté instalado
composer --version

# Si no está instalado:
# curl -sS https://getcomposer.org/installer | php
# sudo mv composer.phar /usr/local/bin/composer

# Instalar dependencias
composer install --no-dev --optimize-autoloader
```

### Paso 5: Configurar Variables de Entorno

```bash
# Copiar el archivo de ejemplo
cp .env.example .env

# Editar con tus credenciales
nano .env
```

**Configurar el archivo `.env`:**

```env
# ==================== BASE DE DATOS ====================
DB_HOST=localhost
DB_NAME=quibu_db                    # Nombre de tu base de datos
DB_USER=tu_usuario_mysql            # Tu usuario MySQL
DB_PASS=tu_contraseña_mysql         # Tu contraseña MySQL

# ==================== MERCADO PAGO - OAUTH ====================
MP_CLIENT_ID=TU_CLIENT_ID           # Desde https://www.mercadopago.cl/developers/panel/app
MP_CLIENT_SECRET=TU_CLIENT_SECRET
MP_REDIRECT_URI=https://www.quibu.cl/quibuv4/api/mp-callback.php
MP_WEBHOOK_URL=https://www.quibu.cl/quibuv4/api/mp-webhook.php
MP_MODE=sandbox                     # 'sandbox' para testing, 'production' para producción

# URLs de retorno
MP_SUCCESS_URL=https://www.quibu.cl/quibuv4/pago-exitoso.php
MP_FAILURE_URL=https://www.quibu.cl/quibuv4/pago-fallido.php
MP_PENDING_URL=https://www.quibu.cl/quibuv4/pago-pendiente.php

# ==================== MERCADO PAGO - QUIBU (COLLECTOR) ====================
# Cuenta de Mercado Pago de QUIBU que recibirá los FEES
QUIBU_MP_ACCESS_TOKEN=TU_ACCESS_TOKEN_QUIBU     # Desde https://www.mercadopago.cl/developers/panel/credentials
QUIBU_MP_PUBLIC_KEY=TU_PUBLIC_KEY_QUIBU

# ==================== TRANSBANK ====================
TRANSBANK_MODE=integration          # 'integration' para testing, 'production' para producción
TRANSBANK_API_KEY=579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C
TRANSBANK_COMMERCE_CODE=597055555532
TRANSBANK_RETURN_URL=https://www.quibu.cl/quibuv4/api/respuesta-pago.php

# ==================== TWILIO (Opcional - SMS) ====================
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_PHONE_NUMBER=

# ==================== GENERAL ====================
APP_ENV=production
APP_DEBUG=false                     # IMPORTANTE: false en producción
APP_URL=https://www.quibu.cl/quibuv4
```

**Guardar con:** `Ctrl + X`, luego `Y`, luego `Enter`

### Paso 6: Configurar Permisos

```bash
# Asignar propietario correcto (usuario web server)
sudo chown -R www-data:www-data /var/www/html/quibuv4

# Permisos de directorios
sudo find /var/www/html/quibuv4 -type d -exec chmod 755 {} \;

# Permisos de archivos
sudo find /var/www/html/quibuv4 -type f -exec chmod 644 {} \;

# Permisos especiales para logs (si existe)
sudo mkdir -p /var/www/html/quibuv4/logs
sudo chmod 775 /var/www/html/quibuv4/logs

# El archivo .env debe ser solo lectura
sudo chmod 600 /var/www/html/quibuv4/.env
```

### Paso 7: Crear/Migrar Base de Datos

```bash
# Conectar a MySQL
mysql -u root -p

# O si tienes un usuario específico:
mysql -u tu_usuario -p
```

**En el prompt de MySQL:**

```sql
-- Crear base de datos para Quibu V4
CREATE DATABASE IF NOT EXISTS quibu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE quibu_db;

-- Ejecutar el schema (copiar y pegar el contenido completo de database/schema.sql)
-- O importar desde archivo:
SOURCE /var/www/html/quibuv4/database/schema.sql;

-- Si ya tienes datos de versiones anteriores, ejecutar migración:
SOURCE /var/www/html/quibuv4/database/migration_mercadopago.sql;

-- Verificar tablas creadas
SHOW TABLES;

-- Salir
EXIT;
```

### Paso 8: Configurar Apache

**Opción A: Crear VirtualHost dedicado (Recomendado)**

```bash
# Crear archivo de configuración
sudo nano /etc/apache2/sites-available/quibuv4.conf
```

**Contenido del archivo:**

```apache
<VirtualHost *:80>
    ServerName www.quibu.cl
    ServerAlias quibu.cl

    DocumentRoot /var/www/html/quibuv4

    <Directory /var/www/html/quibuv4>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Seguridad: Deshabilitar listado de directorios
        Options -Indexes

        # PHP settings
        php_value upload_max_filesize 20M
        php_value post_max_size 20M
        php_value memory_limit 256M
        php_value max_execution_time 300
    </Directory>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/quibuv4-error.log
    CustomLog ${APACHE_LOG_DIR}/quibuv4-access.log combined

    # Proteger archivos sensibles
    <FilesMatch "^\.env$">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

**Guardar y activar:**

```bash
# Habilitar el sitio
sudo a2ensite quibuv4.conf

# Habilitar módulos necesarios
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl

# Verificar configuración
sudo apache2ctl configtest

# Si dice "Syntax OK", reiniciar Apache
sudo systemctl restart apache2
```

**Opción B: Usar subdirectorio en VirtualHost existente**

Si ya tienes un VirtualHost configurado, solo necesitas el archivo `.htaccess` que ya está en el proyecto.

Verificar que `/var/www/html/quibuv4/.htaccess` existe y contiene:

```apache
RewriteEngine On
RewriteBase /quibuv4/

# Permitir acceso a assets
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^assets/ - [L]

# Cache para archivos estáticos (1 año)
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType text/javascript "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
</IfModule>

# Seguridad
<FilesMatch "^\.env$">
    Require all denied
</FilesMatch>

# Headers de seguridad
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

### Paso 9: Configurar SSL/HTTPS (Altamente Recomendado)

```bash
# Instalar Certbot (Let's Encrypt)
sudo apt update
sudo apt install certbot python3-certbot-apache

# Obtener certificado SSL
sudo certbot --apache -d www.quibu.cl -d quibu.cl

# Certbot configurará automáticamente HTTPS
# Seguir las instrucciones en pantalla

# Verificar renovación automática
sudo certbot renew --dry-run
```

### Paso 10: Verificar Instalación

**Verificar archivos:**

```bash
# Verificar estructura
ls -la /var/www/html/quibuv4/

# Verificar que .env existe y tiene el contenido correcto
cat /var/www/html/quibuv4/.env

# Verificar permisos
ls -l /var/www/html/quibuv4/.env
```

**Probar en navegador:**

1. **Landing Page:** `https://www.quibu.cl/quibuv4/pago-landing/`
2. **Pago Agrupado:** `https://www.quibu.cl/quibuv4/pagar/?grupo=1`
3. **Pago Directo:** `https://www.quibu.cl/quibuv4/pagar/directo.php`
4. **API Estadísticas:** `https://www.quibu.cl/quibuv4/api/estadisticas_publicas_grupo.php?idGrupo=1`

### Paso 11: Configurar Webhooks en Mercado Pago

1. Ir a [Panel de Desarrolladores de Mercado Pago](https://www.mercadopago.cl/developers/panel/app)
2. Seleccionar tu aplicación
3. En la sección **Webhooks**, agregar:
   ```
   https://www.quibu.cl/quibuv4/api/mp-webhook.php
   ```
4. Seleccionar eventos: **Pagos (payments)**

### Paso 12: Verificar Logs

```bash
# Ver logs de Apache
sudo tail -f /var/log/apache2/quibuv4-error.log

# Ver logs de PHP
sudo tail -f /var/log/apache2/error.log

# Ver logs de la aplicación (si existen)
tail -f /var/www/html/quibuv4/logs/*.log
```

---

## 📦 Opción 2: Reemplazar Instalación Existente (Producción)

Si quieres reemplazar completamente las carpetas existentes:

### ⚠️ IMPORTANTE: Hacer Backup Primero

```bash
# Backup de archivos actuales
sudo tar -czf /home/backup-quibu-$(date +%Y%m%d).tar.gz \
  /var/www/html/api \
  /var/www/html/dashboard \
  /var/www/html/pagar \
  /var/www/html/pago-landing

# Backup de base de datos
mysqldump -u root -p quibu_db > /home/backup-quibu-db-$(date +%Y%m%d).sql
```

### Reemplazo de Archivos

```bash
cd /var/www/html/

# Eliminar carpetas antiguas (después de hacer backup)
sudo rm -rf api dashboard pagar pago-landing

# Clonar el nuevo repositorio
git clone https://github.com/CrtZeroIqq/quibuv4.git quibuv4-temp
cd quibuv4-temp

# Copiar carpetas necesarias
sudo cp -r api ../api
sudo cp -r dashboard ../dashboard
sudo cp -r pagar ../pagar
sudo cp -r pago-landing ../pago-landing
sudo cp -r vendor ../vendor

# Copiar archivos de nivel raíz
sudo cp pago-exitoso.php ../
sudo cp pago-fallido.php ../
sudo cp pago-pendiente.php ../
sudo cp composer.json ../
sudo cp .env.example ../

# Configurar .env
cd ..
cp .env.example .env
nano .env

# Limpiar
sudo rm -rf quibuv4-temp

# Ajustar permisos
sudo chown -R www-data:www-data api dashboard pagar pago-landing vendor
sudo chmod 600 .env
```

---

## 🔧 Configuraciones Adicionales

### PHP.ini Recomendado

Verificar que PHP tenga configuraciones adecuadas:

```bash
# Editar PHP.ini
sudo nano /etc/php/8.1/apache2/php.ini

# Buscar y modificar:
upload_max_filesize = 20M
post_max_size = 20M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

# Guardar y reiniciar Apache
sudo systemctl restart apache2
```

### Firewall

Si usas UFW:

```bash
# Permitir HTTP y HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Verificar
sudo ufw status
```

### Configurar Cron para Tareas Periódicas

Si necesitas tareas programadas (por ejemplo, enviar recordatorios):

```bash
# Editar crontab
crontab -e

# Agregar tarea (ejemplo: enviar recordatorios diarios a las 9 AM)
0 9 * * * /usr/bin/php /var/www/html/quibuv4/cron/enviar_recordatorios.php >> /var/www/html/quibuv4/logs/cron.log 2>&1
```

---

## ✅ Checklist de Instalación

Marca cada ítem cuando lo completes:

- [ ] Servidor accesible vía SSH
- [ ] PHP 7.4+ instalado
- [ ] MySQL/MariaDB instalado
- [ ] Composer instalado
- [ ] Archivos de Quibu V4 copiados
- [ ] Dependencias instaladas (`composer install`)
- [ ] Archivo `.env` configurado correctamente
- [ ] Base de datos creada
- [ ] Schema de BD importado
- [ ] Permisos de archivos configurados (755/644)
- [ ] Apache configurado (VirtualHost o .htaccess)
- [ ] Módulos de Apache habilitados (rewrite, headers)
- [ ] Apache reiniciado
- [ ] SSL/HTTPS configurado (Certbot)
- [ ] Credenciales de Mercado Pago configuradas
- [ ] Webhooks de MP configurados
- [ ] Credenciales de Transbank configuradas
- [ ] Probado en navegador (landing page funciona)
- [ ] Probado flujo de pago completo
- [ ] Logs revisados (sin errores)
- [ ] Backup de versión anterior hecho

---

## 🐛 Troubleshooting

### Error: "Can't connect to database"

```bash
# Verificar que MySQL esté corriendo
sudo systemctl status mysql

# Verificar credenciales en .env
cat /var/www/html/quibuv4/.env | grep DB_

# Probar conexión manualmente
mysql -u tu_usuario -p -e "SHOW DATABASES;"
```

### Error: "Class not found" o problemas con vendor

```bash
# Reinstalar dependencias
cd /var/www/html/quibuv4
rm -rf vendor
composer install --no-dev --optimize-autoloader

# Verificar autoload
composer dump-autoload
```

### Error 500 en páginas PHP

```bash
# Ver logs de error
sudo tail -50 /var/log/apache2/error.log

# Verificar permisos
ls -la /var/www/html/quibuv4/

# Verificar que .htaccess está presente
ls -la /var/www/html/quibuv4/pagar/.htaccess
```

### Página en blanco o sin estilos

```bash
# Verificar que assets están accesibles
curl https://www.quibu.cl/quibuv4/pagar/assets/css/style.css

# Verificar permisos de assets
ls -la /var/www/html/quibuv4/pagar/assets/
```

### Webhook de Mercado Pago no llega

```bash
# Verificar que la URL es accesible públicamente
curl https://www.quibu.cl/quibuv4/api/mp-webhook.php

# Ver logs del webhook
sudo tail -f /var/log/apache2/error.log | grep webhook
```

---

## 📊 Verificación Final

### Test de Funcionalidades

1. **Landing Page**: Debería cargar correctamente
2. **Búsqueda de RUT**: Debería buscar en BD
3. **Registro de pagador**: Debería guardar en BD
4. **Selección de cuotas**: Checkboxes deberían calcular total
5. **Selector de método de pago**: MP y Transbank visibles
6. **Integración MP**: Redirect a checkout de MP
7. **Integración Transbank**: Redirect a WebPay
8. **Confirmación**: Pago debería registrarse en BD
9. **Estadísticas**: JSON debería retornar correctamente

### Monitoreo Post-Instalación

```bash
# Monitorear logs en tiempo real
sudo tail -f /var/log/apache2/quibuv4-error.log

# Ver uso de recursos
htop

# Verificar conexiones MySQL
sudo mysqladmin -u root -p processlist
```

---

## 🚀 Pasar a Producción

Cuando estés listo para producción:

1. **Actualizar .env**:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   MP_MODE=production
   TRANSBANK_MODE=production
   ```

2. **Actualizar credenciales de producción**:
   - Mercado Pago: Tokens de producción
   - Transbank: Credenciales de producción

3. **Optimizar Composer**:
   ```bash
   composer install --no-dev --optimize-autoloader --classmap-authoritative
   ```

4. **Configurar caché de Apache**:
   ```bash
   sudo a2enmod cache
   sudo a2enmod cache_disk
   sudo systemctl restart apache2
   ```

---

## 📞 Soporte

Si encuentras problemas:

1. **Revisar logs**: `/var/log/apache2/error.log`
2. **Verificar .env**: Credenciales correctas
3. **Probar conexión BD**: MySQL accesible
4. **Verificar permisos**: www-data propietario
5. **Consultar documentación**: `/api/README.md` y `INTEGRACION_MERCADOPAGO.md`

---

**¡Instalación completada!** 🎉

URLs de acceso:
- Landing: `https://www.quibu.cl/quibuv4/pago-landing/`
- Dashboard: `https://www.quibu.cl/quibuv4/dashboard/`
- Pagar: `https://www.quibu.cl/quibuv4/pagar/?grupo=X`
