# Multi-empresa TUSHPA

Cada cliente tiene su propia base de datos `bd_{RUC}` y entra por `https://{RUC}.tushpa.app`.
Tú administras los clientes desde `https://admin.tushpa.app/{TENANCY_ADMIN_RUTA}`.

```
admin.tushpa.app/crearnuevadata  → panel (solo superadmin)   → bd_tushpa_central
20614580063.tushpa.app           → sistema del cliente        → bd_20614580063
```

- Sin `TENANCY_DOMINIO` en el `.env`, el sistema funciona como siempre (una sola base). Así trabajas en Laragon.
- Los registros DNS específicos (69, abbycafe, demo…) ganan sobre el comodín `*`: los clientes del servidor viejo no se tocan.

---

## 1. DNS en Cloudflare (una sola vez)

1. Crea una cuenta en cloudflare.com (plan Free) → **Add a site** → `tushpa.app`.
2. Cloudflare importa tus registros. **Compáralos con GoDaddy uno por uno**, sobre todo MX y TXT si usas correo @tushpa.app.
3. Pon todos los registros en **DNS only** (nube gris), no en "Proxied".
4. Agrega el comodín: tipo `A`, nombre `*`, valor `169.58.14.211`, DNS only.
5. En GoDaddy: Dominio → DNS → **Servidores de nombres** → Cambiar → "Usaré mis propios servidores de nombres" → pon los dos que te dio Cloudflare.
6. Espera a que Cloudflare marque el sitio como **Active** (minutos u horas).

Token para el certificado: Cloudflare → My Profile → API Tokens → Create Token → plantilla **Edit zone DNS** → Zone: `tushpa.app`. Guarda el token.

## 2. Certificado comodín `*.tushpa.app` (una sola vez)

En el Contabo (AlmaLinux 10), como root:

```bash
dnf install -y epel-release
dnf install -y certbot python3-certbot-dns-cloudflare mod_ssl
```

Si `python3-certbot-dns-cloudflare` no está en EPEL 10:

```bash
python3 -m venv /opt/certbot && /opt/certbot/bin/pip install certbot certbot-dns-cloudflare
ln -s /opt/certbot/bin/certbot /usr/local/bin/certbot
```

Credenciales de Cloudflare:

```bash
mkdir -p /root/.secrets
echo "dns_cloudflare_api_token = TU_TOKEN" > /root/.secrets/cloudflare.ini
chmod 600 /root/.secrets/cloudflare.ini
```

Certificado:

```bash
certbot certonly --dns-cloudflare --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d '*.tushpa.app' --cert-name tushpa-comodin --deploy-hook "systemctl reload httpd"
systemctl enable --now certbot-renew.timer
```

Se renueva solo. Un cliente nuevo **no** necesita certificado propio.

## 3. Apache

`/etc/httpd/conf.d/zz-tushpa-clientes.conf` (el `zz-` hace que se cargue al final, así otros sitios con nombre exacto tienen prioridad):

```apache
<VirtualHost *:80>
    ServerName admin.tushpa.app
    ServerAlias *.tushpa.app
    RewriteEngine On
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName admin.tushpa.app
    ServerAlias *.tushpa.app
    DocumentRoot /var/www/sistema_tushpa/public

    <Directory /var/www/sistema_tushpa/public>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/tushpa-comodin/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/tushpa-comodin/privkey.pem
</VirtualHost>
```

SELinux (AlmaLinux lo trae activo):

```bash
setsebool -P httpd_can_network_connect 1      # SUNAT y consulta de RUC
setsebool -P httpd_can_network_connect_db 1
chcon -R -t httpd_sys_rw_content_t /var/www/sistema_tushpa/storage /var/www/sistema_tushpa/bootstrap/cache
apachectl configtest && systemctl reload httpd
```

## 4. MySQL

Un usuario que solo puede crear y usar bases que empiecen con `bd_`:

```sql
CREATE USER 'tushpa'@'localhost' IDENTIFIED BY 'UNA_CONTRASEÑA_LARGA';
GRANT ALL PRIVILEGES ON `bd\_%`.* TO 'tushpa'@'localhost';
FLUSH PRIVILEGES;
```

## 5. `.env` de producción

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.tushpa.app

DB_DATABASE=bd_demo          # base "plantilla": la que usa `php artisan migrate`; nunca pongas aquí la central
DB_USERNAME=tushpa
DB_PASSWORD=UNA_CONTRASEÑA_LARGA

SESSION_DOMAIN=              # vacío: cada subdominio tiene su propia sesión
SESSION_SECURE_COOKIE=true

TENANCY_DOMINIO=tushpa.app
TENANCY_ADMIN_RUTA=crearnuevadata
TENANCY_ADMIN_IPS=           # opcional: IPs separadas por coma que pueden abrir el panel
CENTRAL_DB_DATABASE=bd_tushpa_central
```

## 6. Primera instalación

```bash
cd /var/www/sistema_tushpa
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force          # base demo
php artisan central:instalar         # base central
php artisan superadmin:crear         # tu usuario del panel
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Entra a `https://admin.tushpa.app/crearnuevadata` y crea tu primer cliente.

## 7. Cada actualización del sistema

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force          # base demo
php artisan clientes:migrar          # todas las bases de clientes
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`php artisan clientes:migrar --ruc=20614580063` migra solo un cliente.

## 8. Backups

`/etc/cron.daily/tushpa-backup` (chmod +x):

```bash
#!/bin/bash
DIR=/var/backups/tushpa/$(date +%F); mkdir -p "$DIR"
for BD in $(mysql -N -e "SHOW DATABASES LIKE 'bd\_%'"); do
  mysqldump --single-transaction --routines "$BD" | gzip > "$DIR/$BD.sql.gz"
done
find /var/backups/tushpa -maxdepth 1 -type d -mtime +15 -exec rm -rf {} \;
```

## 9. Pasar un cliente del servidor viejo al nuevo

1. Crea el cliente en el panel (crea `bd_{RUC}` vacía con su empresa).
2. Carga sus datos en `bd_{RUC}`.
3. Si tenía un registro DNS propio (ej. `abbycafe`), apúntalo al nuevo servidor o bórralo para que use el comodín con su RUC.
