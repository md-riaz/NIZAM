#!/bin/bash
# =============================================================================
# NIZAM VPS Installer
# =============================================================================
#
# Installs NIZAM and every dependency on a fresh VPS with zero user interaction.
# Supported OS: Ubuntu 22.04 LTS · Debian 12 (Bookworm)
#
# Usage (one-liner):
#   bash <(curl -fsSL https://raw.githubusercontent.com/md-riaz/NIZAM/main/install.sh)
#
# Or after cloning:
#   sudo bash install.sh
#
# What gets installed:
#   PHP 8.3-FPM + extensions, Composer, PostgreSQL 16, Redis 7,
#   FreeSWITCH 1.10 (packages on Debian 12 / source on Ubuntu 22.04),
#   nginx, Supervisor, UFW firewall, NIZAM application
#
# On completion the URL and admin credentials are printed and saved to
#   /root/nizam_credentials.txt
# =============================================================================

set -euo pipefail

# ── Constants ─────────────────────────────────────────────────────────────────
readonly NIZAM_REPO="https://github.com/md-riaz/NIZAM.git"
readonly NIZAM_BRANCH="main"
readonly NIZAM_DIR="/var/www/nizam"
readonly NIZAM_USER="www-data"
readonly LOG_FILE="/var/log/nizam_install.log"
readonly CREDS_FILE="/root/nizam_credentials.txt"
readonly FS_CONF_DIR="/etc/freeswitch"        # valid for both pkg and src installs

# ── Terminal colours ──────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

# ── Logging helpers ───────────────────────────────────────────────────────────
log()   { echo -e "${GREEN}[✔]${NC} $*"; }
info()  { echo -e "${CYAN}[→]${NC} $*"; }
warn()  { echo -e "${YELLOW}[!]${NC} $*"; }
die()   { echo -e "${RED}[✘] FATAL:${NC} $*" >&2; exit 1; }
step()  { echo -e "\n${BOLD}${BLUE}══ $* ══${NC}"; }

# ── Capture all output to log file as well ────────────────────────────────────
mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$LOG_FILE") 2>&1

# ── Error trap ────────────────────────────────────────────────────────────────
_on_error() {
    echo -e "${RED}[✘] Installation failed — see ${LOG_FILE} for details${NC}" >&2
}
trap _on_error ERR

# =============================================================================
# BANNER
# =============================================================================
banner() {
    echo -e "${BOLD}${BLUE}"
    echo "  ███╗   ██╗██╗███████╗ █████╗ ███╗   ███╗"
    echo "  ████╗  ██║██║╚══███╔╝██╔══██╗████╗ ████║"
    echo "  ██╔██╗ ██║██║  ███╔╝ ███████║██╔████╔██║"
    echo "  ██║╚██╗██║██║ ███╔╝  ██╔══██║██║╚██╔╝██║"
    echo "  ██║ ╚████║██║███████╗██║  ██║██║ ╚═╝ ██║"
    echo "  ╚═╝  ╚═══╝╚═╝╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝"
    echo -e "${NC}"
    echo -e "  ${BOLD}Open Communications Control Platform — VPS Installer${NC}"
    echo -e "  $(date '+%Y-%m-%d %H:%M:%S %Z')"
    echo ""
}

# =============================================================================
# PRE-FLIGHT
# =============================================================================
check_root() {
    [[ $EUID -eq 0 ]] || die "Run this script as root:  sudo bash $0"
}

detect_os() {
    [[ -f /etc/os-release ]] || die "Cannot detect OS — /etc/os-release missing"
    # shellcheck source=/dev/null
    source /etc/os-release
    case "${ID}-${VERSION_ID}" in
        ubuntu-22.04)
            FS_INSTALL_METHOD="source"
            info "OS: Ubuntu 22.04 LTS — FreeSWITCH will be compiled from source (~30 min)"
            ;;
        debian-12)
            FS_INSTALL_METHOD="packages"
            info "OS: Debian 12 (Bookworm) — FreeSWITCH will be installed from official packages"
            ;;
        *)
            die "Unsupported OS: ${ID} ${VERSION_ID}. Supported: Ubuntu 22.04, Debian 12."
            ;;
    esac
}

