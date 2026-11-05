#!/usr/bin/env bash
# =====================================================================================
#  Certificados https (Let's Encrypt) para el dominio principal, el panel y cada empresa {RUC}.{dominio}.
#  Lo corre cron cada 5 minutos (lo instala instalar-almalinux.sh). Si una empresa nueva aún no tiene
#  certificado, lo pide por HTTP (webroot, sin tocar el DNS) y crea su sitio https en Apache.
#  Mientras no tenga certificado, la empresa entra por http; apenas lo tiene, Apache la redirige a https.
#  Marcas blancas (revendedores con dominio propio, ej. wilcatsystems.pe): Apache responde por su dominio
#  (tushpa-marcas.conf, se arma solo) y se piden los certificados de su portada, su panel y cada empresa suya.
#  Configuración: /etc/tushpa-ssl.conf  (DIR, PRINCIPAL, CORREO)
#  Corre como root desde /usr/local/sbin/tushpa-ssl (copia con dueño root; la carpeta del proyecto es de Apache).
#  El botón "Activar https" del panel deja storage/app/ssl-solicitud: systemd (tushpa-ssl.path) lo atiende al instante.
# =====================================================================================
set -uo pipefail
exec 9>/run/tushpa-ssl.lock
flock -n 9 || exit 0          # ya hay otra ejecución en curso
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
    # La web pública (tushpa.app y www.tushpa.app) y el panel
    HOSTS+=("${DOMINIO}" "www.${DOMINIO}" "${ADMIN}.${DOMINIO}")
    # Empresas activas de TUSHPA (las de una marca entran por el dominio de su marca): su subdominio propio (demo) o su RUC.
    # Solo letras, números y guiones llegan al shell
    while read -r sub; do
        [[ "${sub}" =~ ^[a-z0-9-]{1,40}$ ]] && HOSTS+=("${sub}.${DOMINIO}")
    done < <(mysql -N -u"${USUARIO_BD}" -h"${HOST_BD}" "${CENTRAL}" -e "SELECT COALESCE(NULLIF(subdominio, ''), ruc) FROM clientes WHERE estado = 'ACTIVO' AND marca_id IS NULL" 2>/dev/null)
fi

# ---- Marcas blancas: su dominio en Apache y sus direcciones para los certificados ----
NOMBRE_HOST='^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$'
MARCAS=()
MARCAS_LEIDAS=0   # 1 = la consulta respondió (si la base falla un momento, no se toca la configuración de Apache)
if [ -n "${DOMINIO}" ] && salida_marcas="$(mysql -N -u"${USUARIO_BD}" -h"${HOST_BD}" "${CENTRAL}" -e "SELECT LOWER(dominio) FROM marcas WHERE activa = 1" 2>/dev/null)"; then
    MARCAS_LEIDAS=1
    while read -r dom; do
        [[ "${dom}" =~ ${NOMBRE_HOST} ]] && [ "${dom}" != "${DOMINIO}" ] && MARCAS+=("${dom}")
    done <<< "${salida_marcas}"
fi
for dom in "${MARCAS[@]}"; do
    HOSTS+=("${dom}" "www.${dom}" "${ADMIN}.${dom}")
done
if [ "${#MARCAS[@]}" -gt 0 ]; then
    # Sus empresas activas: {subdominio o RUC}.{dominio de su marca}
    while read -r h; do
        [[ "${h}" =~ ${NOMBRE_HOST} ]] && HOSTS+=("${h}")
    done < <(mysql -N -u"${USUARIO_BD}" -h"${HOST_BD}" "${CENTRAL}" -e "SELECT LOWER(CONCAT(COALESCE(NULLIF(c.subdominio, ''), c.ruc), '.', m.dominio)) FROM clientes c JOIN marcas m ON m.id = c.marca_id WHERE c.estado = 'ACTIVO' AND m.activa = 1" 2>/dev/null)
fi

# Apache (puerto 80) responde por el dominio de cada marca: sin esto no llegarían a este sistema ni se podría pedir su
# certificado. Se arma de nuevo solo si cambió; si Apache no la acepta, queda la anterior (nunca se deja Apache roto).
CONF_MARCAS="${CONF_MARCAS:-/etc/httpd/conf.d/tushpa-marcas.conf}"
nueva_conf="$(mktemp)"
for dom in "${MARCAS[@]}"; do
    cat >> "${nueva_conf}" <<CONF
<VirtualHost *:80>
    ServerName ${dom}
    ServerAlias www.${dom} *.${dom}
    DocumentRoot ${DIR}/public
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteCond /etc/httpd/conf.d/tushpa-ssl-%{HTTP_HOST}.conf -f
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
    Timeout 600
    ProxyTimeout 600
    ErrorLog /var/log/httpd/tushpa_marcas_error.log
    CustomLog /var/log/httpd/tushpa_marcas_access.log combined
</VirtualHost>
CONF
done
if [ "${MARCAS_LEIDAS}" -eq 1 ] && ! cmp -s "${nueva_conf}" "${CONF_MARCAS}" 2>/dev/null && { [ -s "${nueva_conf}" ] || [ -f "${CONF_MARCAS}" ]; }; then
    anterior="$(mktemp)"
    [ -f "${CONF_MARCAS}" ] && cp "${CONF_MARCAS}" "${anterior}"
    if [ -s "${nueva_conf}" ]; then cp "${nueva_conf}" "${CONF_MARCAS}"; else rm -f "${CONF_MARCAS}"; fi
    if apachectl configtest >/dev/null 2>&1; then
        systemctl reload httpd
        echo "$(date '+%F %T') Apache: dominios de marcas actualizados (${MARCAS[*]:-ninguno})"
    else
        if [ -s "${anterior}" ]; then cp "${anterior}" "${CONF_MARCAS}"; else rm -f "${CONF_MARCAS}"; fi
        echo "$(date '+%F %T') Apache no aceptó la configuración de las marcas: se dejó la anterior"
    fi
    rm -f "${anterior}"
fi
rm -f "${nueva_conf}"

nuevos=0
for host in "${HOSTS[@]}"; do
    # Nombre de host válido (se escribe en la configuración de Apache)
    [[ "${host}" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]] || continue
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

rm -f "${DIR}/storage/app/ssl-solicitud"
