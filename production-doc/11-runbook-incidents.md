# 11 · Incident Runbook

Known failure modes, ordered by frequency. Each section is self-contained — start with the symptom, end with the fix.

---

## A. Site is down (HTTP 5xx everywhere)

### Triage
```bash
# Are services running?
ssh hexugo "systemctl is-active nginx php8.3-fpm mariadb redis-server"

# What does nginx say?
ssh hexugo "sudo tail -50 /var/log/nginx/hexugo.error.log"

# What does PHP-FPM say?
ssh hexugo "sudo tail -50 /var/log/php8.3-fpm-hexugo.error.log"

# What does Laravel say?
ssh hexugo-deploy "tail -50 /var/www/hexugo/shared/storage/logs/laravel-$(date +%F).log"
```

### Common causes / fixes

- **PHP-FPM down** → `sudo systemctl restart php8.3-fpm`
- **Out of memory (OOM)** — check `dmesg`. Reduce `pm.max_children` in `/etc/php/8.3/fpm/pool.d/hexugo.conf` and reload.
- **Bad config cache** — `cd /var/www/hexugo/current && php artisan config:clear && php artisan config:cache && sudo systemctl reload php8.3-fpm`
- **MariaDB down** — `sudo systemctl restart mariadb`. Look at `/var/log/mysql/error.log`.
- **Redis down** — `sudo systemctl restart redis-server`. Sessions go away on restart unless persistence is on (default = RDB snapshots).

---

## B. SSH lockout / can't connect

### Symptoms
- `Connection refused` or `Connection closed by remote host` on `ssh`
- Both `:22` and `:2222` unreachable
- `kex_exchange_identification: read: Connection reset by peer`

### Recovery via Alibaba Cloud VNC console (always works)

1. Log in to Alibaba Cloud → ECS → instance → **Connect → VNC**
2. Login as `root` with the original cloud password (kept on the cloud control plane even after we set passwordless)
3. Run:

```bash
# Make sure no broken sshd
systemctl stop ssh ssh.service ssh.socket 2>/dev/null
pkill -9 sshd 2>/dev/null
sleep 2

# Reset to known-good config
cat > /etc/ssh/sshd_config.d/99-hexugo-hardening.conf <<'EOF'
Port 22
Port 2222
PermitRootLogin prohibit-password
PasswordAuthentication no
PubkeyAuthentication yes
ChallengeResponseAuthentication no
KbdInteractiveAuthentication no
UsePAM yes
X11Forwarding no
AllowUsers root deploy
MaxAuthTries 3
EOF

# Validate
sshd -t && echo OK

# Disable socket activation (Ubuntu 24.04 specific!) and start service
systemctl disable --now ssh.socket
systemctl enable --now ssh.service
sleep 2

# Verify both ports listening
ss -tlnp | grep -E ':22|:2222'
```

### If only `:2222` was killed (your IP is banned by fail2ban)

```bash
# From VNC, or another machine:
sudo fail2ban-client status sshd
sudo fail2ban-client set sshd unbanip <your-ip>
```

### If keys were lost, emergency password recovery

In VNC:
```bash
# Temporarily allow password auth
sed -i 's/^PasswordAuthentication no/PasswordAuthentication yes/' \
  /etc/ssh/sshd_config.d/99-hexugo-hardening.conf
systemctl restart ssh

# Now you can scp a new pubkey:
# (from your local machine)
# ssh-copy-id -p 22 root@47.86.173.194

# Re-disable password auth
sed -i 's/^PasswordAuthentication yes/PasswordAuthentication no/' \
  /etc/ssh/sshd_config.d/99-hexugo-hardening.conf
systemctl restart ssh
```

---

## C. Frontend shows blank white page

### Symptoms
- `https://hexugo.com/` loads HTML but page renders empty
- DevTools Console shows `404` for `/assets/*.js`

### Cause
Vite emits `<script src="/assets/...">` which doesn't match the nginx rule for `/frontend/dist/assets/*`.

### Fix
Confirm `public/frontend/vite.config.ts` has `base: '/frontend/dist/'`. Then:

```bash
ssh hexugo-deploy "cd /var/www/hexugo/current/public/frontend && npm run build"
ssh hexugo "sudo systemctl reload nginx"
```

Verify:
```bash
curl -s https://hexugo.com/ | grep -E '<(script|link)'
# Should show /frontend/dist/assets/...
```

---

## D. SSL cert expiring / expired

### Check
```bash
ssh hexugo "sudo openssl x509 -enddate -noout -in /etc/letsencrypt/live/hexugo.com/fullchain.pem"
```

### Renew
```bash
ssh hexugo "sudo certbot renew --force-renewal && sudo systemctl reload nginx"
```

If renewal fails because nginx is misconfigured: check ACME challenge path is reachable via HTTP:
```bash
curl -v http://hexugo.com/.well-known/acme-challenge/test
```

