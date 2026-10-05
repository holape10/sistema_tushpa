#!/usr/bin/env bash
# =====================================================================================
#  Instalación de Sistema TUSHPA en un VPS AlmaLinux 8/9 con Apache (Contabo u otro)
#
#  Uso (como root):
#     curl -fsSL https://raw.githubusercontent.com/holape10/sistema_tushpa/main/deploy/instalar-almalinux.sh -o instalar.sh
#     bash instalar.sh                                  # entra por la IP del VPS (http)
#     bash instalar.sh midominio.com correo@gmail.com   # con dominio y SSL gratis (https)
#
#  Qué hace: PHP 8.3 (Remi), MySQL 8, Apache, Composer, Node.js; clona el repositorio en
#  /var/www/html/sistema_tushpa, crea la base de datos, .env, migraciones, compila estilos,
#  configura Apache, SELinux y el firewall. Se puede volver a correr sin romper nada.
# =====================================================================================
set -euo pipefail

APP="${APP:-sistema_tushpa}"
REPO="${REPO:-https://github.com/holape10/sistema_tushpa.git}"
RAMA="${RAMA:-main}"
DOMINIO="${1:-}"
CORREO="${2:-}"
DIR="/var/www/html/${APP}"
DB_NOMBRE="bd_${APP}"
DB_USUARIO="tushpa"
CREDENCIALES="/root/tushpa_credenciales.txt"

verde() { echo -e "\n\033[1;32m==> $*\033[0m"; }
[ "$(id -u)" -eq 0 ] || { echo "Corre este script como root (sudo -i)."; exit 1; }
RHEL=$(rpm -E %rhel)

verde "1/9 Paquetes del sistema"
dnf -y install epel-release
dnf -y install "https://rpms.remirepo.net/enterprise/remi-release-${RHEL}.rpm" || true
dnf -y install git unzip curl tar policycoreutils-python-utils firewalld httpd mod_ssl

verde "2/9 PHP 8.3 con las extensiones que usa el sistema (SUNAT, PDF, Excel, ZIP)"
dnf -y module reset php
dnf -y module enable php:remi-8.3
dnf -y install php php-cli php-fpm php-common php-mysqlnd php-mbstring php-xml php-gd php-zip \
    php-intl php-bcmath php-soap php-opcache php-process php-pdo
cat > /etc/php.d/99-tushpa.ini <<'INI'
; Sistema TUSHPA: subir respaldos del sistema antiguo, imágenes y reportes grandes
upload_max_filesize = 512M
post_max_size = 512M
memory_limit = 512M
max_execution_time = 600
date.timezone = America/Lima
INI

verde "3/9 MySQL 8"
dnf -y install mysql-server
systemctl enable --now mysqld

verde "4/9 Node.js (para compilar los estilos) y Composer"
if ! command -v node >/dev/null || [ "$(node -v | cut -d. -f1 | tr -d v)" -lt 20 ]; then
    dnf -y module reset nodejs || true
    dnf -y module enable nodejs:22 || dnf -y module enable nodejs:20
    dnf -y install nodejs npm
fi
if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
fi

verde "5/9 Código del sistema en ${DIR}"
git config --global --add safe.directory "${DIR}" || true
if [ -d "${DIR}/.git" ]; then
    git -C "${DIR}" fetch origin "${RAMA}"
    git -C "${DIR}" reset --hard "origin/${RAMA}"
else
    git clone -b "${RAMA}" "${REPO}" "${DIR}"
fi
cd "${DIR}"

verde "6/9 Base de datos y .env"
if [ -f "${CREDENCIALES}" ]; then
    # shellcheck disable=SC1090
    source "${CREDENCIALES}"
else
    DB_CLAVE="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)"
    printf 'DB_NOMBRE=%s\nDB_USUARIO=%s\nDB_CLAVE=%s\n' "${DB_NOMBRE}" "${DB_USUARIO}" "${DB_CLAVE}" > "${CREDENCIALES}"
    chmod 600 "${CREDENCIALES}"
fi
# Si MySQL ya tenía clave de root:  MYSQL_PWD='laclave' bash instalar.sh ...
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NOMBRE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${DB_CLAVE}';
ALTER USER '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${DB_CLAVE}';
-- ALL en *.* : el importador crea bases temporales antiguo_* y el multi-empresa crea bd_{RUC}
GRANT ALL PRIVILEGES ON *.* TO '${DB_USUARIO}'@'localhost';
FLUSH PRIVILEGES;
SQL

