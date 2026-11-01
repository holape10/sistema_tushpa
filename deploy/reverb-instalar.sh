#!/usr/bin/env bash
# =====================================================================================
#  Tiempo real (Laravel Reverb): mesas, cocina y avisos del QR al instante (correr UNA vez como root):
#     bash /var/www/html/sistema_tushpa/deploy/reverb-instalar.sh
#  - Claves de Reverb en el .env (si faltan) y el sistema avisando por Reverb.
#  - Servicio systemd tushpa-reverb: escucha solo en 127.0.0.1:8080 (no se abre ningún puerto nuevo).
#  - Apache pasa /app (websocket) y /apps a Reverb en todos los dominios y subdominios, con http o https.
#  No interrumpe a nadie: no recompila estilos ni reinicia Apache (solo lo recarga, sin cortar conexiones).
#  Si Reverb se cae, las pantallas siguen actualizándose solas cada pocos segundos, como antes.
# =====================================================================================
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Corre este script como root."; exit 1; }
DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "${DIR}"

# Si el .env no termina en salto de línea, lo agregado se pegaba a la última línea: primero se asegura el salto
poner() { [ -n "$(tail -c1 .env)" ] && echo >> .env; if grep -qE "^#?\s*$1=" .env; then sed -i -E "s|^#?\s*$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi; }
tiene() { grep -qE "^$1=.+" .env; }

echo "==> Claves de Reverb"
tiene REVERB_APP_ID || poner REVERB_APP_ID "$(shuf -i 100000-999999 -n 1)"
tiene REVERB_APP_KEY || poner REVERB_APP_KEY "$(openssl rand -hex 16)"
tiene REVERB_APP_SECRET || poner REVERB_APP_SECRET "$(openssl rand -hex 24)"
# Laravel le habla a Reverb por dentro del VPS
poner REVERB_HOST 127.0.0.1
poner REVERB_PORT 8080
poner REVERB_SCHEME http
poner REVERB_SERVER_HOST 127.0.0.1
poner REVERB_SERVER_PORT 8080
# El navegador entra por el mismo dominio de la empresa (vacío = el de la página, con su http o https).
# La clave pública la pone cada página: no hace falta recompilar los estilos.
poner VITE_REVERB_HOST '""'
poner VITE_REVERB_PORT '""'
poner VITE_REVERB_SCHEME '""'

echo "==> Servicio tushpa-reverb"
cat > /etc/systemd/system/tushpa-reverb.service <<UNIT
[Unit]
Description=TUSHPA: tiempo real (Laravel Reverb)
After=network.target mysqld.service
[Service]
User=apache
Group=apache
WorkingDirectory=${DIR}
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=3
LimitNOFILE=10000
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable tushpa-reverb >/dev/null

echo "==> Apache"
# Fuera de los VirtualHost: lo heredan todos (dominio principal, subdominios y los https de cada empresa)
cat > /etc/httpd/conf.d/tushpa-reverb.conf <<CONF
# Tiempo real de TUSHPA: el websocket entra por /app y Laravel publica por /apps, ambos hacia Reverb local
ProxyPass /app ws://127.0.0.1:8080/app upgrade=websocket
ProxyPassReverse /app ws://127.0.0.1:8080/app
ProxyPass /apps http://127.0.0.1:8080/apps
ProxyPassReverse /apps http://127.0.0.1:8080/apps
CONF
httpd -M 2>/dev/null | grep -q proxy_wstunnel_module || echo "LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so" > /etc/httpd/conf.modules.d/00-tushpa-wstunnel.conf
command -v setsebool >/dev/null && setsebool -P httpd_can_network_connect 1 || true

echo "==> Configuración (sin modo mantenimiento: nadie deja de trabajar)"
# Se revisa ANTES de activar: si falta alguna clave, el sistema sigue como estaba (sin tiempo real)
for v in REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET; do
    tiene "$v" || { echo "Falta $v en el .env: no se activó el tiempo real (el sistema sigue igual)."; exit 1; }
done
poner BROADCAST_CONNECTION reverb
if ! php artisan config:cache; then
    poner BROADCAST_CONNECTION log
    php artisan config:cache
    echo "No se pudo activar el tiempo real: se dejó como estaba."
    exit 1
fi
chown apache:apache .env bootstrap/cache/config.php

# Si Apache no acepta la configuración nueva se quita: nunca se recarga Apache con un error
apachectl configtest || { rm -f /etc/httpd/conf.d/tushpa-reverb.conf /etc/httpd/conf.modules.d/00-tushpa-wstunnel.conf; echo "Apache no aceptó la configuración: no se cambió nada."; exit 1; }
systemctl restart tushpa-reverb
systemctl reload httpd
sleep 2
systemctl is-active --quiet tushpa-reverb && echo "Listo: tiempo real activo." || { echo "Reverb no arrancó:"; journalctl -u tushpa-reverb -n 20 --no-pager; }
