#!/usr/bin/env bash
# 06-nginx-ssl.sh — Install nginx server block, obtain LE cert, enable HTTPS
# Pre-req: $LE_EMAIL env var
set -euo pipefail

: "${LE_EMAIL:?LE_EMAIL required}"

echo ">> Installing PHP-FPM hexugo pool…"
install -m 644 /opt/hexugo-deploy/templates/php-fpm-hexugo.conf /etc/php/8.3/fpm/pool.d/hexugo.conf
install -m 644 /opt/hexugo-deploy/templates/opcache.ini /etc/php/8.3/fpm/conf.d/10-opcache-prod.ini
# Disable default www pool to free RAM
mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.disabled 2>/dev/null || true
systemctl restart php8.3-fpm

echo ">> Preparing ACME webroot…"
install -d -o www-data -g www-data /var/www/letsencrypt

echo ">> Installing nginx server block…"
install -m 644 /opt/hexugo-deploy/templates/nginx-hexugo.conf /etc/nginx/sites-available/hexugo.conf
ln -snf /etc/nginx/sites-available/hexugo.conf /etc/nginx/sites-enabled/hexugo.conf
rm -f /etc/nginx/sites-enabled/default

# --- bootstrap stage: serve plain HTTP only (no SSL yet) ---
TMP_HTTP="/etc/nginx/sites-available/hexugo-http-bootstrap.conf"
cat > "${TMP_HTTP}" <<'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name hexugo.com www.hexugo.com;
    root /var/www/letsencrypt;

    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 200 "ok"; add_header Content-Type text/plain; }
}
EOF
ln -snf "${TMP_HTTP}" /etc/nginx/sites-enabled/00-bootstrap.conf
mv /etc/nginx/sites-enabled/hexugo.conf /etc/nginx/sites-enabled/hexugo.conf.disabled

nginx -t
systemctl reload nginx

echo ">> Requesting Let's Encrypt cert via webroot…"
certbot certonly --webroot -w /var/www/letsencrypt \
    -d hexugo.com -d www.hexugo.com \
    --non-interactive --agree-tos --email "${LE_EMAIL}" \
    --rsa-key-size 4096

echo ">> Switching to full HTTPS server block…"
rm -f /etc/nginx/sites-enabled/00-bootstrap.conf
mv /etc/nginx/sites-enabled/hexugo.conf.disabled /etc/nginx/sites-enabled/hexugo.conf

nginx -t
systemctl reload nginx

echo ">> Setting up cert auto-renew (systemd timer is built-in for certbot)…"
systemctl enable --now certbot.timer

echo "<<< 06-nginx-ssl.sh complete"
