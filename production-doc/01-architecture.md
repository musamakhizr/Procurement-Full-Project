# 01 · Architecture & Topology

## High-level diagram

```
                     ┌──────────────────────────────────────────────┐
                     │             Internet (HTTPS)                 │
                     └──────────────┬───────────────────────────────┘
                                    │
                          DNS hexugo.com → 47.86.173.194
                                    │
                                    ▼
              ┌───────────────────────────────────────────┐
              │ Alibaba Cloud Security Group              │
              │   inbound: 22, 2222, 80, 443              │
              └─────┬───────────────┬──────────────┬──────┘
                    │               │              │
                    ▼               ▼              ▼
                  UFW            UFW            UFW
                 (allow         (allow         (allow
                  22, 2222)     80, 443)       same)
                    │               │              │
            ┌───────┴──────┐        │              │
            │   sshd       │        ▼              │
            │  (PID file)  │   ┌────────────┐      │
            │  fail2ban    │   │   nginx    │◄─────┘
            └──────────────┘   │   1.24     │
                               │            │
                               │  HTTP→HTTPS│
                               │  www→apex  │
                               │  HTTP/2    │
                               │  HSTS      │
                               │  rate-lmt  │
                               └─────┬──────┘
                                     │
                ┌────────────────────┼─────────────────────┬────────────────┐
                │                    │                     │                │
                ▼                    ▼                     ▼                ▼
       /frontend/dist/*      /api/* + /index.php     /storage/*      /dbmgr-…/*
       (React SPA static)    │                       (uploads)        │
                             │                                        │
                             ▼                                        ▼
                     ┌────────────────┐                       ┌────────────────┐
                     │ PHP-FPM        │                       │ phpMyAdmin     │
                     │ pool: hexugo   │                       │ (htpasswd auth)│
                     │ user: deploy   │                       └────────┬───────┘
                     │ socket: /run/  │                                │
                     │ php-fpm-       │                                │
                     │ hexugo.sock    │                                │
                     └────┬───────┬───┘                                │
                          │       │                                    │
            ┌─────────────┘       │                                    │
            ▼                     ▼                                    ▼
  ┌─────────────────┐     ┌──────────────┐                 ┌──────────────────┐
  │ Redis 7         │     │ MariaDB 10.11│◄────────────────┤ phpMyAdmin reads │
  │ - sessions      │     │ - hexugo_prod│                 │ via 127.0.0.1    │
  │ - cache         │     │ - localhost  │                 └──────────────────┘
  │ - queue         │     │   binding    │
  │ - locks         │     └──────────────┘
  └────────┬────────┘             ▲
           │                      │
           │                      │
   ┌───────┴────────┐             │
   ▼                ▼             │
 ┌──────────────┐ ┌─────────────┐ │
 │ Supervisor   │ │ Supervisor  │ │
 │ queue:work   │ │ schedule:run│ │
 │ (2 workers)  │ │ (every 60s) │ │
 └──────────────┘ └─────────────┘ │
                                  │
                          ┌───────┴─────────┐
                          │ Daily backups   │ (TBD)
                          │ /var/backups    │
                          └─────────────────┘
```

## Component summary

| Component | Version | Role | Bind |
|---|---|---|---|
| Nginx | 1.24.0 (Ubuntu) | TLS termination, reverse proxy, static serving | `:80`, `:443` |
| PHP-FPM | 8.3.30 | App runtime — dedicated `hexugo` pool | unix socket `/run/php/php8.3-fpm-hexugo.sock` |
| MariaDB | 10.11.14 | Persistent storage | `127.0.0.1:3306` |
| Redis | 7.0.15 | Cache, sessions, queue, locks | `127.0.0.1:6379` |
| Supervisor | 4.2.5 | Manages queue workers + scheduler | n/a |
| sshd | OpenSSH 9.x | Remote access | `:22`, `:2222` |
| fail2ban | 1.0.x | Brute-force protection | n/a |
| UFW | latest | Host firewall | n/a |
| Certbot | 2.9.0 | LE cert auto-renew | n/a (timer) |

## Filesystem layout

```
/var/www/hexugo/
├── current               → releases/<TS>     (atomic symlink, what nginx points at)
├── releases/
│   ├── 20260427045423/   (active)
│   ├── 20260427030000/   (previous, kept for rollback)
│   └── 20260426180000/   (older)
└── shared/
    ├── .env              (secrets, mode 640)
    └── storage/
        ├── app/public/
        ├── framework/
        │   ├── cache/
        │   ├── sessions/
        │   └── views/
        └── logs/
            ├── laravel-YYYY-MM-DD.log
            ├── queue-worker.log
            └── scheduler.log

/opt/hexugo-deploy/        (deploy scripts repo, scripts run from here)
/etc/nginx/sites-available/hexugo.conf
/etc/nginx/sites-enabled/hexugo.conf  → ../sites-available/hexugo.conf
/etc/php/8.3/fpm/pool.d/hexugo.conf
/etc/php/8.3/fpm/conf.d/10-opcache-prod.ini
/etc/mysql/mariadb.conf.d/99-hexugo-tuning.cnf
/etc/supervisor/conf.d/hexugo.conf
/etc/ssh/sshd_config.d/99-hexugo-hardening.conf
/etc/letsencrypt/live/hexugo.com/  (cert + key)
/etc/nginx/.htpasswd-pma           (phpMyAdmin basic auth)
```

## Request lifecycle (typical API call)

1. Client → DNS resolves `hexugo.com` → `47.86.173.194:443`
2. **Alibaba SG** allows `:443`
3. **UFW** allows `:443` from world
4. **nginx** terminates TLS using LE cert
5. nginx applies rate-limit zone (`api_general` 60r/s, `api_login` 5r/s)
6. nginx routes path:
   - `/api/*` → `try_files $uri /index.php?$query_string;`
   - `/frontend/dist/assets/*` → static, 1-year cache
   - `/storage/*` → static, 7-day cache
   - `/` and unmatched → React SPA `index.html`
7. PHP-FPM `hexugo` pool worker picks up request
8. Laravel:
   - boots from cached config (`config:cache`, `route:cache`)
   - middleware runs (CORS, throttle, sanctum)
   - controller fires
   - DB via MariaDB (TCP `127.0.0.1:3306`)
   - cache/session via Redis
   - response serialized as JSON
9. nginx adds security headers, gzips response, sends back

## Background processing lifecycle

1. Web request dispatches a job: `SomeJob::dispatch()`
2. Laravel pushes job to **Redis queue** (`hexugo:queues:default`)
3. **Supervisor** is running `php artisan queue:work redis` × 2 workers
4. Worker pops job, runs handler, deletes job on success
5. On failure: 3 retries with 5s backoff, then moves to `failed_jobs` table
6. **Scheduler** runs every 60s; dispatches scheduled tasks per `app/Console/Kernel.php`