detect_server_ip() {
    SERVER_IP=$(
        curl -4 -fsSL --connect-timeout 5 https://ifconfig.me 2>/dev/null ||
        curl -4 -fsSL --connect-timeout 5 https://icanhazip.com 2>/dev/null ||
        hostname -I 2>/dev/null | awk '{print $1}' ||
        echo "127.0.0.1"
    )
    info "Server IP: ${SERVER_IP}"
}

generate_credentials() {
    # alphanumeric only — safe in shell variables, .env values, and SQL strings
    _gen() { openssl rand -base64 32 | tr -d '/+=' | head -c 24; }
    DB_PASS=$(    _gen)
    REDIS_PASS=$( _gen)
    ESL_PASS=$(   _gen)
    ADMIN_PASS=$( _gen)
    ADMIN_EMAIL="admin@nizam.local"
}

# =============================================================================
# SYSTEM PACKAGES
# =============================================================================
install_system_packages() {
    info "Updating package lists…"
    apt-get update -qq
    info "Installing base dependencies…"
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        curl wget git unzip gnupg2 lsb-release ca-certificates \
        apt-transport-https software-properties-common \
        nginx supervisor ufw \
        build-essential   # required for pecl/source builds
}

# =============================================================================
# PHP 8.3
# =============================================================================
install_php() {
    if php8.3 --version &>/dev/null 2>&1; then
        log "PHP 8.3 already installed — skipping"; return
    fi

    info "Adding PHP repository…"
    if [[ "${ID}" == "ubuntu" ]]; then
        DEBIAN_FRONTEND=noninteractive add-apt-repository ppa:ondrej/php -y -q
    else
        curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg \
            https://packages.sury.org/php/apt.gpg
        echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] \
https://packages.sury.org/php/ $(lsb_release -sc) main" \
            > /etc/apt/sources.list.d/php.list
        apt-get update -qq
    fi

    info "Installing PHP 8.3 and extensions…"
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        php8.3-fpm php8.3-cli \
        php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-bcmath \
        php8.3-curl php8.3-redis php8.3-zip php8.3-opcache \
        php8.3-pcntl php8.3-sockets

    # Performance tuning
    local ini="/etc/php/8.3/fpm/php.ini"
    sed -i 's/^;opcache.enable=.*/opcache.enable=1/'                        "$ini"
    sed -i 's/^;opcache.memory_consumption=.*/opcache.memory_consumption=256/' "$ini"
    sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 16M/'         "$ini"
    sed -i 's/^post_max_size = .*/post_max_size = 16M/'                     "$ini"

    systemctl enable php8.3-fpm --quiet
    systemctl restart php8.3-fpm
    log "PHP 8.3 installed"
}

# =============================================================================
# COMPOSER
# =============================================================================
install_composer() {
    if command -v composer &>/dev/null; then
        log "Composer already installed — skipping"; return
    fi
    info "Installing Composer…"
    local tmp
    tmp=$(mktemp)
    curl -fsSL https://getcomposer.org/installer -o "$tmp"
    php "$tmp" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f "$tmp"
    log "Composer $(composer --version --no-ansi 2>/dev/null | head -1)"
}

# =============================================================================
# POSTGRESQL 16
# =============================================================================
install_postgres() {
    if ! command -v psql &>/dev/null; then
        info "Adding PostgreSQL APT repository…"
        curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
            | gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg
        echo "deb https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" \
            > /etc/apt/sources.list.d/pgdg.list
        apt-get update -qq
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
            postgresql-16 postgresql-client-16
        systemctl enable postgresql --quiet
        systemctl start postgresql
        log "PostgreSQL 16 installed"
    else
        log "PostgreSQL already installed — skipping"
    fi

    info "Creating database user and database…"
    # idempotent — skip creation if already exists
    if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='nizam'" \
            | grep -q 1; then
        sudo -u postgres psql -c "CREATE USER nizam WITH PASSWORD '${DB_PASS}';" >/dev/null
    else
        # update password in case it changed
        sudo -u postgres psql -c "ALTER USER nizam WITH PASSWORD '${DB_PASS}';" >/dev/null
    fi
    if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='nizam'" \
            | grep -q 1; then
        sudo -u postgres psql -c "CREATE DATABASE nizam OWNER nizam;" >/dev/null
    fi
    sudo -u postgres psql -c \
        "GRANT ALL PRIVILEGES ON DATABASE nizam TO nizam;" >/dev/null 2>&1 || true
    log "PostgreSQL database ready"
}

