#!/usr/bin/env bash
# =====================================================================================
#  Trabajador de colas: tareas pesadas en segundo plano (copia de seguridad, ZIP de comprobantes…).
#  Correr UNA vez como root:   bash /var/www/html/sistema_tushpa/deploy/colas-instalar.sh
#  - Crea la cola en la base central (si falta).
#  - Servicio systemd tushpa-colas: un trabajador que atiende a todas las empresas, como el usuario apache.
#    Se reinicia solo si se cae y cada hora (para no acumular memoria). Las tareas no se repiten solas si fallan.
#  - Al terminar manda una tarea de prueba y confirma que el trabajador la atiende.
#  No interrumpe a nadie. Si el trabajador se detiene, el sistema hace las tareas en el momento, como antes.
#  actualizar.sh lo reinicia con suavidad (termina la tarea que tiene en curso) cada vez que actualizas.
#  Para quitarlo:  systemctl disable --now tushpa-colas
# =====================================================================================
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Corre este script como root."; exit 1; }
DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "${DIR}"
PHP="$(command -v php)"

grep -qE '^TENANCY_DOMINIO=.+' .env || { echo "Este sistema no es multi-empresa (TENANCY_DOMINIO vacío): la cola necesita la base central."; exit 1; }

echo "==> Cola en la base central"
sudo -u apache "${PHP}" artisan migrate --database=central --path=database/migrations/central --force
# Por si el .env tenía QUEUE_CONNECTION con otro valor: la cola es la de base de datos
if grep -qE '^QUEUE_CONNECTION=' .env; then
    sed -i -E 's|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|' .env
else
    [ -n "$(tail -c1 .env)" ] && echo >> .env
    echo 'QUEUE_CONNECTION=database' >> .env
fi
sudo -u apache "${PHP}" artisan config:cache >/dev/null

echo "==> Servicio tushpa-colas"
cat > /etc/systemd/system/tushpa-colas.service <<UNIT
[Unit]
Description=TUSHPA: trabajador de colas (tareas en segundo plano)
After=network.target mysqld.service mariadb.service
[Service]
User=apache
Group=apache
WorkingDirectory=${DIR}
# --tries=1: una tarea que falla no se repite sola · --max-time: se renueva cada hora · --timeout: máximo por tarea
ExecStart=${PHP} artisan queue:work database --queue=default --sleep=3 --tries=1 --timeout=3500 --max-time=3600 --memory=512
Restart=always
RestartSec=5
# Al detenerlo se le da tiempo de terminar la tarea en curso (una copia grande demora)
TimeoutStopSec=3600
KillSignal=SIGTERM
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable tushpa-colas >/dev/null
systemctl restart tushpa-colas
sleep 5

echo "==> Prueba"
systemctl --no-pager --lines=0 status tushpa-colas || true
if sudo -u apache "${PHP}" artisan colas:probar; then
    echo ""
    echo "Listo: el trabajador de colas está atendiendo. Revisa cuando quieras con:"
    echo "    sudo -u apache php ${DIR}/artisan colas:estado"
else
    echo ""
    echo "El trabajador no respondió. Mira el detalle con:  journalctl -u tushpa-colas -n 50 --no-pager"
    echo "(Mientras tanto el sistema sigue funcionando igual: hace las tareas en el momento.)"
    exit 1
fi
