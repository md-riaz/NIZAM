# NIZAM Bare-Metal Installation Guide

This is the **current full bare-metal setup** for NIZAM on:
- **Ubuntu 22.04 LTS**
- **Debian 12 (Bookworm)**

Use this when you want NIZAM installed directly on the host instead of Docker.

If you just want the fastest path on a fresh VPS, use:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/md-riaz/NIZAM/main/install.sh)
```

That installer is the automated version of this doc.

---

## What this guide installs

- PHP 8.3 + PHP-FPM
- Composer
- PostgreSQL 16
- Redis 7
- FreeSWITCH 1.10
- nginx
- Supervisor
- NIZAM application
- FreeSWITCH XML-cURL integration
- FreeSWITCH ESL integration
- generated gateway XML provisioning directory
- queue worker, scheduler, ESL listener supervision
- basic production hardening

---

## Architecture summary

On bare metal, NIZAM runs like this:

- **nginx** serves the Laravel app
- **PHP-FPM** runs Laravel
- **PostgreSQL** stores the application state
- **Redis** handles cache, sessions, and queues
- **FreeSWITCH** handles SIP/media/runtime telephony
- **NIZAM** serves dialplan and directory via `mod_xml_curl`
- **NIZAM** writes generated gateway XML files into the FreeSWITCH external SIP profile include directory
- **NIZAM ESL listener** receives real-time call and registration events from FreeSWITCH

### Important design rule
NIZAM is the source of truth.

That means:
- database state drives routing
- database state drives generated gateway XML
- FreeSWITCH runtime config is a compiled/generated artifact
- you should not manually maintain gateway XML by hand

---

## 1. System requirements

| Component | Minimum | Recommended |
|---|---:|---:|
| CPU | 2 vCPU | 4 vCPU+ |
| RAM | 2 GB | 4 GB+ |
| Disk | 20 GB | 40 GB SSD+ |
| OS | Ubuntu 22.04 / Debian 12 | Debian 12 preferred |
| PHP | 8.3 | 8.3 |
| PostgreSQL | 16 | 16 |
| Redis | 7 | 7 |
| FreeSWITCH | 1.10 | 1.10 |

### Network ports

| Port | Proto | Purpose |
|---|---|---|
| 80 | TCP | HTTP |
| 443 | TCP | HTTPS |
| 5060 | TCP/UDP | SIP |
| 5080 | TCP/UDP | External SIP profile |
| 7443 | TCP | WSS / WebRTC |
| 16384-16484 | UDP | RTP |
| 8021 | TCP | FreeSWITCH ESL, **internal only** |

### Notes
- `8021` must **not** be exposed publicly.
- If you deploy behind NAT, you must set the FreeSWITCH external SIP/RTP IP values correctly.
- If you want local SIP trunk testing without a real carrier, use the separate `docs/sip-mock-testing.md` workflow in Docker/dev. Bare metal here is the production-style install path.

---

## 2. Install base packages

```bash
sudo apt-get update
sudo apt-get install -y \
  curl wget git unzip gnupg2 lsb-release ca-certificates \
  apt-transport-https software-properties-common \
  nginx supervisor ufw build-essential
```

---

## 3. Install PHP 8.3

### Ubuntu 22.04

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
```

### Debian 12

```bash
curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg \
  https://packages.sury.org/php/apt.gpg

echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] \
https://packages.sury.org/php/ $(lsb_release -sc) main" \
  | sudo tee /etc/apt/sources.list.d/php.list

sudo apt-get update
```

### Install PHP packages

```bash
sudo apt-get install -y \
  php8.3-fpm \
  php8.3-cli \
  php8.3-pgsql \
  php8.3-mbstring \
  php8.3-xml \
  php8.3-bcmath \
  php8.3-curl \
  php8.3-redis \
  php8.3-zip \
  php8.3-opcache \
  php8.3-pcntl \
  php8.3-sockets
```

### Tune PHP-FPM