# =============================================================================
# REDIS 7
# =============================================================================
install_redis() {
    if ! command -v redis-cli &>/dev/null; then
        info "Adding Redis APT repository…"
        curl -fsSL https://packages.redis.io/gpg \
            | gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg
        echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] \
https://packages.redis.io/deb $(lsb_release -cs) main" \
            > /etc/apt/sources.list.d/redis.list
        apt-get update -qq
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq redis
        log "Redis installed"
    else
        log "Redis already installed — skipping"
    fi

    info "Configuring Redis with password and loopback binding…"
    # requirepass — add or update
    if grep -q '^requirepass ' /etc/redis/redis.conf; then
        sed -i "s|^requirepass .*|requirepass ${REDIS_PASS}|" /etc/redis/redis.conf
    else
        sed -i "s|^# requirepass .*|requirepass ${REDIS_PASS}|" /etc/redis/redis.conf
        grep -q '^requirepass ' /etc/redis/redis.conf \
            || echo "requirepass ${REDIS_PASS}" >> /etc/redis/redis.conf
    fi
    # bind to loopback only
    sed -i 's/^bind .*/bind 127.0.0.1 ::1/' /etc/redis/redis.conf

    systemctl enable redis-server --quiet
    systemctl restart redis-server
    log "Redis configured"
}

# =============================================================================
# FREESWITCH — Debian 12: official packages
# =============================================================================
_install_freeswitch_packages() {
    info "Installing FreeSWITCH from official packages…"
    curl -fsSL \
        "https://files.freeswitch.org/repo/deb/debian-release/fsstretch-archive-keyring.asc" \
        | gpg --dearmor > /usr/share/keyrings/freeswitch-archive-keyring.gpg

    echo "deb [signed-by=/usr/share/keyrings/freeswitch-archive-keyring.gpg] \
https://files.freeswitch.org/repo/deb/debian-release/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/freeswitch.list

    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y freeswitch-meta-all

    systemctl enable freeswitch --quiet
    systemctl start  freeswitch
    log "FreeSWITCH installed (packages)"
}

