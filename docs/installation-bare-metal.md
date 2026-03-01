# NIZAM — Bare-Metal Installation Guide

Step-by-step installation of NIZAM and its dependencies on a fresh **Ubuntu 22.04 LTS**
or **Debian 12 (Bookworm)** server without Docker.

> **Tip — Docker is easier for development.**  
> For local development, `docker compose up -d` is the fastest path.  
> This guide is for production bare-metal servers or VMs where you want full OS-level control.

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Install PHP 8.3](#2-install-php-83)
3. [Install Composer](#3-install-composer)
4. [Install PostgreSQL 16](#4-install-postgresql-16)
5. [Install Redis 7](#5-install-redis-7)
6. [Install FreeSWITCH 1.10](#6-install-freeswitch-110)
7. [Install nginx](#7-install-nginx)
8. [Install NIZAM](#8-install-nizam)
9. [Configure nginx](#9-configure-nginx)
10. [Configure FreeSWITCH](#10-configure-freeswitch)
11. [Set Up Process Supervision](#11-set-up-process-supervision)
12. [Production Hardening](#12-production-hardening)

---

## 1. System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| OS | Ubuntu 22.04 / Debian 12 | Ubuntu 22.04 LTS |
| CPU | 2 vCPU | 4 vCPU |
| RAM | 2 GB | 4 GB |
| Disk | 20 GB | 40 GB SSD |
| PHP | 8.2 | 8.3 |
| PostgreSQL | 15 | 16 |
| Redis | 7 | 7 |
| FreeSWITCH | 1.10.10 | 1.10.12 |

**Network ports** that must be accessible:

| Port | Protocol | Purpose |
|------|----------|---------|
| 80 / 443 | TCP | HTTP/HTTPS API |
| 5060 | TCP/UDP | SIP signalling |
| 5080 | TCP/UDP | SIP external profile |
| 16384–32768 | UDP | RTP media |
| 8021 | TCP | FreeSWITCH ESL (internal only — firewall off externally) |

---

## 2. Install PHP 8.3

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates apt-transport-https software-properties-common lsb-release

# Add Ondrej PHP PPA (Ubuntu) or sury.org (Debian)
if grep -qi ubuntu /etc/os-release; then
    sudo add-apt-repository ppa:ondrej/php -y
else
    sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg \
        https://packages.sury.org/php/apt.gpg
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] \
        https://packages.sury.org/php/ $(lsb_release -sc) main" \
        | sudo tee /etc/apt/sources.list.d/php.list
fi

sudo apt-get update
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

### Configure PHP-FPM

```bash
# Use production settings
sudo cp /etc/php/8.3/fpm/php.ini /etc/php/8.3/fpm/php.ini.bak
sudo sed -i 's/^;opcache.enable=.*/opcache.enable=1/' /etc/php/8.3/fpm/php.ini
sudo sed -i 's/^;opcache.memory_consumption=.*/opcache.memory_consumption=256/' /etc/php/8.3/fpm/php.ini
sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 16M/' /etc/php/8.3/fpm/php.ini
sudo sed -i 's/^post_max_size = .*/post_max_size = 16M/' /etc/php/8.3/fpm/php.ini

sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

---

## 3. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
composer --version
```

---

## 4. Install PostgreSQL 16

```bash
sudo apt-get install -y gnupg curl

# Add PostgreSQL official APT repo
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    | sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg
echo "deb https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
    | sudo tee /etc/apt/sources.list.d/pgdg.list

sudo apt-get update
sudo apt-get install -y postgresql-16 postgresql-client-16

sudo systemctl start postgresql
sudo systemctl enable postgresql
```

### Create database and user

```bash
sudo -u postgres psql <<'SQL'
CREATE USER nizam WITH PASSWORD 'change_me_in_production';
CREATE DATABASE nizam OWNER nizam;
GRANT ALL PRIVILEGES ON DATABASE nizam TO nizam;
SQL
```

---

## 5. Install Redis 7

```bash
# Add Redis official APT repo
curl -fsSL https://packages.redis.io/gpg \
    | sudo gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] \
    https://packages.redis.io/deb $(lsb_release -cs) main" \
    | sudo tee /etc/apt/sources.list.d/redis.list

sudo apt-get update
sudo apt-get install -y redis

sudo systemctl start redis-server
sudo systemctl enable redis-server
```

### Secure Redis (production)

```bash
# Set a password
sudo sed -i 's/^# requirepass .*/requirepass change_me_in_production/' /etc/redis/redis.conf

# Bind to loopback only (no public exposure)
sudo sed -i 's/^bind .*/bind 127.0.0.1 ::1/' /etc/redis/redis.conf

sudo systemctl restart redis-server
```

---

## 6. Install FreeSWITCH 1.10

> The FreeSWITCH project provides pre-built packages for Debian 12 (Bookworm).
> Ubuntu 22.04 users must build from source (see below).

### Option A — Debian 12: official packages (fastest)

```bash
sudo apt-get install -y gnupg2 wget

wget -O - https://files.freeswitch.org/repo/deb/debian-release/fsstretch-archive-keyring.asc \
    | sudo gpg --dearmor -o /usr/share/keyrings/freeswitch-archive-keyring.gpg

echo "deb [signed-by=/usr/share/keyrings/freeswitch-archive-keyring.gpg] \
    https://files.freeswitch.org/repo/deb/debian-release/ bookworm main" \
    | sudo tee /etc/apt/sources.list.d/freeswitch.list

sudo apt-get update
sudo apt-get install -y freeswitch-meta-all

sudo systemctl start freeswitch
sudo systemctl enable freeswitch
```

### Option B — Ubuntu 22.04: build from source

Build time: ~20–40 minutes depending on CPU.

```bash
# Build dependencies
sudo apt-get install -y \
    build-essential autoconf automake libtool \
    git cmake pkg-config \
    libjpeg-dev libncurses5-dev libgdbm-dev libdb-dev \
    gettext equivs dpkg-dev libpq-dev liblua5.2-dev libtiff-dev \
    libcurl4-openssl-dev libsqlite3-dev libpcre3-dev \
    libspeexdsp-dev libspeex-dev libldns-dev libedit-dev libopus-dev \
    libmemcached-dev libshout3-dev libmpg123-dev libmp3lame-dev \
    yasm nasm libsndfile1-dev libuv1-dev libvpx-dev \
    libavformat-dev libswscale-dev \
    uuid-dev libssl-dev wget ca-certificates \
    python3-distutils unzip

# Build libks
cd /usr/src
sudo git clone https://github.com/signalwire/libks.git libks
cd libks
sudo cmake .
sudo make -j$(nproc) && sudo make install && sudo ldconfig

# Build sofia-sip
cd /usr/src
sudo wget https://github.com/freeswitch/sofia-sip/archive/refs/tags/v1.13.17.zip
sudo unzip v1.13.17.zip
cd sofia-sip-1.13.17
sudo sh autogen.sh
sudo ./configure CFLAGS="-g -ggdb" --with-pic --with-glib=no --without-doxygen --disable-stun
sudo make -j$(nproc) && sudo make install && sudo ldconfig

# Build spandsp
cd /usr/src
sudo git clone https://github.com/freeswitch/spandsp.git spandsp
cd spandsp
sudo git reset --hard 0d2e6ac65e0e8f53d652665a743015a88bf048d4
sudo sh autogen.sh
sudo ./configure CFLAGS="-g -ggdb" --with-pic
sudo make -j$(nproc) && sudo make install && sudo ldconfig

# Build FreeSWITCH 1.10.12
cd /usr/src
sudo wget https://files.freeswitch.org/releases/freeswitch/freeswitch-1.10.12.-release.tar.gz
sudo tar -zxf freeswitch-1.10.12.-release.tar.gz
cd freeswitch-1.10.12.-release

# Enable callcenter and shout modules
sudo sed -i 's:applications/mod_signalwire:#applications/mod_signalwire:' modules.conf
sudo sed -i 's:endpoints/mod_skinny:#endpoints/mod_skinny:' modules.conf
sudo sed -i 's:endpoints/mod_verto:#endpoints/mod_verto:' modules.conf
sudo sed -i 's:#applications/mod_callcenter:applications/mod_callcenter:' modules.conf
sudo sed -i 's:#formats/mod_shout:formats/mod_shout:' modules.conf

sudo ./configure CFLAGS="-g -ggdb" --with-pic --with-openssl --enable-core-pgsql-support
sudo make -j$(nproc)
sudo make install
sudo make cd-sounds-install
sudo make cd-moh-install

# Create system user
sudo groupadd -r freeswitch 2>/dev/null || true
sudo useradd -r -g freeswitch -d /usr/local/freeswitch -s /usr/sbin/nologin freeswitch 2>/dev/null || true
sudo chown -R freeswitch:freeswitch /usr/local/freeswitch

# Add symlinks
sudo ln -sf /usr/local/freeswitch/bin/freeswitch /usr/sbin/freeswitch
sudo ln -sf /usr/local/freeswitch/bin/fs_cli /usr/bin/fs_cli
sudo ln -sf /usr/local/freeswitch/conf /etc/freeswitch

sudo ldconfig
```

Create a systemd unit for the source-built FreeSWITCH:

```bash
sudo tee /etc/systemd/system/freeswitch.service > /dev/null <<'EOF'
[Unit]
Description=FreeSWITCH VoIP Platform
After=network.target

[Service]
Type=forking
Environment="DAEMON_OPTS=-nonat -nf -nc -rp"
EnvironmentFile=-/etc/default/freeswitch
ExecStart=/usr/sbin/freeswitch ${DAEMON_OPTS}
ExecReload=/usr/bin/kill -HUP $MAINPID
PIDFile=/usr/local/freeswitch/run/freeswitch.pid
User=freeswitch
Group=freeswitch
LimitCORE=infinity
LimitNOFILE=100000
LimitNPROC=60000
LimitRTPRIO=infinity
LimitRTTIME=7000000
IOSchedulingClass=realtime
IOSchedulingPriority=2
CPUSchedulingPolicy=rr
CPUSchedulingPriority=89
UMask=0007
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl start freeswitch
sudo systemctl enable freeswitch
```

---

## 7. Install nginx

```bash
sudo apt-get install -y nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

---

## 8. Install NIZAM

### Clone the repository

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

### Configure the environment

```bash
sudo -u www-data cp .env.example .env
sudo -u www-data nano .env      # or your preferred editor
```

Minimum required settings:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example.com

# Use a real database password
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nizam
DB_USERNAME=nizam
DB_PASSWORD=change_me_in_production

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=change_me_in_production
REDIS_PORT=6379

# Use Redis for sessions, cache, and queues
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# FreeSWITCH
FREESWITCH_HOST=127.0.0.1
FREESWITCH_ESL_PORT=8021
FREESWITCH_ESL_PASSWORD=your_esl_password

# XML-cURL URLs (must point to where nginx serves NIZAM)
FREESWITCH_XML_CURL_URL=http://127.0.0.1/freeswitch/xml-curl
NIZAM_XML_CURL_URL=http://127.0.0.1/freeswitch/xml-curl
```

### Generate application key and run migrations

```bash
cd /var/www/nizam

# Generate APP_KEY
sudo -u www-data php artisan key:generate

# Run migrations
sudo -u www-data php artisan migrate --force

# (Optional) seed demo data
sudo -u www-data php artisan db:seed

# Warm caches for production performance
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Sync API permissions
sudo -u www-data php artisan nizam:sync-permissions

# Fix storage permissions
sudo chown -R www-data:www-data /var/www/nizam/storage /var/www/nizam/bootstrap/cache
sudo chmod -R 775 /var/www/nizam/storage /var/www/nizam/bootstrap/cache
```

---

## 9. Configure nginx

Create the nginx vhost:

```bash
sudo tee /etc/nginx/sites-available/nizam > /dev/null <<'EOF'
server {
    listen 80;
    server_name your-domain.example.com;
    # Redirect HTTP → HTTPS in production
    # return 301 https://$host$request_uri;

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

sudo ln -s /etc/nginx/sites-available/nizam /etc/nginx/sites-enabled/nizam
sudo nginx -t && sudo systemctl reload nginx
```

### Enable HTTPS with Let's Encrypt

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.example.com
```

---

## 10. Configure FreeSWITCH

NIZAM uses `mod_xml_curl` to serve FreeSWITCH its entire directory and dialplan dynamically.

### Update xml_curl.conf.xml

Edit `/etc/freeswitch/autoload_configs/xml_curl.conf.xml` (or `/usr/local/freeswitch/conf/autoload_configs/xml_curl.conf.xml`):

```xml
<configuration name="xml_curl.conf" description="cURL XML Gateway">
  <bindings>
    <binding name="directory">
      <param name="gateway-url" value="http://127.0.0.1/freeswitch/xml-curl" bindings="directory"/>
    </binding>
    <binding name="dialplan">
      <param name="gateway-url" value="http://127.0.0.1/freeswitch/xml-curl" bindings="dialplan"/>
    </binding>
  </bindings>
</configuration>
```

### Update event_socket.conf.xml

Allow NIZAM to connect to FreeSWITCH ESL from localhost:

```xml
<configuration name="event_socket.conf" description="Socket Client">
  <settings>
    <param name="nat-map" value="false"/>
    <param name="listen-ip" value="127.0.0.1"/>
    <param name="listen-port" value="8021"/>
    <param name="password" value="your_esl_password"/>
    <param name="apply-inbound-acl" value="loopback.auto"/>
  </settings>
</configuration>
```

### Restart FreeSWITCH

```bash
sudo systemctl restart freeswitch
# Verify it started
sudo fs_cli -p your_esl_password -x "status"
```

---

## 11. Set Up Process Supervision

NIZAM needs two long-running background processes in addition to PHP-FPM:

| Process | Purpose |
|---------|---------|
| **Queue worker** | Processes webhook delivery and async jobs |
| **ESL listener** | Receives real-time call events from FreeSWITCH |
| **Scheduler** | Runs periodic tasks (gateway status, cleanup) |

### Install Supervisor

```bash
sudo apt-get install -y supervisor
sudo systemctl start supervisor
sudo systemctl enable supervisor
```

### Queue worker configuration

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

### ESL listener configuration

```bash
sudo tee /etc/supervisor/conf.d/nizam-esl.conf > /dev/null <<'EOF'
[program:nizam-esl-listener]
command=php /var/www/nizam/artisan nizam:esl-listen
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

### Scheduler (cron-based)

The scheduler is lightweight and runs via cron:

```bash
# Add to www-data's crontab
(sudo crontab -u www-data -l 2>/dev/null; \
  echo "* * * * * cd /var/www/nizam && php artisan schedule:run >> /dev/null 2>&1") \
  | sudo crontab -u www-data -
```

Or use a supervisor process:

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

### Apply Supervisor changes

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## 12. Production Hardening

### Firewall (UFW)

```bash
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw allow 5060/tcp    # SIP TCP
sudo ufw allow 5060/udp    # SIP UDP
sudo ufw allow 5080/tcp    # SIP external TCP
sudo ufw allow 5080/udp    # SIP external UDP
sudo ufw allow 16384:32768/udp  # RTP media
# ESL (port 8021) must NOT be exposed publicly
sudo ufw enable
```

### Production checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- [ ] Strong `APP_KEY` (generated with `php artisan key:generate`)
- [ ] Strong passwords for PostgreSQL, Redis, and FreeSWITCH ESL
- [ ] Change `FREESWITCH_ESL_PASSWORD` from the default `ClueCon`
- [ ] HTTPS/TLS configured (Let's Encrypt or your own certificate)
- [ ] ESL port 8021 blocked at firewall (internal only)
- [ ] `php artisan config:cache && php artisan route:cache` applied
- [ ] Supervisor running queue worker, ESL listener, and scheduler
- [ ] Log rotation configured for `/var/log/supervisor/nizam-*.log`
- [ ] Automated database backups scheduled

### Log rotation for NIZAM

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
    rotate 4
    compress
    delaycompress
    notifempty
}
EOF
```

---

## Verify the Installation

```bash
# NIZAM health endpoint
curl http://localhost/api/v1/health | python3 -m json.tool

# Expected output:
# {
#   "status": "healthy",
#   "checks": {
#     "app":      { "status": "ok" },
#     "database": { "status": "ok" },
#     "cache":    { "status": "ok" },
#     "esl":      { "connected": true, "status": "ok" },
#     "gateways": { "status": "ok" }
#   }
# }
```

If `esl.connected` is `false`, check:
1. FreeSWITCH is running: `sudo systemctl status freeswitch`
2. ESL listener is running: `sudo supervisorctl status nizam-esl-listener`
3. ESL password matches in both `.env` and `event_socket.conf.xml`
4. ACL allows loopback connections: `sudo fs_cli -x "reload mod_event_socket"`

---

## Upgrading NIZAM

```bash
cd /var/www/nizam

# Pull latest code
sudo -u www-data git pull origin main

# Install new/updated dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

# Apply database migrations
sudo -u www-data php artisan migrate --force

# Refresh caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Gracefully restart queue workers (they pick up new code)
sudo -u www-data php artisan queue:restart

# Restart ESL listener
sudo supervisorctl restart nizam-esl-listener

# Reload PHP-FPM (for OPcache)
sudo systemctl reload php8.3-fpm
```

---

## See Also

- [Environment Bootstrap Guide](environment-bootstrap.md) — Docker setup, FreeSWITCH integration details
- [Deployment & Scaling Guide](deployment-scaling.md) — Production deployment, horizontal scaling
- [API Reference](api-reference.md) — Full API documentation