```bash
sudo sed -i 's/^;opcache.enable=.*/opcache.enable=1/' /etc/php/8.3/fpm/php.ini
sudo sed -i 's/^;opcache.memory_consumption=.*/opcache.memory_consumption=256/' /etc/php/8.3/fpm/php.ini
sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 16M/' /etc/php/8.3/fpm/php.ini
sudo sed -i 's/^post_max_size = .*/post_max_size = 16M/' /etc/php/8.3/fpm/php.ini

sudo systemctl enable php8.3-fpm
sudo systemctl restart php8.3-fpm
```

---

## 4. Install Composer

```bash
curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php
composer --version
```

---

## 5. Install PostgreSQL 16

```bash
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
  | sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg

echo "deb https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
  | sudo tee /etc/apt/sources.list.d/pgdg.list

sudo apt-get update
sudo apt-get install -y postgresql-16 postgresql-client-16
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

### Create DB and user

```bash
sudo -u postgres psql <<'SQL'
CREATE USER nizam WITH PASSWORD 'change_me_now';
CREATE DATABASE nizam OWNER nizam;
GRANT ALL PRIVILEGES ON DATABASE nizam TO nizam;
SQL
```

---

## 6. Install Redis 7

```bash
curl -fsSL https://packages.redis.io/gpg \
  | sudo gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg

echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] \
https://packages.redis.io/deb $(lsb_release -cs) main" \
  | sudo tee /etc/apt/sources.list.d/redis.list

sudo apt-get update
sudo apt-get install -y redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### Lock Redis down

```bash
sudo sed -i 's/^bind .*/bind 127.0.0.1 ::1/' /etc/redis/redis.conf
```

Set a real password:

```bash
sudo sh -c "grep -q '^requirepass ' /etc/redis/redis.conf \
  && sed -i 's|^requirepass .*|requirepass change_me_now|' /etc/redis/redis.conf \
  || echo 'requirepass change_me_now' >> /etc/redis/redis.conf"

sudo systemctl restart redis-server
```

---

## 7. Install FreeSWITCH 1.10

## Option A: Debian 12 package install

```bash
wget -O - https://files.freeswitch.org/repo/deb/debian-release/fsstretch-archive-keyring.asc \
  | sudo gpg --dearmor -o /usr/share/keyrings/freeswitch-archive-keyring.gpg

echo "deb [signed-by=/usr/share/keyrings/freeswitch-archive-keyring.gpg] \
https://files.freeswitch.org/repo/deb/debian-release/ bookworm main" \
  | sudo tee /etc/apt/sources.list.d/freeswitch.list

sudo apt-get update
sudo apt-get install -y freeswitch-meta-all
sudo systemctl enable freeswitch
sudo systemctl start freeswitch
```

## Option B: Ubuntu 22.04 source build

Ubuntu 22.04 still needs the source-build path used by the project installer.
If you want the exact maintained build logic, use:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/md-riaz/NIZAM/main/install.sh)
```

If you insist on doing it manually, follow the same dependency/build sequence used in `install.sh`.
That script is the canonical source here.

---

## 8. Install NIZAM

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/md-riaz/NIZAM.git nizam
sudo chown -R www-data:www-data /var/www/nizam
```

### Install PHP dependencies

```bash
cd /var/www/nizam
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
```

### Create environment file

```bash
cd /var/www/nizam
sudo -u www-data cp .env.example .env
sudo -u www-data nano .env
```

Use a production `.env` like this as the baseline:

```env
APP_NAME=NIZAM
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nizam
DB_USERNAME=nizam
DB_PASSWORD=change_me_now

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=change_me_now
REDIS_PORT=6379

FREESWITCH_HOST=127.0.0.1
FREESWITCH_ESL_PORT=8021
FREESWITCH_ESL_PASSWORD=change_me_now

FREESWITCH_XML_CURL_URL=http://127.0.0.1/freeswitch/xml-curl
NIZAM_XML_CURL_URL=http://127.0.0.1/freeswitch/xml-curl

# Gateway XML provisioning directory on bare metal
FREESWITCH_GATEWAY_DIRECTORY=/etc/freeswitch/sip_profiles/external

# NAT / public IP values if this box is exposed directly
EXT_SIP_IP=YOUR_PUBLIC_IP
EXT_RTP_IP=YOUR_PUBLIC_IP
```

