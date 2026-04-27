# 08 · Deploy & Rollback

## Mental model

Hexugo uses a **release-based, atomic-symlink** deploy strategy (Capistrano-style) for zero downtime.

```
/var/www/hexugo/
├── current → releases/<TS>/   ← symlink that nginx + supervisor reference
├── releases/
│   ├── 20260427151203/        ← active
│   ├── 20260427143000/        ← previous (rollback target)
│   └── 20260427120000/        ← older (kept = 3 by default)
└── shared/
    ├── .env                   ← persistent secrets
    └── storage/               ← persistent uploads/logs
```

A new release is fully prepared in its own directory; only the final `ln -snf` swap makes it live. Old releases stay around for instant rollback.

## Deploy a new release

After pushing changes to GitHub `main`:

```bash
ssh hexugo-deploy "bash /opt/hexugo-deploy/deploy/scripts/deploy.sh"
```

What happens (see `deploy/scripts/deploy.sh`):

1. **Clone** new release: `git clone --depth 1 --branch main … releases/<TS>`
2. **Symlink** shared `.env` and `storage/` into release
3. **Composer**: `composer install --no-dev --optimize-autoloader --classmap-authoritative`
4. **Frontend**: `npm ci && npm run build` in `public/frontend/`
5. **Permissions**: `chmod -R 2775 bootstrap/cache`
6. **Maintenance window**: `artisan down --render="errors::503" --refresh=15` (current release)
7. **Migrations**: `php artisan migrate --force --no-interaction`
8. **Caches**: `config:cache`, `route:cache`, `view:cache`, `event:cache`
9. **Symlink swap**: `ln -snf releases/<TS> /var/www/hexugo/current`
10. **Reload**: `sudo systemctl reload php8.3-fpm` + `sudo supervisorctl restart hexugo-queue:*`
11. **Bring up**: `artisan up`
12. **Prune**: keep only last `KEEP_RELEASES=3` directories

Total time: ~1-2 minutes (most of it is `npm ci` and `composer install`).

## Override at deploy time

```bash
ssh hexugo-deploy "BRANCH=hotfix/foo KEEP_RELEASES=5 bash /opt/hexugo-deploy/deploy/scripts/deploy.sh"
```

Variables:

| Var | Default | Purpose |
|---|---|---|
| `REPO_URL` | `https://github.com/musamakhizr/Procurement-Full-Project.git` | Source |
| `BRANCH` | `main` | Branch to deploy |
| `KEEP_RELEASES` | `3` | Old releases to retain |

## Rollback

```bash
ssh hexugo-deploy "bash /opt/hexugo-deploy/deploy/scripts/rollback.sh"
```

Effect: swaps `current` to the previous release in `releases/`, reloads PHP-FPM, restarts queue workers.

> **Caveat**: rollback does NOT undo migrations. If the previous release expects a different schema, you must manually `php artisan migrate:rollback` BEFORE swapping.

## Manual deploy (without the script)

```bash
ssh hexugo-deploy
cd /var/www/hexugo

# 1. New release
TS=$(date -u +%Y%m%d%H%M%S)
git clone --depth 1 https://github.com/musamakhizr/Procurement-Full-Project.git releases/$TS

# 2. Shared resources
ln -snf /var/www/hexugo/shared/storage releases/$TS/storage
ln -snf /var/www/hexugo/shared/.env    releases/$TS/.env

# 3. Build
cd releases/$TS
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction
( cd public/frontend && npm ci && npm run build )
chmod -R 2775 bootstrap/cache

# 4. Migrate + cache
php artisan migrate --force
php artisan config:cache route:cache view:cache event:cache

# 5. Swap
ln -snf /var/www/hexugo/releases/$TS /var/www/hexugo/current

# 6. Reload
sudo systemctl reload php8.3-fpm
sudo supervisorctl restart hexugo-queue:*
```

## CI/CD (GitHub Actions)

> Not yet wired. To enable:

1. Generate a deploy SSH key and add the **public** half to `/home/deploy/.ssh/authorized_keys` on the server.
2. Add the **private** half to GitHub → repo → Settings → Secrets → Actions, name `DEPLOY_SSH_KEY`.
3. Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup SSH key
        run: |
          install -d -m 700 ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          ssh-keyscan -p 22 -H 47.86.173.194 >> ~/.ssh/known_hosts
      - name: Trigger deploy
        run: |
          ssh -i ~/.ssh/id_ed25519 deploy@47.86.173.194 \
            'bash /opt/hexugo-deploy/deploy/scripts/deploy.sh'
```

## Database migrations during deploy

Migrations run **before** the symlink swap, so:

- **Additive** changes (add column, add table, add index) → safe; old release ignores new columns
- **Backward-incompatible** changes (drop/rename column, add NOT NULL without default) → require a 2-step deploy:
  1. **First deploy**: add new column nullable, write to both
  2. **Second deploy**: switch reads, drop old column

Don't run destructive migrations as part of a single hot deploy — you'll break in-flight requests on the old release.

## Common deploy issues

### "composer install" hangs

Usually network — retry. If persistent: `composer config --global secure-http false` is **NOT** the answer; check DNS / proxy.

### "npm ci" fails with "package-lock.json missing"

`public/frontend/package-lock.json` must be committed (we added it explicitly to git). Verify in `public/frontend/.gitignore`:
```
/node_modules
/dist
```
(NOT `package-lock.json`)

### Migrations fail mid-deploy

The `current` symlink still points at the old release — site stays up. Fix the migration locally, push, redeploy. If you need to roll back the partial migration manually:
```bash
ssh hexugo-deploy "cd /var/www/hexugo/releases/<TS> && php artisan migrate:rollback --step=1"
```

### "queue is processing old code"

You forgot to restart workers. Run:
```bash
ssh hexugo-deploy "sudo supervisorctl restart hexugo-queue:*"
```
