#!/bin/bash

###############################################################################
# Quibu V4 - Instalación Limpia en Raíz HTML con Base de Datos Existente
#
# Este script instala Quibu V4 directamente en /var/www/html CONSERVANDO
# tu base de datos actual con todos los datos de quibuv2/v3.
#
# - Instala en la raíz HTML (no en subdirectorio)
# - Maneja WordPress existente (opción de mover a subdirectorio)
# - Preserva 100% de los datos de la base de datos
# - Crea backup completo antes de cualquier cambio
#
# Uso: sudo bash install-clean-keep-db.sh
###############################################################################

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Verificar root
if [ "$EUID" -ne 0 ]; then
    print_error "Este script debe ejecutarse como root (sudo)"
    exit 1
fi

print_header "QUIBU V4 - INSTALACIÓN LIMPIA (BD EXISTENTE)"

echo ""
print_warning "IMPORTANTE: Este script mantendrá tu base de datos actual"
print_info "Solo instalará los archivos nuevos de Quibu V4"
print_info "Los datos de quibuv2/v3 se conservarán intactos"
echo ""
read -p "¿Deseas continuar? (s/n): " CONFIRM
if [ "$CONFIRM" != "s" ]; then
    print_error "Instalación cancelada"
    exit 1
fi

# Variables
INSTALL_DIR="/var/www/html"
BACKUP_DIR="/home/backup-quibu-$(date +%Y%m%d-%H%M%S)"
WEB_USER="www-data"

# Paso 1: Verificar base de datos existente
print_header "Verificando Base de Datos Existente"

read -p "Usuario MySQL (root): " MYSQL_USER
MYSQL_USER=${MYSQL_USER:-root}

read -sp "Contraseña MySQL: " MYSQL_PASS
echo ""

read -p "Nombre de la base de datos existente: " DB_NAME

# Verificar conexión
if mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    print_success "Conexión a base de datos '$DB_NAME' exitosa"

    # Verificar tablas existentes
    TABLES=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -e "SHOW TABLES" | wc -l)
    if [ "$TABLES" -gt 1 ]; then
        print_success "Base de datos contiene $((TABLES-1)) tablas"

        # Mostrar tablas
        echo ""
        print_info "Tablas existentes:"
        mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -e "SHOW TABLES"
        echo ""
    else
        print_warning "La base de datos está vacía"
    fi
else
    print_error "No se pudo conectar a la base de datos '$DB_NAME'"
    exit 1
fi

# Paso 2: Backup de archivos actuales (si existen)
print_header "Creando Backup de Archivos Actuales"

mkdir -p "$BACKUP_DIR"

# Backup completo de la raíz HTML actual
print_info "Backing up TODO el contenido de /var/www/html..."
if [ "$(ls -A $INSTALL_DIR)" ]; then
    tar -czf "$BACKUP_DIR/html-root-complete.tar.gz" -C /var/www html 2>/dev/null || true
    print_success "Backup de archivos creado"
else
    print_info "La raíz HTML está vacía, no hay nada que respaldar"
fi

# Backup de base de datos
print_info "Backing up base de datos..."
mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" > "$BACKUP_DIR/database.sql"
gzip "$BACKUP_DIR/database.sql"

print_success "Backup completo guardado en: $BACKUP_DIR"

# Paso 3: Limpiar instalaciones anteriores
print_header "Preparando Raíz HTML para Instalación"

echo ""
print_warning "IMPORTANTE: Quibu V4 se instalará directamente en /var/www/html"
print_info "Se ha creado un backup completo en: $BACKUP_DIR"
echo ""

# Verificar si hay WordPress
if [ -f "$INSTALL_DIR/wp-config.php" ]; then
    print_warning "Se detectó una instalación de WordPress en la raíz"
    echo ""
    echo "Opciones:"
    echo "  1) Mover WordPress a /var/www/html/wordpress (subdirectorio)"
    echo "  2) Mantener WordPress y mezclar con Quibu V4 (no recomendado)"
    echo "  3) Eliminar WordPress completamente"
    echo "  4) Cancelar instalación"
    echo ""
    read -p "Selecciona una opción (1-4): " WP_OPTION

    case $WP_OPTION in
        1)
            mkdir -p "$INSTALL_DIR/wordpress"
            print_info "Moviendo WordPress a /var/www/html/wordpress..."
            # Mover todos los archivos de WordPress
            for item in wp-* xmlrpc.php license.txt readme.html wp-includes wp-content wp-admin index.php; do
                if [ -e "$INSTALL_DIR/$item" ]; then
                    mv "$INSTALL_DIR/$item" "$INSTALL_DIR/wordpress/" 2>/dev/null || true
                fi
            done
            print_success "WordPress movido a subdirectorio"
            ;;
        2)
            print_warning "Se mantendrá WordPress en la raíz y se mezclarán los archivos"
            print_warning "Esto puede causar conflictos. Asegúrate de probar todo después."
            ;;
        3)
            print_warning "Eliminando WordPress..."
            rm -rf "$INSTALL_DIR"/wp-*
            rm -f "$INSTALL_DIR/xmlrpc.php" "$INSTALL_DIR/license.txt" "$INSTALL_DIR/readme.html"
            print_success "WordPress eliminado"
            ;;
        4)
            print_error "Instalación cancelada por el usuario"
            exit 1
            ;;
        *)
            print_error "Opción inválida"
            exit 1
            ;;
    esac
