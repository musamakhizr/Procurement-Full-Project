# 12 · Credentials Index

> **No secret values are stored in this repo.** This document tells you *where* each credential lives. The actual values are in `deploy/CREDENTIALS.local` (gitignored) on the developer machine that originally provisioned the server.

| Credential | Where the value lives | Used by |
|---|---|---|
| Server SSH (root password) | Alibaba Cloud ECS console (initial provision; never used after key-only is in place) | Recovery only |
| Server SSH (deploy private key) | Local: `~/.ssh/hexugo_deploy` <br> Server pubkeys: `/root/.ssh/authorized_keys`, `/home/deploy/.ssh/authorized_keys` | Daily ops |
| MariaDB root password | `deploy/CREDENTIALS.local` → `MYSQL_ROOT_PASS` | Emergency only — daily access uses `unix_socket` from a root shell |
| MariaDB app password | `deploy/CREDENTIALS.local` → `DB_PASS` <br> Also in `/var/www/hexugo/shared/.env` → `DB_PASSWORD` | Laravel app |
| Laravel `APP_KEY` | `/var/www/hexugo/shared/.env` → `APP_KEY` | Symmetric encryption (cookies, signed URLs) |
| Sanctum tokens | `personal_access_tokens` table | Per-user API auth |
| Let's Encrypt account email | `usamakhizarazlaantech@gmail.com` (registered with LE) | Cert renewal notifications |
| TLS private key | `/etc/letsencrypt/live/hexugo.com/privkey.pem` (mode 600, owner root) | nginx |
| phpMyAdmin htpasswd password | `deploy/CREDENTIALS.local` → `PMA_BASIC_PASS` <br> Hashed in `/etc/nginx/.htpasswd-pma` | Outer auth gate |
| phpMyAdmin URL prefix | `deploy/templates/nginx-hexugo.conf` (`dbmgr-…` slug) | Browser URL |
| phpMyAdmin blowfish secret | `/etc/phpmyadmin/config.inc.php` | PMA cookie signing |
| Sentry DSN | (placeholder) `/var/www/hexugo/shared/.env` → `SENTRY_LARAVEL_DSN` | Error tracking |
| GitHub repo deploy key (CI/CD) | (TBD when CI/CD is wired) GitHub Actions Secrets | Pull on deploy |

## Generating a new strong secret

```bash
openssl rand -base64 32 | tr -d '/+=' | head -c 28
```

For password fields. For Laravel APP_KEY:
```bash
php artisan key:generate --show
```

## Rotation policy (recommended)

| Secret | Rotate every | How |
|---|---|---|
| MariaDB app password | 90 days | Update `.env` → `php artisan config:cache` → `ALTER USER` in MariaDB |
| MariaDB root password | yearly | `ALTER USER 'root'@'localhost' IDENTIFIED BY '...'` |
| phpMyAdmin htpasswd | 90 days | `htpasswd -B /etc/nginx/.htpasswd-pma hexadmin` |
| Deploy SSH key | 12 months OR on team change | New keypair → add new pubkey → verify → remove old |
| Sentry DSN | only if leaked | Regenerate in Sentry dashboard |
| Seeded test/admin user passwords | **immediately** in production | They are `password` (literal) — change before opening to real users |

## Where credentials must NEVER appear

- ❌ Repository (any branch)
- ❌ Git commit messages
- ❌ Slack / chat / email
- ❌ Browser bookmarks
- ❌ Logs (Laravel masks `password`/`token` in logs by default; verify if changing)
- ❌ Frontend bundles (use only `VITE_API_BASE_URL` and the like — never paste server-side secrets in `VITE_*` vars)

## Where they're allowed

- ✅ `deploy/CREDENTIALS.local` (gitignored, on the trusted dev machine)
- ✅ `/var/www/hexugo/shared/.env` (mode 640, owner deploy:www-data)
- ✅ Cloud provider secrets manager (recommended next step — Alibaba Cloud KMS or AWS Secrets Manager)
- ✅ A team password vault (1Password, Bitwarden, Vaultwarden)

## Emergency: forgot the deploy key passphrase

The Hexugo deploy key (`~/.ssh/hexugo_deploy`) was generated with **no passphrase** for automated workflows. If you regenerate with a passphrase, you must store it in a secret manager and ensure CI can unlock it (`ssh-agent` + `SSH_AUTH_SOCK`).

If the key is lost entirely:

1. From Alibaba VNC (see Runbook section B), regenerate a key on a trusted machine
2. Append the new pubkey to `/root/.ssh/authorized_keys` and `/home/deploy/.ssh/authorized_keys`
3. Verify: `ssh -i new-key root@47.86.173.194`
4. Remove the old pubkey from both `authorized_keys` files
