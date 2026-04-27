#!/usr/bin/env bash
# 03-firewall.sh — UFW rules + fail2ban
set -euo pipefail

echo ">> Configuring UFW…"
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 2222/tcp comment 'SSH (custom port)'
ufw allow 80/tcp   comment 'HTTP (Certbot + redirect)'
ufw allow 443/tcp  comment 'HTTPS'
ufw --force enable
ufw status verbose

echo ">> Configuring fail2ban…"
install -m 644 /opt/hexugo-deploy/templates/fail2ban-jail.local /etc/fail2ban/jail.local
systemctl enable --now fail2ban
systemctl restart fail2ban
sleep 2
fail2ban-client status

echo "<<< 03-firewall.sh complete"