fi

# Limpiar versiones antiguas de Quibu
echo ""
if [ -d "$INSTALL_DIR/quibuv2" ] || [ -d "$INSTALL_DIR/quibuv3_old" ]; then
    print_warning "Se detectaron versiones antiguas de Quibu"
    read -p "¿Deseas eliminar quibuv2 y quibuv3_old? (s/n): " REMOVE_OLD
    if [ "$REMOVE_OLD" == "s" ]; then
        rm -rf "$INSTALL_DIR/quibuv2"
        rm -rf "$INSTALL_DIR/quibuv3_old"
        print_success "Versiones antiguas eliminadas"
    fi
fi

# Eliminar carpetas específicas de Quibu V4 existente para instalar limpio
print_info "Limpiando carpetas de Quibu V4 existentes..."
for DIR in api dashboard pagar pago-landing vendor; do
    if [ -d "$INSTALL_DIR/$DIR" ]; then
        rm -rf "$INSTALL_DIR/$DIR"
    fi
done

# Eliminar archivos específicos de Quibu V4
for FILE in composer.json composer.lock .env .env.example .gitignore; do
    if [ -f "$INSTALL_DIR/$FILE" ]; then
        rm -f "$INSTALL_DIR/$FILE"
    fi
done

print_success "Raíz HTML preparada para instalación"

# Paso 4: Instalar archivos de Quibu V4
print_header "Instalando Archivos de Quibu V4"

# Verificar que estamos en el repo
if [ ! -d ".git" ]; then
    print_error "Debes ejecutar este script desde la raíz del repositorio quibuv4"
    exit 1
fi

# Asegurar que el directorio existe
mkdir -p "$INSTALL_DIR"

# Copiar archivos directamente a la raíz (excluyendo git y node_modules)
print_info "Copiando archivos a /var/www/html..."
rsync -av --exclude='.git' --exclude='node_modules' --exclude='backup-*' --exclude='wordpress' ./ "$INSTALL_DIR/"
print_success "Archivos de Quibu V4 instalados en la raíz HTML"

# Paso 5: Instalar dependencias
print_header "Instalando Dependencias de Composer"

cd "$INSTALL_DIR"

if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader
    print_success "Dependencias instaladas"
else
    print_error "Composer no está instalado"
    exit 1
fi

# Paso 6: Configurar .env
print_header "Configurando Variables de Entorno"

