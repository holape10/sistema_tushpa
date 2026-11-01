#!/usr/bin/env bash
# =====================================================================================
#  Copias fuera de Contabo: cada noche sube a Google Drive los respaldos de todas las empresas
#  (los que el sistema saca solo a las 3 am en storage/app/respaldos). Si el VPS se pierde, los datos siguen en Drive.
#
#  Instalar (UNA vez, como root, después de conectar Google Drive con "rclone config" usando el nombre gdrive):
#     bash /var/www/html/sistema_tushpa/deploy/respaldos-nube.sh instalar
#  Subir ahora mismo (para probar):
#     bash /var/www/html/sistema_tushpa/deploy/respaldos-nube.sh
#  - En Drive quedan los últimos DIAS días (por defecto 14); lo más viejo se borra solo.
#  - Si la subida falla, avisa por Telegram (el mismo bot del monitor de errores).
# =====================================================================================
set -euo pipefail
DIR="${TUSHPA_DIR:-/var/www/html/sistema_tushpa}"
REMOTO="${REMOTO:-gdrive:TUSHPA-respaldos}"
DIAS="${DIAS:-14}"
ORIGEN="${DIR}/storage/app/respaldos"

valor() { { grep -E "^$1=" "${DIR}/.env" || true; } | tail -1 | cut -d= -f2- | tr -d '"'; }
avisar() {
    local token chat
    token="$(valor TELEGRAM_BOT_TOKEN)"; chat="$(valor TELEGRAM_CHAT_ID)"
    [ -n "${token}" ] && [ -n "${chat}" ] && curl -s -m 20 "https://api.telegram.org/bot${token}/sendMessage" \
        --data-urlencode "chat_id=${chat}" --data-urlencode "text=$1" >/dev/null || true
}

if [ "${1:-}" = "instalar" ]; then
    [ "$(id -u)" -eq 0 ] || { echo "Corre este script como root."; exit 1; }
    command -v rclone >/dev/null || dnf -y install rclone
    rclone listremotes | grep -qx "${REMOTO%%:*}:" \
        || { echo "Falta conectar Google Drive: corre  rclone config  y créalo con el nombre ${REMOTO%%:*}"; exit 1; }
    # Lo que corre como root vive fuera de la carpeta de Apache (igual que el script de certificados)
    install -o root -g root -m 755 "${DIR}/deploy/respaldos-nube.sh" /usr/local/sbin/tushpa-respaldos-nube
    echo "30 4 * * * root /bin/bash /usr/local/sbin/tushpa-respaldos-nube >> /var/log/tushpa-respaldos-nube.log 2>&1" > /etc/cron.d/tushpa-respaldos-nube
    systemctl enable --now crond >/dev/null 2>&1 || true
    echo "==> Primera subida (puede tardar unos minutos)"
    /bin/bash /usr/local/sbin/tushpa-respaldos-nube && echo "Listo: cada noche a las 4:30 am se suben los respaldos a Google Drive."
    exit 0
fi

[ -d "${ORIGEN}" ] || { echo "No existe ${ORIGEN}"; exit 1; }
echo "$(date '+%F %T') subiendo respaldos a ${REMOTO}"
# Solo lo de los últimos 2 días (lo anterior ya está en Drive); --transfers 4 = 4 archivos a la vez
if ! rclone copy "${ORIGEN}" "${REMOTO}" --max-age 2d --transfers 4 --retries 5 --low-level-retries 20; then
    avisar "🚨 TUSHPA: no se pudieron subir los respaldos a Google Drive ($(hostname), $(date '+%F %T')). Revisa /var/log/tushpa-respaldos-nube.log"
    exit 1
fi
rclone delete "${REMOTO}" --min-age "${DIAS}d" || true
echo "$(date '+%F %T') listo: $(rclone size "${REMOTO}" 2>/dev/null | tail -1)"
