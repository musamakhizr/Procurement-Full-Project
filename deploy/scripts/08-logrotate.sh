#!/usr/bin/env bash
# 08-logrotate.sh — install logrotate config for laravel + php-fpm
set -euo pipefail

install -m 644 /opt/hexugo-deploy/templates/logrotate-hexugo /etc/logrotate.d/hexugo
logrotate -d /etc/logrotate.d/hexugo 2>&1 | head -30 || true

echo "<<< 08-logrotate.sh complete"
