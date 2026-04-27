# 03 · Security Model

## Threat model (in scope)

| Threat | Mitigation |
|---|---|
| Brute-force SSH login | key-only auth, fail2ban, password auth disabled |
| Compromised key | per-user keypairs, easy rotation; root login limited to `prohibit-password` (still key-only) |
| Web application bugs | rate limits, CORS scoped to `hexugo.com`, Laravel security defaults, prepared statements |
| Eavesdropping | TLS 1.2/1.3 only, HSTS preload, modern cipher suite |
| Clickjacking, MIME sniffing, XSS reflection | `X-Frame-Options`, `X-Content-Type-Options`, CSP-equivalent headers |
| Direct DB access from outside | MariaDB bound to `127.0.0.1` only, no remote root |
| Bot/spam to login endpoint | nginx `limit_req` `5r/s` for `/api/auth/login`, `60r/s` for general API |
| Lateral movement after web compromise | PHP-FPM runs as `deploy`, can only access app dirs; sudo NOPASSWD limited to whitelist |

## Out of scope (or planned)

- DDoS mitigation at L4 → handled by Alibaba Cloud network-layer DDoS basic protection (default)
- WAF rules → not deployed; consider Cloudflare in front later
- Sentry / SIEM → DSN slot exists in `.env` but not populated
- Secrets rotation automation → manual
- 2FA on phpMyAdmin → not configured (htpasswd only)

## SSH

| Setting | Value | File |
|---|---|---|
| Listening ports | `22` and `2222` | `/etc/ssh/sshd_config.d/99-hexugo-hardening.conf` |
| Root login | `yes` (password OR key) | same |
| Password auth | `yes` ⚠️ (legacy compatibility for existing users) | same |
| Pubkey auth | `yes` | same |
| KbdInteractive | `no` | same |
| AllowUsers | `root deploy` | same |
| MaxAuthTries | `3` | same |
| ClientAliveInterval | `300` | same |
| Service mode | `ssh.service` (NOT socket-activated) | systemd |
| fail2ban sshd | ban 24h after 3 failures in 10m | `/etc/fail2ban/jail.local` |

> ⚠️ **Note on password auth**: re-enabled at user request to preserve access for existing users with only the root password. Compensated with stricter fail2ban (3 failures = 24h ban). To return to key-only later: change `PasswordAuthentication yes` → `no` in the override file and reload sshd.

To **add a new SSH user**:

```bash
ssh hexugo
useradd -m -s /bin/bash -G sudo,www-data alice
install -d -m 700 -o alice -g alice /home/alice/.ssh
echo 'ssh-ed25519 AAAA…' | install -m 600 -o alice -g alice /dev/stdin /home/alice/.ssh/authorized_keys
# Add to AllowUsers
sed -i 's/^AllowUsers .*/& alice/' /etc/ssh/sshd_config.d/99-hexugo-hardening.conf
systemctl reload ssh
```

To **rotate the deploy keypair**: generate new key locally, append pubkey to `/home/deploy/.ssh/authorized_keys`, verify new key works, then remove old key from authorized_keys.

## fail2ban

Watches:
- `sshd` jail — bans for 24h after 3 failed attempts inside 10m
- `nginx-http-auth` jail — bans for 1h after 5 failures
- `nginx-limit-req` jail — bans for 1h after 10 rate-limit triggers in 10m

Manual ban / unban:
```bash
sudo fail2ban-client status sshd          # see banned IPs
sudo fail2ban-client set sshd unbanip 1.2.3.4
sudo fail2ban-client set sshd banip   1.2.3.4
```

## UFW (host firewall)

```
default: deny incoming, allow outgoing
2222/tcp ALLOW   (SSH custom port)
22/tcp   ALLOW   (SSH legacy port)
80/tcp   ALLOW   (HTTP, used by Certbot + redirect to HTTPS)
443/tcp  ALLOW   (HTTPS)
```

## Sudo policy for `deploy`

`/etc/sudoers.d/deploy`:

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.3-fpm
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx
deploy ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl *
deploy ALL=(ALL) NOPASSWD: /usr/bin/nginx -t
```

`deploy` cannot run arbitrary `sudo`. To do anything else, log in as `root`.

## TLS

| | |
|---|---|
| Issuer | Let's Encrypt (R3) |
| Cert path | `/etc/letsencrypt/live/hexugo.com/fullchain.pem` |
| Key path  | `/etc/letsencrypt/live/hexugo.com/privkey.pem` |
| Renewal  | `certbot.timer` (twice daily, renews if <30d to expiry) |
| Protocols | TLSv1.2, TLSv1.3 only |
| Cipher suite | AEAD only (ECDHE-ECDSA-AES256-GCM-SHA384, ECDHE-RSA-AES256-GCM-SHA384, ChaCha20) |
| Session tickets | off |
| OCSP stapling | enabled (warns but works) |
| HSTS | `max-age=31536000; includeSubDomains; preload` |

Manual renew test:
```bash
sudo certbot renew --dry-run
```

## Application-level security

- **Sanctum** stateful domains restricted to `hexugo.com,www.hexugo.com`
- **Session cookies** marked `Secure`, `SameSite=Lax`, `HttpOnly` (Laravel default)
- **CSRF** protected on all `web` routes; API uses bearer tokens (no CSRF needed)
- **CORS** strict — same origin
- **bcrypt** rounds = 12 for password hashing
- **APP_DEBUG** = `false` in `.env`
- **APP_ENV** = `production`

## Database security

- MariaDB bound to `127.0.0.1` only
- `hexugo_user` granted only `hexugo_prod.*` (no `*.*`, no GRANT OPTION)
- Anonymous users dropped, test database dropped
- `root` accessible via `unix_socket` from local TTY OR password (recovery)
- `mysql_native_password` used for root password (compatibility)

## phpMyAdmin

Defense in depth:
1. **Secret URL prefix** `/dbmgr-4591d95d/` — not discoverable by `/phpmyadmin` scanners
2. **HTTP Basic Auth** at nginx level (htpasswd file)
3. **PMA cookie auth** with bcrypted blowfish secret
4. Only over **HTTPS** (HTTP redirects to HTTPS at nginx layer)
5. `information_schema`, `mysql`, `performance_schema`, `sys` databases hidden

To rotate the htpasswd:
```bash
sudo htpasswd -B /etc/nginx/.htpasswd-pma hexadmin
```

To change the URL prefix: edit `deploy/templates/nginx-hexugo.conf` (search for `dbmgr-`), commit, redeploy.
