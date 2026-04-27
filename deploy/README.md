# Hexugo — Production Deployment

Production infrastructure for **https://hexugo.com** running on Ubuntu 24.04 LTS (ARM64).

## Stack

| Layer | Tech |
|---|---|
| Reverse proxy / TLS | nginx 1.24 + Let's Encrypt |
| Application | PHP 8.3-FPM (dedicated pool) + Laravel 12 |
| Frontend | React 18 + Vite 6 (built into `public/frontend/dist`) |
| Database | MariaDB 10.11 (bound to localhost) |
| Cache / Session / Queue | Redis 7 |
| Queue worker | Supervisor → `php artisan queue:work redis` |
| SSL | Certbot + auto-renew via `certbot.timer` |
| Firewall | UFW (deny inbound except 2222/80/443) |
| Intrusion prevention | fail2ban (sshd, nginx-limit-req) |
| SSH | Port 2222, key-only, root prohibit-password |

## Folder layout on server

```
/var/www/hexugo/
├── current -> releases/<TS>/         # atomic symlink
├── releases/
│   ├── 20260426_211530/               # newest
│   ├── 20260426_201112/
│   └── 20260425_180000/
└── shared/
    ├── .env                           # production secrets (mode 640)
    └── storage/                       # logs, framework cache, sessions
```

## Files in this directory

```
deploy/
├── README.md                          # this file
├── scripts/
│   ├── 01-bootstrap.sh                # install nginx/php/mariadb/redis/etc
│   ├── 02-user-and-ssh.sh             # create deploy user + harden sshd
│   ├── 03-firewall.sh                 # UFW + fail2ban
│   ├── 04-database.sh                 # secure MariaDB + create app db
│   ├── 05-app-bootstrap.sh            # first deploy of the app
│   ├── 06-nginx-ssl.sh                # nginx vhost + LE cert
│   ├── 07-supervisor.sh               # queue worker + scheduler
│   ├── 08-logrotate.sh                # logrotate configs
│   ├── deploy.sh                      # zero-downtime release deploy
│   └── rollback.sh                    # roll back to previous release
└── templates/
    ├── nginx-hexugo.conf              # nginx server block
    ├── php-fpm-hexugo.conf            # PHP-FPM pool
    ├── opcache.ini                    # OPcache prod config
    ├── mariadb-tuning.cnf             # MariaDB tuning
    ├── supervisor-hexugo.conf         # queue/scheduler workers
    ├── env.production.template        # .env scaffold
    ├── sshd-hexugo.conf               # hardened sshd_config
    ├── fail2ban-jail.local            # fail2ban jails
    └── logrotate-hexugo               # log rotation
```

## First-time deploy (operator runbook)

All scripts assume they are pulled into `/opt/hexugo-deploy` on the server.

```bash
# 1. SSH as root (or via existing key)
ssh -p 2222 deploy@hexugo.com   # or root pre-hardening on port 22

# 2. Pull this repo
git clone https://github.com/musamakhizr/Procurement-Full-Project.git /opt/hexugo-deploy
cd /opt/hexugo-deploy/deploy/scripts

# 3. Run in order
sudo bash 01-bootstrap.sh
DEPLOY_PUBKEY="ssh-ed25519 AAAA…" sudo -E bash 02-user-and-ssh.sh
sudo bash 03-firewall.sh
DB_NAME=hexugo_prod DB_USER=hexugo_user DB_PASS='STRONG_PW' MYSQL_ROOT_PASS='ROOT_PW' \
    sudo -E bash 04-database.sh
REPO_URL=https://github.com/musamakhizr/Procurement-Full-Project.git \
    DB_NAME=hexugo_prod DB_USER=hexugo_user DB_PASS='STRONG_PW' \
    sudo -E bash 05-app-bootstrap.sh
LE_EMAIL=usamakhizarazlaantech@gmail.com sudo -E bash 06-nginx-ssl.sh
sudo bash 07-supervisor.sh
sudo bash 08-logrotate.sh
```

## Subsequent deploys

```bash
ssh -p 2222 deploy@hexugo.com
bash /opt/hexugo-deploy/deploy/scripts/deploy.sh
```

Roll back the previous release with:

```bash
bash /opt/hexugo-deploy/deploy/scripts/rollback.sh
```

## Security notes

- Root login is **prohibit-password** (key only). Day-to-day work happens as `deploy`.
- `deploy` has password-locked account; sudo NOPASSWD is restricted to a tiny whitelist (php-fpm reload, nginx reload, supervisorctl, nginx -t).
- MariaDB `root` is unix-socket auth; password backup exists for emergency.
- Sanctum is configured for `hexugo.com` and `www.hexugo.com` only.
- Sentry DSN slot exists in `.env` — fill in to enable error tracking.

## Health check

```bash
curl -fsS https://hexugo.com/api/categories | jq length
```

## CI/CD ready

Add a GitHub Actions workflow that runs:

```yaml
- name: Deploy
  run: ssh -p 2222 deploy@hexugo.com '/opt/hexugo-deploy/deploy/scripts/deploy.sh'
```

with the `hexugo_deploy` private key stored as a GitHub Actions secret.