# =============================================================================
# FREESWITCH — Ubuntu 22.04: build from source
# =============================================================================
_install_freeswitch_source() {
    warn "Building FreeSWITCH from source — this will take 20–40 minutes…"
    warn "Do NOT close this terminal."

    # ── Build dependencies ────────────────────────────────────────────────────
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        autoconf automake cmake libtool pkg-config \
        libjpeg-dev libncurses5-dev libgdbm-dev libdb-dev \
        gettext equivs dpkg-dev libpq-dev liblua5.2-dev libtiff-dev \
        libcurl4-openssl-dev libsqlite3-dev libpcre3-dev \
        libspeexdsp-dev libspeex-dev libldns-dev libedit-dev libopus-dev \
        libmemcached-dev libshout3-dev libmpg123-dev libmp3lame-dev \
        yasm nasm libsndfile1-dev libuv1-dev libvpx-dev \
        libavformat-dev libswscale-dev \
        uuid-dev libssl-dev python3-distutils mlocate unzip sqlite3

    cd /usr/src

    # ── libks ────────────────────────────────────────────────────────────────
    if [[ ! -d libks ]]; then
        git clone --depth 1 https://github.com/signalwire/libks.git libks
    fi
    cmake -S libks -B libks/build -DCMAKE_BUILD_TYPE=Release \
        -DCMAKE_INSTALL_PREFIX=/usr/local > /dev/null
    cmake --build libks/build --parallel "$(nproc)" > /dev/null
    cmake --install libks/build > /dev/null
    ldconfig

    # ── sofia-sip ────────────────────────────────────────────────────────────
    if [[ ! -f sofia-sip-1.13.17.zip ]]; then
        wget -q https://github.com/freeswitch/sofia-sip/archive/refs/tags/v1.13.17.zip \
            -O sofia-sip-1.13.17.zip
    fi
    [[ -d sofia-sip-1.13.17 ]] || unzip -q sofia-sip-1.13.17.zip
    cd sofia-sip-1.13.17
    sh autogen.sh > /dev/null 2>&1
    ./configure CFLAGS="-g -ggdb" --with-pic --with-glib=no \
        --without-doxygen --disable-stun > /dev/null
    make -j"$(nproc)" > /dev/null
    make install > /dev/null
    ldconfig
    cd /usr/src

    # ── spandsp ──────────────────────────────────────────────────────────────
    if [[ ! -d spandsp ]]; then
        git clone --depth 1 https://github.com/freeswitch/spandsp.git spandsp
    fi
    cd spandsp
    git checkout 0d2e6ac65e0e8f53d652665a743015a88bf048d4 > /dev/null 2>&1 || true
    sh autogen.sh > /dev/null 2>&1
    ./configure CFLAGS="-g -ggdb" --with-pic > /dev/null
    make -j"$(nproc)" > /dev/null
    make install > /dev/null
    ldconfig
    cd /usr/src

    # ── FreeSWITCH 1.10.12 ───────────────────────────────────────────────────
    local FS_TARBALL="freeswitch-1.10.12.-release.tar.gz"
    if [[ ! -f "$FS_TARBALL" ]]; then
        wget -q "https://files.freeswitch.org/releases/freeswitch/${FS_TARBALL}"
    fi
    [[ -d freeswitch-1.10.12.-release ]] || tar -zxf "$FS_TARBALL"
    cd freeswitch-1.10.12.-release

    # Enable call-center and shout; disable modules that fail on Ubuntu 22.04
    sed -i 's|applications/mod_signalwire|#applications/mod_signalwire|'   modules.conf
    sed -i 's|endpoints/mod_skinny|#endpoints/mod_skinny|'                  modules.conf
    sed -i 's|endpoints/mod_verto|#endpoints/mod_verto|'                    modules.conf
    sed -i 's|#applications/mod_callcenter|applications/mod_callcenter|'   modules.conf
    sed -i 's|#formats/mod_shout|formats/mod_shout|'                        modules.conf

    ./configure CFLAGS="-g -ggdb" \
        --with-pic --with-openssl --enable-core-pgsql-support > /dev/null
    make -j"$(nproc)" > /dev/null
    make install > /dev/null
    make cd-sounds-install > /dev/null
    make cd-moh-install    > /dev/null

    # ── System user + symlinks ────────────────────────────────────────────────
    groupadd -r freeswitch 2>/dev/null || true
    useradd  -r -g freeswitch -d /usr/local/freeswitch \
        -s /usr/sbin/nologin freeswitch 2>/dev/null || true
    chown -R freeswitch:freeswitch /usr/local/freeswitch
    ln -sf /usr/local/freeswitch/bin/freeswitch /usr/sbin/freeswitch
    ln -sf /usr/local/freeswitch/bin/fs_cli      /usr/bin/fs_cli
    # Config symlink: all code uses /etc/freeswitch regardless of install method
    ln -sfn /usr/local/freeswitch/conf           /etc/freeswitch
    ldconfig

    # ── systemd unit ─────────────────────────────────────────────────────────
    cat > /etc/systemd/system/freeswitch.service << 'UNIT'
[Unit]
Description=FreeSWITCH VoIP Platform
After=network.target syslog.target

[Service]
Type=forking
PIDFile=/usr/local/freeswitch/run/freeswitch.pid
ExecStart=/usr/sbin/freeswitch -nonat -nf -nc -rp
ExecReload=/usr/bin/kill -HUP $MAINPID
User=freeswitch
Group=freeswitch
LimitCORE=infinity
LimitNOFILE=100000
LimitNPROC=60000
LimitRTPRIO=infinity
IOSchedulingClass=realtime
IOSchedulingPriority=2
CPUSchedulingPolicy=rr
CPUSchedulingPriority=89
UMask=0007
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
UNIT

    systemctl daemon-reload
    systemctl enable freeswitch --quiet
    systemctl start  freeswitch
    log "FreeSWITCH built and installed from source"
}

