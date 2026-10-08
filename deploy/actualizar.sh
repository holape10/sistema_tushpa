#!/usr/bin/env bash
# Actualiza Sistema TUSHPA en el VPS con lo último de GitHub (como root):  bash /var/www/html/sistema_tushpa/deploy/actualizar.sh
set -euo pipefail
APP="${APP:-sistema_tushpa}"
RAMA="${RAMA:-main}"
DIR="/var/www/html/${APP}"
cd "${DIR}"
export COMPOSER_ALLOW_SUPERUSER=1

echo "==> Modo mantenimiento"
php artisan down --retry=15 || true
trap 'php artisan up || true' EXIT

echo "==> Código"
git config --global --add safe.directory "${DIR}" || true
git fetch origin "${RAMA}"
git reset --hard "origin/${RAMA}"

echo "==> Dependencias y base de datos"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
# Multi-empresa: base central (planes, subdominios) y bases de cada cliente
if grep -qE '^TENANCY_DOMINIO=.+' .env; then
    php artisan migrate --database=central --path=database/migrations/central --force
    php artisan clientes:migrar || true
fi

echo "==> Estilos"
npm ci --no-audit --no-fund
npm run build

echo "==> Cachés y permisos"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R apache:apache "${DIR}"
chmod -R ug+rwX storage bootstrap/cache public/imagenes
command -v restorecon >/dev/null && restorecon -R "${DIR}" || true
systemctl reload php-fpm httpd
# Tareas programadas de Laravel (limpieza de la cola de impresión a las 4 am, etc.)
if [ ! -f /etc/cron.d/tushpa-schedule ]; then
    echo "* * * * * apache cd ${DIR} && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/tushpa-schedule
    systemctl enable --now crond >/dev/null 2>&1 || true
fi
# El script de certificados corre como root: su copia vive fuera de la carpeta de Apache
[ -f /usr/local/sbin/tushpa-ssl ] && install -o root -g root -m 755 deploy/ssl-clientes.sh /usr/local/sbin/tushpa-ssl

echo "==> Listo: $(git log -1 --format='%h %s')"
