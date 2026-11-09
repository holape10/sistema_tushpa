#!/usr/bin/env bash
# Ajusta MySQL, PHP-FPM y OPcache a la memoria del VPS (como root, mejor de noche: MySQL se reinicia unos segundos).
#   bash /var/www/html/sistema_tushpa/deploy/optimizar-servidor.sh
# Se puede correr varias veces (si cambias de plan de VPS, vuelve a correrlo y se recalcula). Deja copia de lo que cambia.
set -euo pipefail
verde() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }

RAM_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
CPUS=$(nproc)
echo "VPS: ${RAM_MB} MB de RAM y ${CPUS} CPU"
if [ "${RAM_MB}" -lt 1800 ]; then
    echo "Con menos de 2 GB de RAM no conviene ajustar nada: sube el plan del VPS primero."; exit 1
fi

# Reparto de la memoria: ~40% para los datos de MySQL en memoria, ~35% para PHP, el resto para el sistema, Node (estilos) y Reverb
POOL_MB=$(( RAM_MB * 40 / 100 / 128 * 128 ))
PHP_MB=$(( RAM_MB * 35 / 100 ))
HIJOS=$(( PHP_MB / 90 ))          # un proceso de PHP del sistema usa ~60-90 MB en promedio
[ "${HIJOS}" -lt 8 ] && HIJOS=8
[ "${HIJOS}" -gt 120 ] && HIJOS=120

verde "1/3 MySQL (${POOL_MB} MB para tener las tablas en memoria)"
CNF=/etc/my.cnf.d/99-tushpa.cnf
ANTERIOR=""
if [ -f "${CNF}" ]; then ANTERIOR="${CNF}.$(date +%Y%m%d%H%M).bak"; cp "${CNF}" "${ANTERIOR}"; fi
# Si MySQL no acepta el ajuste o no arranca, se vuelve a lo de antes y se levanta como estaba (nunca queda apagado)
volver_mysql() {
    echo "MySQL no aceptó el ajuste: se deja como estaba."
    if [ -n "${ANTERIOR}" ]; then cp "${ANTERIOR}" "${CNF}"; else rm -f "${CNF}"; fi
    systemctl restart mysqld || systemctl start mysqld
    exit 1
}
cat > "${CNF}" <<CNF
# Sistema TUSHPA (deploy/optimizar-servidor.sh) — calculado para ${RAM_MB} MB de RAM
[mysqld]
# Lo más importante: cuánto de las tablas e índices cabe en memoria (de fábrica son solo 128 MB)
innodb_buffer_pool_size = ${POOL_MB}M
innodb_buffer_pool_instances = $(( POOL_MB >= 2048 ? 4 : 1 ))
# Registro de cambios más grande: menos escrituras a disco con muchas ventas a la vez
innodb_redo_log_capacity = 1G
innodb_flush_method = O_DIRECT
# Cada empresa tiene su base (~200 tablas cada una): que no se cierren y reabran tablas todo el tiempo
table_open_cache = 8000
table_definition_cache = 12000
open_files_limit = 65535
max_connections = $(( HIJOS * 2 + 50 ))
# Historial de cambios (binary logs): de fábrica guarda 30 días y llena el disco (~1 GB por día con el padrón de RUC
# de cada noche). Sin réplicas no hace falta tanto: 3 días. Al reiniciar, MySQL borra solo los que pasaron ese plazo.
binlog_expire_logs_seconds = 259200
# Consultas que demoran más de 1 segundo quedan anotadas para saber qué optimizar
slow_query_log = 1
slow_query_log_file = /var/log/mysql-lentas.log
long_query_time = 1
CNF
touch /var/log/mysql-lentas.log && chown mysql:mysql /var/log/mysql-lentas.log
# Algunas instalaciones de MySQL no leen la carpeta /etc/my.cnf.d: si los ajustes no se ven, se le agrega al archivo principal
lee_ajustes() { my_print_defaults mysqld 2>/dev/null | grep -q -- "--innodb_buffer_pool_size=${POOL_MB}M"; }
if ! lee_ajustes && [ -f /etc/my.cnf ] && ! grep -q '^!includedir /etc/my.cnf.d' /etc/my.cnf; then
    cp /etc/my.cnf "/etc/my.cnf.$(date +%Y%m%d%H%M).bak"
    printf '
# Sistema TUSHPA: leer también los ajustes de /etc/my.cnf.d
!includedir /etc/my.cnf.d
' >> /etc/my.cnf
    echo "    Se agregó /etc/my.cnf.d a /etc/my.cnf (MySQL no leía esa carpeta)."
fi
if ! lee_ajustes; then
    echo "    ⚠ MySQL no lee los ajustes (su archivo principal no es /etc/my.cnf). No se reinicia: avisa a soporte con:  my_print_defaults mysqld"
    exit 1
fi
# systemd también limita los archivos abiertos de MySQL
mkdir -p /etc/systemd/system/mysqld.service.d
printf '[Service]\nLimitNOFILE=65535\n' > /etc/systemd/system/mysqld.service.d/tushpa.conf
systemctl daemon-reload
# Revisa el archivo antes de reiniciar (si tiene algo que esta versión de MySQL no conoce, no se reinicia)
mysqld --validate-config --user=mysql >/dev/null 2>&1 || volver_mysql
systemctl restart mysqld || volver_mysql
echo "    ✔ MySQL reiniciado leyendo los ajustes: ${POOL_MB} MB en memoria y el historial de cambios guardado solo 3 días."
echo "    Historiales que quedan: $(ls /var/lib/mysql/binlog.0* 2>/dev/null | wc -l) archivo(s), $(du -ch /var/lib/mysql/binlog.0* 2>/dev/null | tail -1 | cut -f1)"
echo "    Espacio total de MySQL: $(du -sh /var/lib/mysql 2>/dev/null | cut -f1)" 

verde "2/3 PHP-FPM (hasta ${HIJOS} pedidos atendidos a la vez)"
POOL=/etc/php-fpm.d/www.conf
cp "${POOL}" "${POOL}.$(date +%Y%m%d%H%M).bak"
fijar() {  # fijar clave valor  -> reemplaza la línea (aunque esté comentada) o la agrega
    if grep -qE "^;?\s*$1\s*=" "${POOL}"; then sed -i -E "s|^;?\s*$1\s*=.*|$1 = $2|" "${POOL}"; else echo "$1 = $2" >> "${POOL}"; fi
}
fijar pm dynamic
fijar pm.max_children "${HIJOS}"
fijar pm.start_servers $(( HIJOS / 4 > 2 ? HIJOS / 4 : 2 ))
fijar pm.min_spare_servers $(( HIJOS / 8 > 2 ? HIJOS / 8 : 2 ))
fijar pm.max_spare_servers $(( HIJOS / 3 > 4 ? HIJOS / 3 : 4 ))
# Recicla cada proceso cada tanto: si alguno crece por un reporte grande, vuelve a su tamaño
fijar pm.max_requests 1000

verde "3/3 OPcache (el código de PHP queda compilado en memoria)"
cat > /etc/php.d/99-tushpa-opcache.ini <<'INI'
; Sistema TUSHPA (deploy/optimizar-servidor.sh)
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 40000
; Revisa si cambió un archivo como mucho cada 60 s (no en cada pedido); actualizar.sh igual recarga PHP-FPM al subir una versión
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60
realpath_cache_size = 4096K
realpath_cache_ttl = 600
INI
php-fpm -t
systemctl reload php-fpm

echo
echo "Listo. Para ver las consultas lentas (más de 1 s):  tail -n 50 /var/log/mysql-lentas.log"
