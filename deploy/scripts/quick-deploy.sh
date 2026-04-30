#!/usr/bin/env bash
# quick-deploy.sh — In-place git pull + rebuild + reload, run as `deploy` user on server.
#
# What it does (in order, fail-fast on any error):
#   1. git pull origin main         (in /var/www/hexugo/current)
#   2. composer install --no-dev    (only if composer.lock changed)
#   3. npm ci + npm run build       (only if frontend deps or source changed)
#   4. php artisan migrate --force  (only if new migrations exist)
#   5. clear + rebuild Laravel caches
#   6. reload php-fpm + restart queue workers
#
# Usage on server:
#   bash /opt/hexugo-deploy/deploy/scripts/quick-deploy.sh
#
# Usage from your laptop:
#   ssh hexugo-deploy "bash /opt/hexugo-deploy/deploy/scripts/quick-deploy.sh"

set -euo pipefail

APP="/var/www/hexugo/current"
BRANCH="${BRANCH:-main}"
START=$(date -u +%s)

c_green="\033[0;32m"; c_yellow="\033[0;33m"; c_red="\033[0;31m"; c_reset="\033[0m"
log()  { echo -e "${c_green}[$(date -u +%H:%M:%S)]${c_reset} $*"; }
warn() { echo -e "${c_yellow}[$(date -u +%H:%M:%S)] WARN${c_reset} $*"; }
die()  { echo -e "${c_red}[$(date -u +%H:%M:%S)] ERROR${c_reset} $*"; exit 1; }

trap 'die "Quick-deploy FAILED at line $LINENO."' ERR

cd "$APP" || die "App dir $APP not found"

# Keep /opt/hexugo-deploy in sync too (where deploy scripts/templates live)
# Non-fatal if it fails (e.g. permissions) — the running script copy is enough.
if [ -d /opt/hexugo-deploy/.git ]; then
    log "Updating deploy scripts in /opt/hexugo-deploy…"
    if ! git -C /opt/hexugo-deploy fetch --quiet --all 2>/dev/null \
       || ! git -C /opt/hexugo-deploy reset --quiet --hard "origin/$BRANCH" 2>/dev/null; then
        warn "couldn't update /opt/hexugo-deploy — continuing"
    fi
fi

# ───────────────────────────── 1. Pull ─────────────────────────────
log "Fetching $BRANCH…"
git fetch --all --prune
LOCAL_HEAD=$(git rev-parse HEAD)
REMOTE_HEAD=$(git rev-parse "origin/$BRANCH")

if [ "$LOCAL_HEAD" = "$REMOTE_HEAD" ]; then
    warn "Already at $REMOTE_HEAD — nothing to pull. (forcing rebuild anyway)"
else
    log "Updating $LOCAL_HEAD → $REMOTE_HEAD"
fi

# Save what changed so we can skip unnecessary work
CHANGED=$(git diff --name-only "$LOCAL_HEAD" "$REMOTE_HEAD" 2>/dev/null || echo "*")
git reset --hard "origin/$BRANCH"

needs_composer=false
needs_npm=false
needs_migrate=false

if echo "$CHANGED" | grep -qE '^(composer\.(json|lock)|app/|bootstrap/|config/|routes/|database/|public/index\.php)'; then
    needs_composer=true
fi
if echo "$CHANGED" | grep -qE '^public/frontend/'; then
    needs_npm=true
fi
if echo "$CHANGED" | grep -qE '^database/migrations/'; then
    needs_migrate=true
fi
# First-run safety: if change-detect fails (no diff), do everything
if [ "$LOCAL_HEAD" = "$REMOTE_HEAD" ]; then
    needs_composer=true
    needs_npm=true
fi

# ───────────────────────────── 2. Composer ──────────────────────────
if [ "$needs_composer" = true ]; then
    log "composer install --no-dev --optimize-autoloader…"
    composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
else
    log "Skipping composer (no relevant changes)"
fi

# ───────────────────────────── 3. Frontend ──────────────────────────
if [ "$needs_npm" = true ] && [ -d "$APP/public/frontend" ]; then
    log "Building frontend (npm ci + npm run build)…"
    pushd "$APP/public/frontend" >/dev/null

    # Defensive: pre-clean dist/ ourselves (not via Vite) so ownership/permission
    # issues from a previous root-built dist surface clearly here, not deep in vite.
    if [ -d dist ] && [ ! -w dist ]; then
        warn "dist/ not writable by $(whoami) — attempting chown via sudo"
        sudo chown -R "$(id -u):$(id -g)" dist 2>/dev/null || true
    fi
    rm -rf dist 2>/dev/null || { warn "rm -rf dist failed; sudo retry"; sudo rm -rf dist; }

    if [ ! -d node_modules ] || [ package-lock.json -nt node_modules/.installed-marker 2>/dev/null ]; then
        npm ci --no-audit --no-fund
        touch node_modules/.installed-marker
    fi
    npm run build
    popd >/dev/null
else
    log "Skipping frontend build (no frontend changes)"
fi

# ───────────────────────────── 4. Migrate ───────────────────────────
if [ "$needs_migrate" = true ]; then
    log "Running new migrations (php artisan migrate --force)…"
    php artisan migrate --force --no-interaction
else
    log "Skipping migrations (no new migration files)"
fi

# ───────────────────────────── 5. Caches ────────────────────────────
log "Rebuilding Laravel caches…"
php artisan config:clear  >/dev/null
php artisan route:clear   >/dev/null
php artisan view:clear    >/dev/null
php artisan event:clear   >/dev/null 2>&1 || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

# Clear runtime caches that might hold stale data after code changes
php artisan cache:clear >/dev/null 2>&1 || true

# ───────────────────────────── 6. Reload runtime ────────────────────
log "Reloading PHP-FPM (picks up code changes via OPcache)…"
sudo /usr/bin/systemctl reload php8.3-fpm

log "Restarting queue workers (so they pick up new code)…"
sudo /usr/bin/supervisorctl restart hexugo-queue:* >/dev/null
sudo /usr/bin/supervisorctl restart hexugo-schedule >/dev/null 2>&1 || true

# ───────────────────────────── Done ─────────────────────────────────
ELAPSED=$(( $(date -u +%s) - START ))
NEW_HEAD=$(git rev-parse --short HEAD)
log "✓ Deploy complete in ${ELAPSED}s — now at ${NEW_HEAD}"
echo
echo "Summary of changes:"
git log --oneline "${LOCAL_HEAD}..HEAD" 2>/dev/null | head -10 || echo "  (already up to date)"