install_freeswitch() {
    if command -v freeswitch &>/dev/null; then
        log "FreeSWITCH already installed — skipping"; return
    fi
    if [[ "${FS_INSTALL_METHOD}" == "packages" ]]; then
        _install_freeswitch_packages
    else
        _install_freeswitch_source
    fi
}

# =============================================================================
# NIZAM APPLICATION
# =============================================================================
install_nizam() {
    # ── Clone ──────────────────────────────────────────────────────────────────
    if [[ -d "${NIZAM_DIR}/.git" ]]; then
        info "Repository already cloned — pulling latest…"
        git -C "${NIZAM_DIR}" fetch --quiet origin
        git -C "${NIZAM_DIR}" reset --hard "origin/${NIZAM_BRANCH}" --quiet
    else
        info "Cloning NIZAM to ${NIZAM_DIR}…"
        mkdir -p /var/www
        git clone --branch "${NIZAM_BRANCH}" --depth 1 \
            "${NIZAM_REPO}" "${NIZAM_DIR}" --quiet
        chown -R "${NIZAM_USER}:${NIZAM_USER}" "${NIZAM_DIR}"
    fi

    # ── PHP dependencies ───────────────────────────────────────────────────────
    info "Installing PHP dependencies (Composer)…"
    sudo -u "${NIZAM_USER}" composer install \
        --working-dir="${NIZAM_DIR}" \
        --no-dev --optimize-autoloader --no-interaction --quiet

    # ── Generate APP_KEY ───────────────────────────────────────────────────────
    APP_KEY="base64:$(openssl rand -base64 32)"

    # ── Write .env ────────────────────────────────────────────────────────────
    info "Writing .env…"
    cat > "${NIZAM_DIR}/.env" << ENVEOF
APP_NAME=NIZAM
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=http://${SERVER_IP}

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nizam
DB_USERNAME=nizam
DB_PASSWORD=${DB_PASS}

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=${REDIS_PASS}
REDIS_PORT=6379

MAIL_MAILER=log

FREESWITCH_HOST=127.0.0.1
FREESWITCH_ESL_PORT=8021
FREESWITCH_ESL_PASSWORD=${ESL_PASS}
FREESWITCH_XML_CURL_URL=http://127.0.0.1/freeswitch/xml-curl
NIZAM_XML_CURL_URL=http://127.0.0.1/freeswitch/xml-curl

EXT_RTP_IP=${SERVER_IP}
EXT_SIP_IP=${SERVER_IP}
RTP_PORT_RANGE_START=16384
RTP_PORT_RANGE_END=32768
DTMF_TYPE=rfc2833
SRTP_POLICY=optional

SANCTUM_STATEFUL_DOMAINS=${SERVER_IP}
ENVEOF
    chown "${NIZAM_USER}:${NIZAM_USER}" "${NIZAM_DIR}/.env"
    chmod 640 "${NIZAM_DIR}/.env"

    # ── Migrate ────────────────────────────────────────────────────────────────
    info "Running database migrations…"
    sudo -u "${NIZAM_USER}" php "${NIZAM_DIR}/artisan" migrate --force --quiet

    # ── Create admin user and default tenant ───────────────────────────────────
    info "Creating admin user (${ADMIN_EMAIL})…"

    # Bootstrap Laravel from a temp PHP file to avoid tinker's interactive mode
    local setup_php
    setup_php=$(mktemp /tmp/nizam_setup.XXXXXX.php)
    # NOTE: heredoc is intentionally unquoted so that bash expands
    # ${NIZAM_DIR}, ${SERVER_IP}, ${ADMIN_EMAIL}, ${ADMIN_PASS}.
    # PHP-level variables are escaped as \$ to remain literal in the PHP file.
    cat > "${setup_php}" << PHPEOF
<?php
define('LARAVEL_START', microtime(true));
chdir('${NIZAM_DIR}');
require '${NIZAM_DIR}/vendor/autoload.php';
\$app = require_once '${NIZAM_DIR}/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

// Create default tenant (idempotent)
\$tenant = App\Models\Tenant::firstOrCreate(
    ['slug' => 'nizam'],
    [
        'name'           => 'NIZAM',
        'domain'         => '${SERVER_IP}',
        'settings'       => [],
        'max_extensions' => 100,
        'is_active'      => true,
    ]
);

// Create admin user (idempotent)
App\Models\User::firstOrCreate(
    ['email' => '${ADMIN_EMAIL}'],
    [
        'name'      => 'Administrator',
        'password'  => '${ADMIN_PASS}',
        'tenant_id' => \$tenant->id,
        'role'      => 'admin',
    ]
);

echo "OK\n";
PHPEOF

    local result
    result=$(sudo -u "${NIZAM_USER}" php "${setup_php}" 2>&1) || true
    rm -f "${setup_php}"
    if [[ "${result}" != *"OK"* ]]; then
        die "Admin user creation failed:\n${result}"
    fi

    # ── Warm caches ────────────────────────────────────────────────────────────
    info "Warming Laravel caches…"
    sudo -u "${NIZAM_USER}" php "${NIZAM_DIR}/artisan" config:cache --quiet
    sudo -u "${NIZAM_USER}" php "${NIZAM_DIR}/artisan" route:cache  --quiet
    sudo -u "${NIZAM_USER}" php "${NIZAM_DIR}/artisan" view:cache   --quiet
    sudo -u "${NIZAM_USER}" php "${NIZAM_DIR}/artisan" nizam:sync-permissions --quiet 2>/dev/null || true

    # ── File permissions ───────────────────────────────────────────────────────
    chown -R "${NIZAM_USER}:${NIZAM_USER}" \
        "${NIZAM_DIR}/storage" \
        "${NIZAM_DIR}/bootstrap/cache"
    chmod -R 775 \
        "${NIZAM_DIR}/storage" \
        "${NIZAM_DIR}/bootstrap/cache"

    log "NIZAM application installed"
}