if [ ! -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"

    # Auto-configurar con datos de BD existente
    sed -i "s/DB_HOST=localhost/DB_HOST=localhost/" "$INSTALL_DIR/.env"
    sed -i "s/DB_NAME=quibu_db/DB_NAME=$DB_NAME/" "$INSTALL_DIR/.env"
    sed -i "s/DB_USER=root/DB_USER=$MYSQL_USER/" "$INSTALL_DIR/.env"
    sed -i "s/DB_PASS=/DB_PASS=$MYSQL_PASS/" "$INSTALL_DIR/.env"

    print_success "Archivo .env creado y configurado con tu BD existente"

    echo ""
    print_warning "IMPORTANTE: Debes configurar las credenciales de Mercado Pago"
    print_info "Editar: $INSTALL_DIR/.env"
    echo ""
    read -p "¿Deseas editar el .env ahora? (s/n): " EDIT_ENV
    if [ "$EDIT_ENV" == "s" ]; then
        nano "$INSTALL_DIR/.env"
    fi
else
    print_info ".env ya existe, no se sobrescribe"
fi

# Paso 7: Verificar y actualizar schema de BD
print_header "Verificando Schema de Base de Datos"

echo ""
print_info "Verificando si las tablas de Quibu V4 existen..."

# Verificar tablas requeridas
REQUIRED_TABLES=("wp_usuarios_app" "wp_grupos_cobranza" "wp_pagadores" "wp_cuotas_definidas" "wp_pagos_cuotas")
MISSING_TABLES=()

for TABLE in "${REQUIRED_TABLES[@]}"; do
    if ! mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -e "DESCRIBE $TABLE" >/dev/null 2>&1; then
        MISSING_TABLES+=("$TABLE")
    fi
done

if [ ${#MISSING_TABLES[@]} -eq 0 ]; then
    print_success "Todas las tablas requeridas existen"
else
    print_warning "Faltan ${#MISSING_TABLES[@]} tablas: ${MISSING_TABLES[*]}"
    read -p "¿Deseas crear las tablas faltantes? (s/n): " CREATE_TABLES
    if [ "$CREATE_TABLES" == "s" ]; then
        mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" < "$INSTALL_DIR/database/schema.sql"
        print_success "Tablas creadas"
    fi
fi

# Verificar columnas de Mercado Pago
print_info "Verificando columnas de Mercado Pago en wp_usuarios_app..."

if ! mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" -e "SHOW COLUMNS FROM wp_usuarios_app LIKE 'mp_access_token'" | grep -q mp_access_token; then
    print_warning "Faltan columnas de Mercado Pago"
    read -p "¿Deseas ejecutar la migración de Mercado Pago? (s/n): " MIGRATE_MP
    if [ "$MIGRATE_MP" == "s" ]; then
        mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" < "$INSTALL_DIR/database/migration_mercadopago.sql"
        print_success "Migración de Mercado Pago ejecutada"
    fi
else
    print_success "Columnas de Mercado Pago ya existen"
fi

# Paso 8: Configurar permisos
print_header "Configurando Permisos"

chown -R "$WEB_USER:$WEB_USER" "$INSTALL_DIR"
find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
chmod 600 "$INSTALL_DIR/.env"

if [ -d "$INSTALL_DIR/logs" ]; then
    chmod 775 "$INSTALL_DIR/logs"
fi

print_success "Permisos configurados"

# Paso 9: Configurar Apache (opcional)
print_header "Configuración de Apache (Opcional)"

read -p "¿Deseas actualizar la configuración de Apache? (s/n): " SETUP_APACHE
if [ "$SETUP_APACHE" == "s" ]; then
    read -p "Dominio (www.quibu.cl): " DOMAIN
    DOMAIN=${DOMAIN:-www.quibu.cl}

    # Buscar archivo de configuración existente
    CONF_FILE="/etc/apache2/sites-available/000-default.conf"
    if [ -f "/etc/apache2/sites-available/$DOMAIN.conf" ]; then
        CONF_FILE="/etc/apache2/sites-available/$DOMAIN.conf"
    fi

    print_info "Configurando $CONF_FILE..."

    cat > "$CONF_FILE" <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN

    DocumentRoot $INSTALL_DIR

    <Directory $INSTALL_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/quibu-error.log
    CustomLog \${APACHE_LOG_DIR}/quibu-access.log combined

    # Proteger archivos sensibles
    <FilesMatch "^\.env$">
        Require all denied
    </FilesMatch>

    <FilesMatch "^composer\.(json|lock)$">
        Require all denied
    </FilesMatch>
</VirtualHost>
EOF

    a2enmod rewrite headers
    apache2ctl configtest && systemctl reload apache2

    print_success "Apache configurado para servir Quibu V4 desde la raíz"
fi

# Paso 10: Resumen final
print_header "INSTALACIÓN COMPLETADA"

echo ""
print_success "Quibu V4 instalado exitosamente manteniendo tu BD existente"
echo ""
print_info "Directorio: $INSTALL_DIR"
print_info "Base de datos: $DB_NAME (datos conservados)"
print_info "Backup guardado en: $BACKUP_DIR"
echo ""

print_info "URLs de acceso (Quibu V4 en la raíz):"
echo "  - Landing: https://www.quibu.cl/pago-landing/"
echo "  - Pagar: https://www.quibu.cl/pagar/?grupo=ID"
echo "  - Dashboard: https://www.quibu.cl/dashboard/"
echo ""

print_warning "TAREAS PENDIENTES:"
echo "  1. Configurar credenciales de Mercado Pago en .env"
echo "  2. Configurar credenciales de Transbank en .env"
echo "  3. Configurar webhooks en Mercado Pago"
echo "  4. Probar el flujo completo"
echo "  5. Verificar que los datos antiguos se muestran correctamente"
echo ""

print_info "Para verificar datos existentes:"
echo "  mysql -u $MYSQL_USER -p $DB_NAME"
echo "  SELECT * FROM wp_grupos_cobranza;"
echo "  SELECT * FROM wp_pagadores LIMIT 10;"
echo ""

print_success "¡Instalación completada! 🚀"
