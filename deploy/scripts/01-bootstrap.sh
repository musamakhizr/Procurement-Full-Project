#!/usr/bin/env bash
# 01-bootstrap.sh — Install all base packages on a fresh Ubuntu 24.04 server
# Run as: sudo bash 01-bootstrap.sh
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

echo ">> Updating package lists and upgrading system…"
apt-get update -y
apt-get full-upgrade -y
apt-get install -y software-properties-common ca-certificates curl gnupg lsb-release \
    apt-transport-https unzip git htop ufw fail2ban unattended-upgrades \
    logrotate cron rsync acl

echo ">> Adding ondrej/php PPA (PHP 8.3)…"
add-apt-repository -y ppa:ondrej/php
apt-get update -y

echo ">> Installing nginx…"
apt-get install -y nginx

echo ">> Installing PHP 8.3 + extensions…"
apt-get install -y \
    php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql php8.3-redis php8.3-curl \
    php8.3-mbstring php8.3-xml php8.3-zip \
    php8.3-bcmath php8.3-intl php8.3-gd \
    php8.3-imagick php8.3-opcache php8.3-readline \
    php8.3-soap php8.3-tokenizer

echo ">> Installing Composer…"
if ! command -v composer >/dev/null 2>&1; then
    EXPECTED_SIG="$(curl -sS https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [ "$EXPECTED_SIG" = "$ACTUAL_SIG" ] || { echo "Composer installer signature mismatch"; exit 1; }
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

echo ">> Installing MariaDB server…"
apt-get install -y mariadb-server mariadb-client

echo ">> Installing Redis…"
apt-get install -y redis-server
sed -i 's/^supervised .*/supervised systemd/' /etc/redis/redis.conf
sed -i 's/^# *maxmemory-policy .*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
systemctl enable --now redis-server

echo ">> Installing Supervisor…"
apt-get install -y supervisor
systemctl enable --now supervisor

echo ">> Installing Node.js 20 (NodeSource)…"
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | cut -dv -f2 | cut -d. -f1)" -lt 20 ]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

echo ">> Installing Certbot (snap to keep up to date)…"
apt-get install -y certbot python3-certbot-nginx

echo ">> Enabling unattended security updates…"
dpkg-reconfigure -f noninteractive unattended-upgrades

echo ">> Versions:"
nginx -v 2>&1
php -v | head -1
composer --version
mariadb --version
redis-server --version | head -1
supervisord -v
node -v
npm -v
certbot --version

echo "<<< 01-bootstrap.sh complete"