# =============================================================================
# NGINX
# =============================================================================
configure_nginx() {
    info "Writing nginx vhost…"
    cat > /etc/nginx/sites-available/nizam << NGINXEOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${SERVER_IP} _;

    root ${NIZAM_DIR}/public;
    index index.php;

    charset utf-8;
    client_max_body_size 16M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/nizam_access.log;
    error_log  /var/log/nginx/nizam_error.log warn;
}
NGINXEOF

    # Enable NIZAM site, remove default
    ln -sf /etc/nginx/sites-available/nizam /etc/nginx/sites-enabled/nizam
    rm -f /etc/nginx/sites-enabled/default

    nginx -t
    systemctl enable nginx --quiet
    systemctl reload nginx
    log "nginx configured"
}

# =============================================================================
# FREESWITCH INTEGRATION
# =============================================================================
configure_freeswitch() {
    info "Configuring FreeSWITCH integration…"

    local conf="${FS_CONF_DIR}/autoload_configs"

    # event_socket.conf.xml — set ESL password, restrict to loopback
    cat > "${conf}/event_socket.conf.xml" << FSEOF
<configuration name="event_socket.conf" description="Socket Client">
  <settings>
    <param name="nat-map" value="false"/>
    <param name="listen-ip" value="127.0.0.1"/>
    <param name="listen-port" value="8021"/>
    <param name="password" value="${ESL_PASS}"/>
    <param name="apply-inbound-acl" value="loopback.auto"/>
  </settings>
</configuration>
FSEOF

    # xml_curl.conf.xml — point FreeSWITCH to NIZAM for directory + dialplan
    cat > "${conf}/xml_curl.conf.xml" << FSEOF
<configuration name="xml_curl.conf" description="cURL XML Gateway">
  <bindings>
    <binding name="nizam">
      <param name="gateway-url" value="http://127.0.0.1/freeswitch/xml-curl" bindings="directory|dialplan"/>
    </binding>
  </bindings>
</configuration>
FSEOF

    # Ownership (packages user is 'freeswitch', source build also uses 'freeswitch')
    chown -R freeswitch:freeswitch "${FS_CONF_DIR}" 2>/dev/null || true

    # Restart to apply new config
    systemctl restart freeswitch

    # Wait up to 30 s for FreeSWITCH ESL to accept connections
    info "Waiting for FreeSWITCH ESL to come up…"
    local tries=10
    while (( tries > 0 )); do
        if fs_cli -p "${ESL_PASS}" -x "version" &>/dev/null 2>&1; then
            log "FreeSWITCH ESL is reachable"
            break
        fi
        tries=$(( tries - 1 ))
        sleep 3
    done
    if (( tries == 0 )); then
        warn "FreeSWITCH ESL did not respond within 30 s — check: journalctl -u freeswitch"
    fi

    log "FreeSWITCH configured"
}

