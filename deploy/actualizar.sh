#!/usr/bin/env bash
# Actualiza Sistema TUSHPA en el VPS con lo último de GitHub (como root):  bash /var/www/html/sistema_tushpa/deploy/actualizar.sh
set -euo pipefail
APP="${APP:-sistema_tushpa}"
RAMA="${RAMA:-main}"
DIR="/var/www/html/${APP}"
cd "${DIR}"
export COMPOSER_ALLOW_SUPERUSER=1
# Cuánto demora cada paso (para saber qué hace lenta la actualización)
PASO=$SECONDS
tiempo() { echo "    ($(( SECONDS - PASO )) s)"; PASO=$SECONDS; }

echo "==> Modo mantenimiento"
# Pantalla amable "Estamos actualizando el sistema" (resources/views/errors/503.blade.php), armada antes de tocar el código
php artisan down --retry=15 --render="errors::503" || true
trap 'php artisan up || true' EXIT

echo "==> Código"
git config --global --add safe.directory "${DIR}" || true
git fetch origin "${RAMA}"
git reset --hard "origin/${RAMA}"
tiempo

# Mensajes del sistema (validaciones, login) en español
sed -i 's/^APP_LOCALE=en$/APP_LOCALE=es/; s/^APP_FALLBACK_LOCALE=en$/APP_FALLBACK_LOCALE=es/' .env

echo "==> Dependencias y base de datos"
composer install --no-dev --optimize-autoloader --no-interaction
tiempo
php artisan migrate --force
# Multi-empresa: base central (planes, subdominios) y bases de cada cliente
if grep -qE '^TENANCY_DOMINIO=.+' .env; then
    php artisan migrate --database=central --path=database/migrations/central --force
    php artisan clientes:migrar || true
fi
tiempo

# Claves de los avisos al celular del mozo (solo la primera vez; si ya existen no las toca)
php artisan push:claves || true
# QR de pago pesados → WebP liviano (pantalla de suspendido); los ya optimizados no se tocan
php artisan cobros:optimizar-qr || true

echo "==> Estilos"
# Los paquetes solo se reinstalan si cambió package-lock.json (reinstalar todo cada vez demora minutos)
HUELLA_NPM="$(sha1sum package-lock.json | cut -d' ' -f1)"
if [ -d node_modules ] && [ "$(cat node_modules/.tushpa-lock 2>/dev/null)" = "${HUELLA_NPM}" ]; then
    echo "    paquetes sin cambios"
else
    npm ci --no-audit --no-fund
    echo "${HUELLA_NPM}" > node_modules/.tushpa-lock
fi
npm run build
tiempo

echo "==> Cachés y permisos"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
tiempo
chown -R apache:apache "${DIR}"
chmod -R ug+rwX storage bootstrap/cache public/imagenes
command -v restorecon >/dev/null && restorecon -R "${DIR}" || true
echo "    permisos"; tiempo
# No mostrar a internet las versiones exactas de Apache y PHP (si Apache no acepta el cambio, se quita)
[ -f /etc/httpd/conf.d/00-tushpa-seguridad.conf ] || printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/httpd/conf.d/00-tushpa-seguridad.conf
apachectl configtest >/dev/null 2>&1 || rm -f /etc/httpd/conf.d/00-tushpa-seguridad.conf
[ -d /etc/php.d ] && [ ! -f /etc/php.d/99-tushpa-seguridad.ini ] && echo 'expose_php = Off' > /etc/php.d/99-tushpa-seguridad.ini || true
systemctl reload php-fpm httpd
# Tiempo real (deploy/reverb-instalar.sh): arranca con el código nuevo
systemctl is-enabled --quiet tushpa-reverb 2>/dev/null && systemctl restart tushpa-reverb || true
# Trabajador de colas (deploy/colas-instalar.sh): termina la tarea que tiene en curso y vuelve con el código nuevo.
# queue:restart es suave (no corta una copia a medias); systemd lo levanta de nuevo al salir.
if systemctl is-enabled --quiet tushpa-colas 2>/dev/null; then
    sudo -u apache php artisan queue:restart || true
fi
# Tareas programadas de Laravel (limpieza de la cola de impresión a las 4 am, etc.)
if [ ! -f /etc/cron.d/tushpa-schedule ]; then
    echo "* * * * * apache cd ${DIR} && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/tushpa-schedule
    systemctl enable --now crond >/dev/null 2>&1 || true
fi
# El script de certificados corre como root: su copia vive fuera de la carpeta de Apache
[ -f /usr/local/sbin/tushpa-ssl ] && install -o root -g root -m 755 deploy/ssl-clientes.sh /usr/local/sbin/tushpa-ssl
[ -f /usr/local/sbin/tushpa-respaldos-nube ] && install -o root -g root -m 755 deploy/respaldos-nube.sh /usr/local/sbin/tushpa-respaldos-nube

echo "==> Listo en ${SECONDS} s: $(git log -1 --format='%h %s')"
