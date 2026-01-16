#!/bin/bash

###############################################################################
# Quibu V4 - Script de Deployment Automatizado
#
# Este script facilita la instalación de Quibu V4 en tu servidor
# Uso: sudo bash deploy.sh
###############################################################################

set -e  # Salir si hay errores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funciones de utilidad
print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Verificar que se ejecuta como root
if [ "$EUID" -ne 0 ]; then
    print_error "Este script debe ejecutarse como root (sudo)"
    exit 1
fi

print_header "QUIBU V4 - DEPLOYMENT AUTOMATIZADO"

# Variables
INSTALL_DIR="/var/www/html/quibuv4"
WEB_USER="www-data"
WEB_GROUP="www-data"

# Paso 1: Verificar requisitos
print_header "Verificando Requisitos del Sistema"

# PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    print_success "PHP $PHP_VERSION instalado"
else
    print_error "PHP no está instalado"
    exit 1
fi

# MySQL
if command -v mysql &> /dev/null; then
    MYSQL_VERSION=$(mysql --version | awk '{print $5}' | cut -d "." -f 1,2)
    print_success "MySQL/MariaDB instalado"
else
    print_error "MySQL no está instalado"
    exit 1
fi

# Composer
if command -v composer &> /dev/null; then
    print_success "Composer instalado"
else
    print_warning "Composer no está instalado. Instalando..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    print_success "Composer instalado exitosamente"
fi

# Apache
if systemctl is-active --quiet apache2; then
    print_success "Apache está corriendo"
else
    print_warning "Apache no está corriendo. Iniciando..."
    systemctl start apache2
fi

# Paso 2: Preguntar modo de instalación
print_header "Modo de Instalación"
echo "1) Instalación nueva en subdirectorio (recomendado para testing)"
echo "2) Reemplazar instalación existente (requiere backup)"
read -p "Selecciona una opción (1 o 2): " INSTALL_MODE

if [ "$INSTALL_MODE" != "1" ] && [ "$INSTALL_MODE" != "2" ]; then
    print_error "Opción inválida"
    exit 1
fi

# Paso 3: Backup si es reemplazo
if [ "$INSTALL_MODE" == "2" ]; then
    print_header "Creando Backup"

    BACKUP_DIR="/home/backup-quibu-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    # Backup de archivos
    if [ -d "/var/www/html/api" ]; then
        print_info "Backing up archivos..."
        tar -czf "$BACKUP_DIR/archivos.tar.gz" \
            /var/www/html/api \
            /var/www/html/dashboard \
            /var/www/html/pagar \
            /var/www/html/pago-landing 2>/dev/null || true
        print_success "Backup de archivos creado en $BACKUP_DIR/archivos.tar.gz"
    fi

    # Backup de base de datos
    read -p "¿Hacer backup de base de datos? (s/n): " BACKUP_DB
    if [ "$BACKUP_DB" == "s" ]; then
        read -p "Usuario MySQL: " MYSQL_USER
        read -sp "Contraseña MySQL: " MYSQL_PASS
        echo ""
        read -p "Nombre de base de datos: " DB_NAME

        mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" > "$BACKUP_DIR/database.sql"
        print_success "Backup de base de datos creado en $BACKUP_DIR/database.sql"
    fi
fi

# Paso 4: Crear directorio e instalar archivos
print_header "Instalando Archivos"

if [ "$INSTALL_MODE" == "1" ]; then
    # Instalación en subdirectorio
    if [ -d "$INSTALL_DIR" ]; then
        print_warning "El directorio $INSTALL_DIR ya existe"
        read -p "¿Deseas eliminarlo y continuar? (s/n): " CONFIRM
        if [ "$CONFIRM" != "s" ]; then
            print_error "Instalación cancelada"
            exit 1
        fi
        rm -rf "$INSTALL_DIR"
    fi

    mkdir -p "$INSTALL_DIR"

    # Copiar archivos (asumiendo que estamos en el repo)
    if [ -d ".git" ]; then
        print_info "Copiando archivos desde repositorio local..."
        rsync -av --exclude='.git' --exclude='node_modules' ./ "$INSTALL_DIR/"
    else
        print_error "No se encuentra repositorio Git. Por favor ejecuta desde la raíz del proyecto."
        exit 1
    fi
else
    # Reemplazo en /var/www/html/
    print_info "Reemplazando archivos existentes..."

    # Eliminar carpetas antiguas
    rm -rf /var/www/html/api
    rm -rf /var/www/html/dashboard
    rm -rf /var/www/html/pagar
    rm -rf /var/www/html/pago-landing

    # Copiar nuevos archivos
    cp -r api /var/www/html/
    cp -r dashboard /var/www/html/
    cp -r pagar /var/www/html/
    cp -r pago-landing /var/www/html/
    cp -r vendor /var/www/html/
    cp pago-*.php /var/www/html/
    cp composer.json /var/www/html/

    INSTALL_DIR="/var/www/html"
fi

print_success "Archivos instalados"

# Paso 5: Instalar dependencias
print_header "Instalando Dependencias"
cd "$INSTALL_DIR"
composer install --no-dev --optimize-autoloader
print_success "Dependencias instaladas"

# Paso 6: Configurar .env
print_header "Configurando Variables de Entorno"

