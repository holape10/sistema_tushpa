#!/usr/bin/env bash
# Protege el acceso SSH del VPS (como root):  bash /var/www/html/sistema_tushpa/deploy/fail2ban-instalar.sh
# Bloquea por 1 hora la IP que falla 5 veces la contraseña en 10 minutos (los robots de internet prueban miles).
# Si ya está instalado, solo revisa que esté funcionando y muestra a quién bloqueó. Se puede correr varias veces.
set -euo pipefail
verde() { printf '\n\033[1;32m==> %s\033[0m\n' "$1"; }

verde "1/3 Instalando fail2ban"
if ! command -v fail2ban-client >/dev/null; then
    dnf -y install epel-release
    dnf -y install fail2ban
else
    echo "    Ya estaba instalado."
fi

verde "2/3 Reglas para SSH"
# La IP desde donde se corre este script nunca se bloquea (para no dejarte afuera)
MI_IP="$(echo "${SSH_CLIENT:-}" | awk '{print $1}')"
cat > /etc/fail2ban/jail.d/tushpa.local <<CONF
# Sistema TUSHPA (deploy/fail2ban-instalar.sh)
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5
# Quien vuelve a insistir después de un bloqueo, cada vez queda bloqueado más tiempo (hasta 1 semana)
bantime.increment = true
bantime.maxtime = 1w
ignoreip = 127.0.0.1/8 ::1 ${MI_IP}

[sshd]
enabled = true
backend = systemd
CONF
echo "    Tu IP actual (${MI_IP:-desconocida}) queda en la lista blanca."

verde "3/3 Encendiendo"
systemctl enable --now fail2ban
systemctl restart fail2ban
sleep 2
fail2ban-client status sshd || true
echo
echo "Listo. Para ver a quién bloqueó:      fail2ban-client status sshd"
echo "Para desbloquear una IP:              fail2ban-client set sshd unbanip 1.2.3.4"