# =============================================================================
# SUPERVISOR (queue worker · ESL listener · scheduler)
# =============================================================================
configure_supervisor() {
    info "Writing Supervisor program configs…"

    cat > /etc/supervisor/conf.d/nizam-queue.conf << SUPEOF
[program:nizam-queue]
process_name=%(program_name)s_%(process_num)02d
command=php ${NIZAM_DIR}/artisan queue:work redis --sleep=3 --tries=3 --timeout=90 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${NIZAM_USER}
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nizam-queue.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
SUPEOF

    cat > /etc/supervisor/conf.d/nizam-esl.conf << SUPEOF
[program:nizam-esl-listener]
command=php ${NIZAM_DIR}/artisan nizam:esl-listen
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${NIZAM_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nizam-esl.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
startsecs=5
startretries=10
SUPEOF

    cat > /etc/supervisor/conf.d/nizam-scheduler.conf << SUPEOF
[program:nizam-scheduler]
command=php ${NIZAM_DIR}/artisan schedule:work
autostart=true
autorestart=true
user=${NIZAM_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nizam-scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
SUPEOF

    systemctl enable supervisor --quiet
    systemctl start  supervisor
    supervisorctl reread  > /dev/null
    supervisorctl update  > /dev/null
    log "Supervisor configured (queue × 2, esl-listener, scheduler)"
}

# =============================================================================
# FIREWALL (UFW)
# =============================================================================
configure_firewall() {
    info "Configuring UFW firewall…"
    ufw --force disable   > /dev/null 2>&1
    ufw --force reset     > /dev/null 2>&1
    ufw default deny incoming  > /dev/null
    ufw default allow outgoing > /dev/null
    ufw allow 22/tcp    comment 'SSH'
    ufw allow 80/tcp    comment 'HTTP'
    ufw allow 443/tcp   comment 'HTTPS'
    ufw allow 5060/tcp  comment 'SIP TCP'
    ufw allow 5060/udp  comment 'SIP UDP'
    ufw allow 5080/tcp  comment 'SIP external TCP'
    ufw allow 5080/udp  comment 'SIP external UDP'
    ufw allow 16384:32768/udp comment 'RTP media'
    ufw --force enable  > /dev/null
    log "Firewall enabled"
}

# =============================================================================
# LOGROTATE
# =============================================================================
configure_logrotate() {
    cat > /etc/logrotate.d/nizam << 'LREOF'
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
LREOF
}

# =============================================================================
# SAVE CREDENTIALS & PRINT SUMMARY
# =============================================================================
save_credentials() {
    cat > "${CREDS_FILE}" << CREDSEOF
╔════════════════════════════════════════════════════════════════════╗
║                  NIZAM Installation Details                        ║
╚════════════════════════════════════════════════════════════════════╝

Installed  : $(date '+%Y-%m-%d %H:%M:%S %Z')
Server IP  : ${SERVER_IP}
Log file   : ${LOG_FILE}

── Access ───────────────────────────────────────────────────────────
API URL         : http://${SERVER_IP}/api/v1/health
Admin email     : ${ADMIN_EMAIL}
Admin password  : ${ADMIN_PASS}

── Database (PostgreSQL 16) ─────────────────────────────────────────
Host      : 127.0.0.1:5432
Database  : nizam
Username  : nizam
Password  : ${DB_PASS}

── Cache / Queue (Redis 7) ──────────────────────────────────────────
Host      : 127.0.0.1:6379
Password  : ${REDIS_PASS}

── FreeSWITCH ESL ───────────────────────────────────────────────────
Host      : 127.0.0.1:8021
Password  : ${ESL_PASS}

── Log files ────────────────────────────────────────────────────────
NIZAM app    : /var/www/nizam/storage/logs/laravel.log
nginx access : /var/log/nginx/nizam_access.log
nginx error  : /var/log/nginx/nizam_error.log
Queue worker : /var/log/supervisor/nizam-queue.log
ESL listener : /var/log/supervisor/nizam-esl.log
CREDSEOF
    chmod 600 "${CREDS_FILE}"
}

print_summary() {
    save_credentials

    local w=66   # box width (inner)
    _row() { printf "${BOLD}${GREEN}║${NC}  %-${w}s${BOLD}${GREEN}║${NC}\n" "$*"; }
    _kv()  { printf "${BOLD}${GREEN}║${NC}  ${CYAN}%-18s${NC} ${YELLOW}%-$((w-20))s${NC}${BOLD}${GREEN}║${NC}\n" "$1" "$2"; }

    echo ""
    echo -e "${BOLD}${GREEN}╔$(printf '═%.0s' $(seq 1 $((w+4))))╗${NC}"
    echo -e "${BOLD}${GREEN}║$(printf ' %.0s' $(seq 1 $((w+4))))║${NC}"
    printf   "${BOLD}${GREEN}║${NC}  %*s%-*s  ${BOLD}${GREEN}║${NC}\n" \
        $(( (w - 30) / 2 )) "" $(( w - (w - 30) / 2 )) "🎉  NIZAM Installation Complete!  🎉"
    echo -e "${BOLD}${GREEN}║$(printf ' %.0s' $(seq 1 $((w+4))))║${NC}"
    echo -e "${BOLD}${GREEN}╠$(printf '═%.0s' $(seq 1 $((w+4))))╣${NC}"
    _row ""
    _kv "API URL:"        "http://${SERVER_IP}/api/v1/health"
    _kv "Admin email:"    "${ADMIN_EMAIL}"
    _kv "Admin password:" "${ADMIN_PASS}"
    _row ""
    echo -e "${BOLD}${GREEN}╠$(printf '═%.0s' $(seq 1 $((w+4))))╣${NC}"
    _row "Credentials file: ${CREDS_FILE}"
    _row "Install log:      ${LOG_FILE}"
    echo -e "${BOLD}${GREEN}╚$(printf '═%.0s' $(seq 1 $((w+4))))╝${NC}"

    echo ""
    echo -e "  ${BOLD}Quick verification:${NC}"
    echo -e "  ${CYAN}curl http://${SERVER_IP}/api/v1/health${NC}"
    echo ""
    echo -e "  ${BOLD}Login:${NC}"
    echo -e "  ${CYAN}curl -s -X POST http://${SERVER_IP}/api/v1/auth/login \\"
    echo -e "    -H 'Content-Type: application/json' \\"
    echo -e "    -d '{\"email\":\"${ADMIN_EMAIL}\",\"password\":\"${ADMIN_PASS}\"}' \\"
    echo -e "    | python3 -m json.tool${NC}"
    echo ""
    echo -e "  ${BOLD}Add HTTPS (recommended for production):${NC}"
    echo -e "  ${CYAN}apt install certbot python3-certbot-nginx${NC}"
    echo -e "  ${CYAN}certbot --nginx -d your-domain.example.com${NC}"
    echo ""
}

# =============================================================================
# MAIN
# =============================================================================
main() {
    banner

    step "Pre-flight"
    check_root
    detect_os
    detect_server_ip
    generate_credentials

    step "System packages"
    install_system_packages

    step "PHP 8.3"
    install_php

    step "Composer"
    install_composer

    step "PostgreSQL 16"
    install_postgres

    step "Redis 7"
    install_redis

    step "FreeSWITCH 1.10"
    install_freeswitch

    step "NIZAM Application"
    install_nizam

    step "nginx"
    configure_nginx

    step "FreeSWITCH integration"
    configure_freeswitch

    step "Supervisor (workers)"
    configure_supervisor

    step "Firewall (UFW)"
    configure_firewall

    step "Log rotation"
    configure_logrotate

    print_summary
}

main "$@"