if [ ! -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
    print_success "Archivo .env creado"

    echo ""
    print_warning "IMPORTANTE: Debes editar el archivo .env con tus credenciales"
    print_info "Archivo ubicado en: $INSTALL_DIR/.env"
    echo ""
    read -p "¿Deseas editarlo ahora? (s/n): " EDIT_ENV
    if [ "$EDIT_ENV" == "s" ]; then
        nano "$INSTALL_DIR/.env"
    fi
else
    print_info ".env ya existe, no se sobrescribe"
fi

# Paso 7: Configurar permisos
print_header "Configurando Permisos"

chown -R "$WEB_USER:$WEB_GROUP" "$INSTALL_DIR"
find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
find "$INSTALL_DIR" -type f -exec chmod 644 {} \;

# Permisos especiales
if [ -f "$INSTALL_DIR/.env" ]; then
    chmod 600 "$INSTALL_DIR/.env"
fi

if [ -d "$INSTALL_DIR/logs" ]; then
    chmod 775 "$INSTALL_DIR/logs"
fi

print_success "Permisos configurados"

# Paso 8: Configurar base de datos
print_header "Configurando Base de Datos"

read -p "¿Deseas crear/migrar la base de datos ahora? (s/n): " SETUP_DB
if [ "$SETUP_DB" == "s" ]; then
    read -p "Usuario MySQL (root): " MYSQL_USER
    MYSQL_USER=${MYSQL_USER:-root}

    read -sp "Contraseña MySQL: " MYSQL_PASS
    echo ""

    read -p "Nombre de la base de datos (quibu_db): " DB_NAME
    DB_NAME=${DB_NAME:-quibu_db}

    # Crear base de datos
    mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

    if [ $? -eq 0 ]; then
        print_success "Base de datos '$DB_NAME' creada"
    else
        print_error "Error al crear base de datos"
    fi

    # Importar schema
    if [ -f "$INSTALL_DIR/database/schema.sql" ]; then
        read -p "¿Importar schema de base de datos? (s/n): " IMPORT_SCHEMA
        if [ "$IMPORT_SCHEMA" == "s" ]; then
            mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" < "$INSTALL_DIR/database/schema.sql"
            print_success "Schema importado"
        fi
    fi

    # Migración para Mercado Pago
    if [ -f "$INSTALL_DIR/database/migration_mercadopago.sql" ]; then
        read -p "¿Ejecutar migración de Mercado Pago? (s/n): " MIGRATE_MP
        if [ "$MIGRATE_MP" == "s" ]; then
            mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" < "$INSTALL_DIR/database/migration_mercadopago.sql"
            print_success "Migración ejecutada"
        fi
    fi
fi

# Paso 9: Configurar Apache
print_header "Configurando Apache"

read -p "¿Deseas configurar Apache VirtualHost? (s/n): " SETUP_APACHE
if [ "$SETUP_APACHE" == "s" ]; then
    read -p "Nombre del dominio (www.quibu.cl): " DOMAIN
    DOMAIN=${DOMAIN:-www.quibu.cl}

    # Crear VirtualHost
    cat > /etc/apache2/sites-available/quibuv4.conf <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    ServerAlias ${DOMAIN#www.}

    DocumentRoot $INSTALL_DIR

    <Directory $INSTALL_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/quibuv4-error.log
    CustomLog \${APACHE_LOG_DIR}/quibuv4-access.log combined

    <FilesMatch "^\.env$">
        Require all denied
    </FilesMatch>
</VirtualHost>
EOF

    # Habilitar sitio y módulos
    a2ensite quibuv4.conf
    a2enmod rewrite headers ssl

    print_success "VirtualHost configurado"

    # Probar configuración
    apache2ctl configtest

    if [ $? -eq 0 ]; then
        print_success "Configuración de Apache válida"
        systemctl reload apache2
        print_success "Apache reiniciado"
    else
        print_error "Error en configuración de Apache"
    fi
fi

# Paso 10: SSL/HTTPS
print_header "Configurando SSL (Opcional)"

read -p "¿Deseas instalar certificado SSL con Let's Encrypt? (s/n): " SETUP_SSL
if [ "$SETUP_SSL" == "s" ]; then
    if command -v certbot &> /dev/null; then
        print_info "Certbot encontrado. Instalando certificado..."
        certbot --apache -d "$DOMAIN" -d "${DOMAIN#www.}"
        print_success "Certificado SSL instalado"
    else
        print_warning "Certbot no está instalado. Instalando..."
        apt update
        apt install -y certbot python3-certbot-apache
        certbot --apache -d "$DOMAIN" -d "${DOMAIN#www.}"
        print_success "Certificado SSL instalado"
    fi
fi

# Resumen final
print_header "INSTALACIÓN COMPLETADA"

echo ""
print_success "Quibu V4 ha sido instalado exitosamente"
echo ""
print_info "Directorio de instalación: $INSTALL_DIR"
print_info "Usuario web: $WEB_USER"
echo ""

if [ "$INSTALL_MODE" == "1" ]; then
    print_info "URLs de acceso:"
    echo "  - Landing: https://$DOMAIN/quibuv4/pago-landing/"
    echo "  - Pagar: https://$DOMAIN/quibuv4/pagar/?grupo=X"
    echo "  - Dashboard: https://$DOMAIN/quibuv4/dashboard/"
else
    print_info "URLs de acceso:"
    echo "  - Landing: https://$DOMAIN/pago-landing/"
    echo "  - Pagar: https://$DOMAIN/pagar/?grupo=X"
    echo "  - Dashboard: https://$DOMAIN/dashboard/"
fi

echo ""
print_warning "TAREAS PENDIENTES:"
echo "  1. Editar $INSTALL_DIR/.env con tus credenciales"
echo "  2. Configurar webhooks en Mercado Pago"
echo "  3. Probar el flujo completo de pago"
echo "  4. Verificar logs: tail -f /var/log/apache2/quibuv4-error.log"
echo ""

print_success "¡Deployment completado! 🚀"