### Generate key, migrate, cache, sync permissions

```bash
cd /var/www/nizam
sudo -u www-data php artisan key:generate
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan nizam:sync-permissions
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### File permissions

```bash
sudo chown -R www-data:www-data /var/www/nizam/storage /var/www/nizam/bootstrap/cache
sudo chmod -R 775 /var/www/nizam/storage /var/www/nizam/bootstrap/cache
```

---

## 9. Configure nginx

Create the site:

```bash
sudo tee /etc/nginx/sites-available/nizam > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.example.com;

    root /var/www/nizam/public;
    index index.php;

    charset utf-8;
    client_max_body_size 16M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/nizam_access.log;
    error_log  /var/log/nginx/nizam_error.log warn;
}
EOF
```

Enable it:

```bash
sudo ln -sf /etc/nginx/sites-available/nizam /etc/nginx/sites-enabled/nizam
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl enable nginx
sudo systemctl reload nginx
```

### Add HTTPS

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.example.com
```

---

## 10. Configure FreeSWITCH for NIZAM

## 10.1 event_socket.conf.xml

Set ESL to localhost only and match the Laravel `.env` password:

`/etc/freeswitch/autoload_configs/event_socket.conf.xml`

```xml
<configuration name="event_socket.conf" description="Socket Client">
  <settings>
    <param name="nat-map" value="false"/>
    <param name="listen-ip" value="127.0.0.1"/>
    <param name="listen-port" value="8021"/>
    <param name="password" value="change_me_now"/>
    <param name="apply-inbound-acl" value="loopback.auto"/>
  </settings>
</configuration>
```

## 10.2 xml_curl.conf.xml

NIZAM serves dialplan and directory dynamically via XML-cURL.

`/etc/freeswitch/autoload_configs/xml_curl.conf.xml`

```xml
<configuration name="xml_curl.conf" description="cURL XML Gateway">
  <bindings>
    <binding name="nizam">
      <param name="gateway-url" value="http://127.0.0.1/freeswitch/xml-curl" bindings="directory|dialplan"/>
    </binding>
  </bindings>
</configuration>
```

## 10.3 Generated gateway XML include directory

This part matters now.

NIZAM writes one XML file per gateway into the configured external SIP profile include directory.
On bare metal, use:

```bash
sudo mkdir -p /etc/freeswitch/sip_profiles/external
sudo chown -R www-data:www-data /etc/freeswitch/sip_profiles/external
```

And in `.env`:

```env
FREESWITCH_GATEWAY_DIRECTORY=/etc/freeswitch/sip_profiles/external
```

### How it works
- DB gateway row exists → NIZAM generates `v_<gateway_uuid>.xml`
- gateway updated → file regenerated
- gateway deleted → file removed and Sofia reloaded/rescanned
- if drift happens → run reconcile command

### Reconcile command

```bash
cd /var/www/nizam
sudo -u www-data php artisan nizam:reconcile-gateways --dry-run
sudo -u www-data php artisan nizam:reconcile-gateways
```

Use this if:
- you suspect stale gateway XML files
- filesystem and DB drifted
- you want a safety cleanup pass after manual recovery work

## 10.4 Restart FreeSWITCH

```bash
sudo systemctl restart freeswitch
sudo fs_cli -p change_me_now -x "status"
```

---

## 11. Background process supervision

NIZAM needs these long-running processes:

- queue worker
- scheduler
- ESL listener

Install Supervisor if not already installed:

