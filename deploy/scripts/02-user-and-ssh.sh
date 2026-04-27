#!/usr/bin/env bash
# 02-user-and-ssh.sh — Create deploy user, install pubkey, harden sshd
# Pre-req: $DEPLOY_PUBKEY env var holds the ssh public key
set -euo pipefail

: "${DEPLOY_PUBKEY:?DEPLOY_PUBKEY env var required}"

DEPLOY_USER="deploy"

echo ">> Creating user '$DEPLOY_USER' if missing…"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    useradd -m -s /bin/bash -G sudo,www-data "$DEPLOY_USER"
    # Lock password (key-only login). Sudo requires NOPASSWD below.
    passwd -l "$DEPLOY_USER"
fi

# Make sure deploy is in www-data group (Laravel storage perms)
usermod -aG www-data "$DEPLOY_USER"

echo ">> Installing pubkey for $DEPLOY_USER…"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
AUTH_KEYS="/home/$DEPLOY_USER/.ssh/authorized_keys"
touch "$AUTH_KEYS"
grep -qxF "$DEPLOY_PUBKEY" "$AUTH_KEYS" || echo "$DEPLOY_PUBKEY" >> "$AUTH_KEYS"
chown "$DEPLOY_USER:$DEPLOY_USER" "$AUTH_KEYS"
chmod 600 "$AUTH_KEYS"

echo ">> Sudoers: allow deploy NOPASSWD for systemctl reload of php/nginx/supervisor…"
cat > /etc/sudoers.d/deploy <<'EOF'
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.3-fpm
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx
deploy ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl *
deploy ALL=(ALL) NOPASSWD: /usr/bin/nginx -t
EOF
chmod 440 /etc/sudoers.d/deploy
visudo -cf /etc/sudoers.d/deploy

echo ">> Hardening sshd (port 2222, no password, no challenge)…"
install -m 644 /opt/hexugo-deploy/templates/sshd-hexugo.conf /etc/ssh/sshd_config.d/99-hexugo-hardening.conf
sshd -t  # validate before restart
systemctl restart ssh

echo "<<< 02-user-and-ssh.sh complete (sshd now on port 2222)"