URL="http://$(curl -fsS -4 https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')"
[ -n "${DOMINIO}" ] && URL="https://${DOMINIO}"
[ -f .env ] || cp .env.example .env
fijar() {  # fijar CLAVE valor  -> reemplaza (o agrega) la línea en .env, aunque esté comentada
    if grep -qE "^#?\s*$1=" .env; then sed -i -E "s|^#?\s*$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
fijar APP_NAME '"Sistema TUSHPA"'
fijar APP_ENV production
fijar APP_DEBUG false
fijar APP_URL "${URL}"
fijar APP_LOCALE es
fijar DB_CONNECTION mysql
fijar DB_HOST 127.0.0.1
fijar DB_PORT 3306
fijar DB_DATABASE "${DB_NOMBRE}"
fijar DB_USERNAME "${DB_USUARIO}"
fijar DB_PASSWORD "${DB_CLAVE}"
fijar LOG_LEVEL warning
chmod 640 .env

verde "7/9 Dependencias, migraciones y estilos"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force
npm ci --no-audit --no-fund
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

verde "8/9 Permisos y SELinux"
mkdir -p public/imagenes/productos storage/app/private storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R apache:apache "${DIR}"
find "${DIR}" -type d -exec chmod 755 {} \;
find "${DIR}" -type f -not -path "*/node_modules/*" -exec chmod 644 {} \;
chmod 640 .env
chmod -R ug+rwX storage bootstrap/cache public/imagenes
if command -v getenforce >/dev/null && [ "$(getenforce)" != "Disabled" ]; then
    # Apache puede escribir en storage, caché e imágenes subidas; y conectarse a SUNAT / consultas RUC / MySQL
    semanage fcontext -a -t httpd_sys_rw_content_t "${DIR}/(storage|bootstrap/cache|public/imagenes)(/.*)?" 2>/dev/null \
        || semanage fcontext -m -t httpd_sys_rw_content_t "${DIR}/(storage|bootstrap/cache|public/imagenes)(/.*)?"
    restorecon -R "${DIR}"
    setsebool -P httpd_can_network_connect 1
    setsebool -P httpd_can_network_connect_db 1
fi

verde "9/9 Apache y firewall"
SERVIDOR="${DOMINIO:-localhost}"   # 00- = primer sitio: también responde al entrar por la IP
cat > "/etc/httpd/conf.d/00-${APP}.conf" <<CONF
# Sistema TUSHPA: solo la carpeta public/ es visible desde internet (nunca .env, vendor ni storage)
<Directory "${DIR}">
    Require all denied
</Directory>
<Directory "${DIR}/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

<VirtualHost *:80>
    ServerName ${SERVIDOR}
    DocumentRoot ${DIR}/public
    # Importar respaldos grandes y reportes pesados
    Timeout 600
    ProxyTimeout 600
    ErrorLog /var/log/httpd/${APP}_error.log
    CustomLog /var/log/httpd/${APP}_access.log combined
</VirtualHost>
CONF
systemctl enable --now php-fpm httpd
systemctl restart php-fpm httpd
systemctl enable --now firewalld
firewall-cmd --permanent --add-service=http --add-service=https >/dev/null
firewall-cmd --reload >/dev/null

if [ -n "${DOMINIO}" ]; then
    verde "SSL gratis con Let's Encrypt para ${DOMINIO} (la cámara y la voz del PV Móvil necesitan https)"
    dnf -y install certbot python3-certbot-apache
    certbot --apache -d "${DOMINIO}" --non-interactive --agree-tos -m "${CORREO:-admin@${DOMINIO}}" --redirect \
        || echo "No se pudo emitir el SSL: revisa que el dominio apunte a la IP de este VPS y vuelve a correr el script."
fi

verde "¡Listo!"
cat <<FIN

  Sistema:        ${URL}
  Primer ingreso: ${URL}/config   (registra tu empresa y el usuario administrador)
  Carpeta:        ${DIR}
  Base de datos:  ${DB_NOMBRE}  ·  usuario ${DB_USUARIO}  ·  clave guardada en ${CREDENCIALES}
  Errores:        ${DIR}/storage/logs  y  /var/log/httpd/${APP}_error.log
  Actualizar:     bash ${DIR}/deploy/actualizar.sh

FIN
