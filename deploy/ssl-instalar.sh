#!/usr/bin/env bash
# =====================================================================================
#  https automático y botón "Activar https" del panel (correr UNA vez como root):
#     bash /var/www/html/sistema_tushpa/deploy/ssl-instalar.sh holapesac@gmail.com
#  - Copia deploy/ssl-clientes.sh a /usr/local/sbin/tushpa-ssl con dueño root (lo que corre como root no
#    debe vivir en una carpeta que Apache puede modificar).
#  - systemd tushpa-ssl.path: cuando el panel deja storage/app/ssl-solicitud, pide el certificado al instante.
#  - cron cada minuto como respaldo (empresas nuevas aunque nadie toque el botón).
# =====================================================================================
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Corre este script como root."; exit 1; }
DIR="$(cd "$(dirname "$0")/.." && pwd)"
CORREO="${1:-}"

command -v certbot >/dev/null || dnf -y install certbot

if [ ! -f /etc/tushpa-ssl.conf ]; then
    [ -n "${CORREO}" ] || { echo "Indica tu correo: bash $0 tu@correo.com"; exit 1; }
    PRINCIPAL="$(grep -E '^APP_URL=' "${DIR}/.env" | cut -d= -f2- | tr -d '"' | sed -E 's#^https?://##; s#/.*##')"
    printf 'DIR=%s\nPRINCIPAL=%s\nCORREO=%s\n' "${DIR}" "${PRINCIPAL}" "${CORREO}" > /etc/tushpa-ssl.conf
fi
chmod 600 /etc/tushpa-ssl.conf

install -o root -g root -m 755 "${DIR}/deploy/ssl-clientes.sh" /usr/local/sbin/tushpa-ssl

cat > /etc/systemd/system/tushpa-ssl.service <<UNIT
[Unit]
Description=TUSHPA: certificados https de las empresas
[Service]
Type=oneshot
ExecStart=/bin/bash /usr/local/sbin/tushpa-ssl
UNIT
cat > /etc/systemd/system/tushpa-ssl.path <<UNIT
[Unit]
Description=TUSHPA: boton Activar https del panel
[Path]
PathExists=${DIR}/storage/app/ssl-solicitud
Unit=tushpa-ssl.service
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now tushpa-ssl.path

echo "* * * * * root /bin/bash /usr/local/sbin/tushpa-ssl >> /var/log/tushpa-ssl.log 2>&1" > /etc/cron.d/tushpa-ssl
systemctl enable --now crond >/dev/null 2>&1 || true
systemctl enable --now certbot-renew.timer >/dev/null 2>&1 || true

/bin/bash /usr/local/sbin/tushpa-ssl && echo "Listo: https automático y botón del panel activos." || echo "Revisa /var/log/tushpa-ssl.log"
