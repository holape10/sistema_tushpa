#!/usr/bin/env bash
# =====================================================================================
#  Certificados https (Let's Encrypt) para el dominio principal, el panel y cada empresa {RUC}.{dominio}.
#  Lo corre cron cada 5 minutos (lo instala instalar-almalinux.sh). Si una empresa nueva aún no tiene
#  certificado, lo pide por HTTP (webroot, sin tocar el DNS) y crea su sitio https en Apache.
#  Mientras no tenga certificado, la empresa entra por http; apenas lo tiene, Apache la redirige a https.
#  Configuración: /etc/tushpa-ssl.conf  (DIR, PRINCIPAL, CORREO)
# =====================================================================================
set -uo pipefail
source /etc/tushpa-ssl.conf
cd "${DIR}" || exit 1

env_de() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"'; }
DOMINIO="$(env_de TENANCY_DOMINIO)"
ADMIN="$(env_de TENANCY_ADMIN_SUBDOMINIO)"; ADMIN="${ADMIN:-admin}"
CENTRAL="$(env_de CENTRAL_DB_DATABASE)"; CENTRAL="${CENTRAL:-bd_tushpa_central}"
export MYSQL_PWD="$(env_de DB_PASSWORD)"
USUARIO_BD="$(env_de DB_USERNAME)"
HOST_BD="$(env_de DB_HOST)"; HOST_BD="${HOST_BD:-localhost}"   # localhost = socket (root suele existir solo así)

HOSTS=("${PRINCIPAL}")
if [ -n "${DOMINIO}" ]; then
    HOSTS+=("${ADMIN}.${DOMINIO}")
    # Empresas activas del multi-empresa (solo RUC de 11 dígitos: nada raro llega al shell)
    while read -r ruc; do
        [[ "${ruc}" =~ ^[0-9]{11}$ ]] && HOSTS+=("${ruc}.${DOMINIO}")
    done < <(mysql -N -u"${USUARIO_BD}" -h"${HOST_BD}" "${CENTRAL}" -e "SELECT ruc FROM clientes WHERE estado = 'ACTIVO'" 2>/dev/null)
fi

nuevos=0
for host in "${HOSTS[@]}"; do
    [ -z "${host}" ] && continue
    conf="/etc/httpd/conf.d/tushpa-ssl-${host}.conf"
    if [ ! -f "/etc/letsencrypt/live/${host}/fullchain.pem" ]; then
        certbot certonly --webroot -w "${DIR}/public" -d "${host}" --non-interactive --agree-tos -m "${CORREO}" \
            --deploy-hook "systemctl reload httpd" >/dev/null 2>&1 \
            || { echo "$(date '+%F %T') sin certificado para ${host} (¿el DNS ya apunta a este VPS?)"; continue; }
        echo "$(date '+%F %T') certificado nuevo: ${host}"
    fi
    if [ ! -f "${conf}" ]; then
        cat > "${conf}" <<CONF
<VirtualHost *:443>
    ServerName ${host}
    DocumentRoot ${DIR}/public
    Timeout 600
    ProxyTimeout 600
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${host}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${host}/privkey.pem
    ErrorLog /var/log/httpd/tushpa_ssl_error.log
    CustomLog /var/log/httpd/tushpa_ssl_access.log combined
</VirtualHost>
CONF
        nuevos=1
    fi
done

if [ "${nuevos}" -eq 1 ]; then
    apachectl configtest >/dev/null 2>&1 && systemctl reload httpd
fi
