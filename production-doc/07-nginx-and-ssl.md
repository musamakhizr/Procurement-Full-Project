# 07 · Nginx & TLS

## Files

| Path | Purpose |
|---|---|
| `/etc/nginx/nginx.conf` | Main config (default Ubuntu) |
| `/etc/nginx/sites-available/hexugo.conf` | Hexugo server block (managed via repo template) |
| `/etc/nginx/sites-enabled/hexugo.conf` | Symlink → above |
| `/etc/nginx/.htpasswd-pma` | Basic-auth file for phpMyAdmin |
| `/etc/letsencrypt/live/hexugo.com/` | TLS cert + key |
| `/var/www/letsencrypt/` | ACME challenge webroot |
| `/var/log/nginx/hexugo.access.log` | Access log |
| `/var/log/nginx/hexugo.error.log` | Error log |

The source-of-truth nginx template is **`deploy/templates/nginx-hexugo.conf`** in the repo. To change the config:

1. Edit the template
2. Commit + push
3. On server: `sudo bash /opt/hexugo-deploy/deploy/scripts/06-nginx-ssl.sh` OR manually:
   ```bash
   sudo install -m 644 /opt/hexugo-deploy/deploy/templates/nginx-hexugo.conf /etc/nginx/sites-available/hexugo.conf
   sudo nginx -t && sudo systemctl reload nginx
   ```

## Server blocks

The template defines **three** server blocks:

| Listen | Server names | Purpose |
|---|---|---|
| `:80` | hexugo.com, www.hexugo.com | ACME challenge + HTTP→HTTPS 301 |
| `:443 ssl http2` | www.hexugo.com | www→apex 301 |
| `:443 ssl http2` | hexugo.com | Main app |

## TLS

| | |
|---|---|
| Cert | `/etc/letsencrypt/live/hexugo.com/fullchain.pem` |
| Key | `/etc/letsencrypt/live/hexugo.com/privkey.pem` |
| Protocols | TLSv1.2 + TLSv1.3 only |
| Ciphers | AEAD-only modern suite (no RSA-only key exchange) |
| Session tickets | off |
| Stapling | on |
| HSTS | `max-age=31536000; includeSubDomains; preload` |

Renewal is automatic via `certbot.timer` (runs twice daily, renews if cert is < 30 days from expiry). Test:

```bash
ssh hexugo "sudo certbot renew --dry-run"
```

After renewal, certbot's nginx hook auto-reloads. If you change the cert manually:
```bash
sudo systemctl reload nginx
```

## Security headers

Sent on every HTTPS response:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
X-XSS-Protection: 1; mode=block
```

> A Content-Security-Policy is **not** set (would need careful per-page configuration with React + inline styles). Add in a future hardening pass.

## Rate limiting

Two zones declared at top of the config:

```nginx
limit_req_zone $binary_remote_addr zone=api_general:10m rate=60r/s;
limit_req_zone $binary_remote_addr zone=api_login:10m   rate=5r/s;
```

Applied:

| Path | Zone | Burst |
|---|---|---|
| `/api/auth/login` | `api_login` | 10 |
| `/api/*` (other) | `api_general` | 120 |

When clients exceed limits, nginx returns `503` and fail2ban (`nginx-limit-req` jail) bans the IP after 10 such errors in 10 minutes.

## Compression

Gzip enabled for text-like MIME types:

```
gzip on;
gzip_min_length 1024;
gzip_comp_level 6;
gzip_types text/plain text/css application/javascript application/json …
```

> Brotli is **not** enabled. To add: `apt install nginx-module-brotli`, load module, add `brotli on; brotli_types …;` in server block.

## Caching headers

| Path | Cache-Control |
|---|---|
| `/frontend/dist/assets/*` | `public, immutable, max-age=31536000` |
| `/storage/*` | `expires 7d` (default `public`) |
| API responses | none (Laravel-controlled) |

## File / body limits

```
client_max_body_size 25M
client_body_buffer_size 128k
client_header_buffer_size 4k
large_client_header_buffers 4 16k
keepalive_timeout 65
```

PHP-FPM limits match (`upload_max_filesize=25M`, `post_max_size=25M`).

## PHP-FPM pool

Dedicated pool isolates Hexugo from any other site. File: `/etc/php/8.3/fpm/pool.d/hexugo.conf`. Key params:

| Setting | Value | Why |
|---|---|---|
| `user` / `group` | `deploy` / `www-data` | Process isolation |
| `listen` | `/run/php/php8.3-fpm-hexugo.sock` | Unix socket (faster than TCP) |
| `pm` | `dynamic` | Scale workers with load |
| `pm.max_children` | 40 | ~256MB×40 = 10GB headroom on 14GB box |
| `pm.start_servers` | 6 | Warm pool |
| `pm.min_spare_servers` | 4 | Always have headroom |
| `pm.max_spare_servers` | 10 | Reap idle |
| `pm.max_requests` | 500 | Recycle workers (memory leak guard) |
| `request_terminate_timeout` | 60s | Kill stuck requests |
| `slowlog_timeout` | 10s | Anything ≥ 10s logged |
| `memory_limit` | 256M | Per-request |

Reload (after config change):
```bash
sudo systemctl reload php8.3-fpm
```

## OPcache

Enabled in `/etc/php/8.3/fpm/conf.d/10-opcache-prod.ini`:

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0     ← prod: no per-request stat()
opcache.jit=tracing
opcache.jit_buffer_size=64M
```

> Because `validate_timestamps=0`, file edits don't take effect until you **reload PHP-FPM**. The deploy script does this automatically. If you SSH-edit code in `current/`, run `sudo systemctl reload php8.3-fpm`.

## Common operations

### See the live nginx config
```bash
ssh hexugo "sudo nginx -T 2>&1 | less"
```

### Reload after editing
```bash
ssh hexugo "sudo nginx -t && sudo systemctl reload nginx"
```

### Inspect access patterns
```bash
ssh hexugo "sudo tail -f /var/log/nginx/hexugo.access.log"
```

### Top error sources
```bash
ssh hexugo "sudo awk '\$9>=500 {print \$7}' /var/log/nginx/hexugo.access.log | sort | uniq -c | sort -rn | head"
```

### Connections by IP (find abusive)
```bash
ssh hexugo "ss -tn state established | awk '{print \$5}' | cut -d: -f1 | sort | uniq -c | sort -rn | head"
```
