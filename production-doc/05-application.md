# 05 · Laravel Application

## Stack

| | |
|---|---|
| Framework | Laravel 12.56 |
| PHP | 8.3.30 (FPM, dedicated `hexugo` pool) |
| Auth | Laravel Sanctum 4 (bearer tokens) |
| Cache / Session / Queue | Redis 7 |
| Mail | `log` driver (placeholder — switch to SMTP/SES when needed) |

## Directory layout (server)

```
/var/www/hexugo/current/             ← symlink to releases/<TS>/
├── app/                             (controllers, models, middleware)
├── bootstrap/                       (cache/* writable)
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/                          ← nginx document root
│   ├── index.php                    (Laravel front controller)
│   ├── frontend/
│   │   └── dist/                    (built React assets)
│   └── storage → ../storage/app/public/
├── routes/
│   ├── api.php                      (most routes here)
│   ├── web.php
│   └── console.php
├── storage → /var/www/hexugo/shared/storage/   (symlink)
├── .env    → /var/www/hexugo/shared/.env       (symlink)
└── vendor/
```

## Environment file (`.env`)

Lives at **`/var/www/hexugo/shared/.env`** (mode `640`, owner `deploy:www-data`). Gets symlinked into each release as `release/.env`. Template: `deploy/templates/env.production.template`.

Key settings:

```ini
APP_NAME=Hexugo
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hexugo.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=hexugo_prod
DB_USERNAME=hexugo_user
DB_PASSWORD=<random>

SESSION_DRIVER=redis
SESSION_DOMAIN=.hexugo.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=hexugo.com,www.hexugo.com
FRONTEND_URL=https://hexugo.com

VITE_API_BASE_URL=https://hexugo.com/api

# Sentry — slot exists, fill DSN to activate
SENTRY_LARAVEL_DSN=
```

## After editing `.env`

```bash
ssh hexugo-deploy
cd /var/www/hexugo/current
php artisan config:cache
sudo /usr/bin/systemctl reload php8.3-fpm
```

## Cached artifacts (cleared on every deploy)

| Command | Effect |
|---|---|
| `php artisan config:cache` | Merges all `config/*` into `bootstrap/cache/config.php` |
| `php artisan route:cache` | Compiles route definitions |
| `php artisan view:cache` | Pre-compiles Blade templates |
| `php artisan event:cache` | Pre-resolves event listeners |

## Bootstrap customizations (`bootstrap/app.php`)

- **Middleware alias** `admin` → `EnsureUserIsAdmin::class`
- **`redirectGuestsTo`** returns `null` for `/api/*` (so Sanctum auth failures surface as 401 JSON instead of redirecting to a non-existent `login` route)
- **AuthenticationException render handler** returns `{"message":"Unauthenticated."}` JSON 401 for API requests

## Queue & background jobs

The job layer uses Redis (key prefix `hexugo`, db `0`, queue `default`). Two workers run permanently under Supervisor:

```ini
[program:hexugo-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/hexugo/current/artisan queue:work redis
        --sleep=3 --tries=3 --max-time=3600 --backoff=5
        --queue=high,default,low
numprocs=2
user=deploy
autostart=true
autorestart=true
```

Manage:
```bash
sudo supervisorctl status
sudo supervisorctl restart hexugo-queue:*
sudo supervisorctl tail -f hexugo-queue:hexugo-queue_00 stdout
```

After deploy: `supervisorctl restart hexugo-queue:*` — important so workers pick up code changes.

### Failed jobs

```bash
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan queue:failed"
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan queue:retry all"
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan queue:flush"   # purge
```

## Scheduler

Runs every 60 seconds (managed by Supervisor `hexugo-schedule` program). Add scheduled tasks in `app/Console/Kernel.php` (Laravel 12 uses `routes/console.php` as well).

## Sanctum

- All authenticated routes use `auth:sanctum`
- Tokens issued via `POST /api/auth/login`, returned as `token` field
- Tokens stored in `personal_access_tokens` table
- Stateful (cookie) auth limited to domains in `SANCTUM_STATEFUL_DOMAINS`

## Maintenance mode

```bash
ssh hexugo-deploy "/var/www/hexugo/current/artisan down --render='errors::503' --refresh=15"
# work on it…
ssh hexugo-deploy "/var/www/hexugo/current/artisan up"
```

The `deploy.sh` script does this around migrations automatically.

## Storage / file uploads

`storage/app/public/` is symlinked to `public/storage` so files are accessible at `https://hexugo.com/storage/<file>`. Already linked at deploy time via `php artisan storage:link`.

## Adding/updating a route

1. Edit `routes/api.php` (or `routes/web.php`)
2. Test locally
3. Commit + push
4. Run `bash /opt/hexugo-deploy/deploy/scripts/deploy.sh` on server (or wait for CI)
5. Deploy script automatically refreshes the route cache