---

## E. Queue jobs not running

### Triage
```bash
ssh hexugo "sudo supervisorctl status hexugo-queue:*"
ssh hexugo-deploy "tail -50 /var/www/hexugo/shared/storage/logs/queue-worker.log"
ssh hexugo "redis-cli -h 127.0.0.1 llen 'queues:default'"
```

### Common causes

- **Workers stopped** → `sudo supervisorctl restart hexugo-queue:*`
- **Redis down** → see section A
- **Worker has stale code** → restart workers (`max-time=3600` should self-restart hourly anyway)
- **Pile of failed jobs** → `php artisan queue:failed` then `queue:retry all` or `queue:flush`

---

## F. MariaDB out of connections

### Symptom
`SQLSTATE[HY000] [1040] Too many connections`

### Triage
```bash
ssh hexugo "mariadb -u root -e 'SHOW PROCESSLIST;' | head -50"
ssh hexugo "mariadb -u root -e 'SHOW STATUS LIKE \"Threads_connected\";'"
```

### Fix
- Kill long-running queries: `KILL <id>` per process list
- Bump `max_connections` in `/etc/mysql/mariadb.conf.d/99-hexugo-tuning.cnf` (default 200), restart mariadb
- If consistently high: investigate slow queries via `/var/log/mysql/slow.log`

---

## G. Disk full

```bash
ssh hexugo "df -hT && du -sh /var/log /var/www /var/lib/mysql /var/lib/redis /home"
```

Typical culprits and cleanup:

| Path | Cleanup |
|---|---|
| `/var/log/*` | `journalctl --vacuum-time=14d` and rotate logs (`logrotate -f /etc/logrotate.conf`) |
| `/var/www/hexugo/releases/` | Reduce KEEP_RELEASES, prune: `ls -1dt releases/*/ \| tail -n +4 \| xargs rm -rf` |
| `/var/lib/mysql/binlog.*` (if binlog enabled) | `PURGE BINARY LOGS BEFORE NOW() - INTERVAL 7 DAY;` |
| `/tmp/*` | `find /tmp -type f -mtime +7 -delete` |

---

## H. fail2ban banned a legitimate IP

```bash
# Find the IP in the ban list
ssh hexugo "sudo fail2ban-client status sshd"
# Or
ssh hexugo "sudo zgrep 'Ban <ip>' /var/log/fail2ban.log*"

# Unban
ssh hexugo "sudo fail2ban-client set sshd unbanip 1.2.3.4"

# Add to permanent allowlist
ssh hexugo "sudo sed -i 's|^ignoreip.*|ignoreip = 127.0.0.1/8 ::1 1.2.3.4|' /etc/fail2ban/jail.local"
ssh hexugo "sudo systemctl reload fail2ban"
```

---

## I. Deploy failed mid-flight

The `current` symlink only swaps at the very end of `deploy.sh`. If anything failed before the swap, the previous release is still active and serving traffic. Just fix and re-run `deploy.sh`.

If the swap happened but workers/php-fpm didn't reload:
```bash
ssh hexugo-deploy "sudo /usr/bin/systemctl reload php8.3-fpm && sudo /usr/bin/supervisorctl restart hexugo-queue:*"
```

If migration ran but app is broken:
```bash
ssh hexugo-deploy "bash /opt/hexugo-deploy/deploy/scripts/rollback.sh"
# then if needed:
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan migrate:rollback --step=1"
```

---

## J. phpMyAdmin won't load

### Common causes
- Wrong basic-auth creds → check `/etc/nginx/.htpasswd-pma` (`htpasswd -B -v` to verify)
- `/etc/phpmyadmin/config.inc.php` has invalid PHP → `php -l /etc/phpmyadmin/config.inc.php`
- nginx location block wrong → `sudo nginx -T | grep -A 30 dbmgr-`
- Permission on htpasswd → `chmod 640 /etc/nginx/.htpasswd-pma; chown root:www-data ...`

---

## K. Site shows Laravel welcome page instead of React

### Symptom
`https://hexugo.com/` shows the default Laravel welcome page (gradient + Laravel logo).

### Cause
nginx `try_files` is matching `/index.php` before `/frontend/dist/index.html` for the `/` location.

### Fix
Verify the server block has BOTH:
```nginx
location = / {
    try_files /frontend/dist/index.html =404;
}
location / {
    try_files $uri /frontend/dist/index.html;
}
```
And **NOT** an `index index.php index.html;` directive that promotes index.php for `/`.

---

## L. Anything else

1. Check `journalctl -xe --no-pager | tail -100` for the most recent system errors
2. Check `tail /var/log/syslog`
3. Look at the app log: `/var/www/hexugo/shared/storage/logs/laravel-YYYY-MM-DD.log`
4. If desperate: roll back (`bash /opt/hexugo-deploy/deploy/scripts/rollback.sh`)