```bash
sudo apt-get install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

## 11.1 Queue worker

```bash
sudo tee /etc/supervisor/conf.d/nizam-queue.conf > /dev/null <<'EOF'
[program:nizam-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nizam/artisan queue:work redis --sleep=3 --tries=3 --timeout=90 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nizam-queue.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
EOF
```

## 11.2 ESL listener

Use the current command name:

```bash
sudo tee /etc/supervisor/conf.d/nizam-esl.conf > /dev/null <<'EOF'
[program:nizam-esl-listener]
command=php /var/www/nizam/artisan freeswitch:listen
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nizam-esl.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
startsecs=5
startretries=10
EOF
```

## 11.3 Scheduler

```bash
sudo tee /etc/supervisor/conf.d/nizam-scheduler.conf > /dev/null <<'EOF'
[program:nizam-scheduler]
command=php /var/www/nizam/artisan schedule:work
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nizam-scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
EOF
```

## 11.4 Load Supervisor config

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## 12. First verification steps

### API health

```bash
curl http://127.0.0.1/api/v1/health | python3 -m json.tool
```

### FreeSWITCH ESL

```bash
sudo fs_cli -p change_me_now -x "status"
```

### Laravel queue/listener/scheduler

```bash
sudo supervisorctl status
```

### Route cache sanity

If you edited routes or pulled a new version and routing looks wrong:

```bash
cd /var/www/nizam
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan route:cache
```

### Gateway XML reconcile dry-run

```bash
cd /var/www/nizam
sudo -u www-data php artisan nizam:reconcile-gateways --dry-run
```

---

## 13. Production hardening checklist

## Firewall

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 5060/tcp
sudo ufw allow 5060/udp
sudo ufw allow 5080/tcp
sudo ufw allow 5080/udp
sudo ufw allow 7443/tcp
sudo ufw allow 16384:16484/udp
sudo ufw enable
```

Do **not** open `8021` publicly.

## Checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] strong `APP_KEY`
- [ ] strong PostgreSQL password
- [ ] strong Redis password
- [ ] `FREESWITCH_ESL_PASSWORD` changed from default
- [ ] HTTPS enabled
- [ ] `8021` blocked from public access
- [ ] nginx logs monitored
- [ ] supervisor logs monitored
- [ ] DB backup scheduled
- [ ] `php artisan config:cache` and `route:cache` run
- [ ] NAT/public IP values set correctly if the host is not on a public interface directly
- [ ] gateway reconcile command tested once

---

## 14. Log rotation

```bash
sudo tee /etc/logrotate.d/nizam > /dev/null <<'EOF'
/var/www/nizam/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
}

/var/log/supervisor/nizam-*.log {
    weekly
    missingok
    rotate 8
    compress
    delaycompress
    notifempty
}
EOF
```

---

## 15. Upgrade procedure

```bash
cd /var/www/nizam
sudo -u www-data git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart
sudo supervisorctl restart nizam-esl-listener
sudo systemctl reload php8.3-fpm
```

If you changed gateway provisioning code or suspect XML drift:

```bash
cd /var/www/nizam
sudo -u www-data php artisan nizam:reconcile-gateways
```

---

## 16. Operational notes that matter now

## `show registrations` is not the only truth
In this stack, FreeSWITCH may show `0 total.` for `show registrations` even when a specific gateway shows `REGED` and `UP`.

Trust the gateway-specific status more than that summary command during troubleshooting.

## Gateway runtime files are generated
Do not commit or hand-edit generated files like:

```text
storage/freeswitch/sip_profiles/external/v_<gateway_uuid>.xml
```

On bare metal, the configured gateway directory is also generated runtime state.

## Bridge routing is first-class now
The current app supports bridge-aware routing across:
- DIDs
- policies
- time conditions
- IVR timeout routing
- ring-group fallbacks
- gateway-backed outbound originate

So this install guide assumes the current bridge/gateway-era backend, not the old simpler one.

---

## 17. Related docs

- [README.md](../README.md)
- [docs/environment-bootstrap.md](environment-bootstrap.md)
- [docs/deployment-scaling.md](deployment-scaling.md)
- [docs/api-reference.md](api-reference.md)
- [docs/sip-mock-testing.md](sip-mock-testing.md)
- [install.sh](../install.sh)
