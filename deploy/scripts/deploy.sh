#!/usr/bin/env bash
# deploy.sh — Zero-downtime release deploy (run as 'deploy' user)
# Strategy:
#   1. Clone fresh release into releases/<timestamp>
#   2. Symlink shared .env + storage
#   3. composer install + npm build
#   4. migrate --force (safe for additive migrations)
#   5. Build new caches
#   6. Atomic symlink swap of 'current'
#   7. Reload php-fpm + restart queue workers
#   8. Keep last KEEP_RELEASES, prune older

set -euo pipefail

APP_ROOT="/var/www/hexugo"
SHARED_DIR="${APP_ROOT}/shared"
RELEASES_DIR="${APP_ROOT}/releases"
REPO_URL="${REPO_URL:-https://github.com/musamakhizr/Procurement-Full-Project.git}"
BRANCH="${BRANCH:-main}"
KEEP_RELEASES="${KEEP_RELEASES:-3}"
TS=$(date -u +%Y%m%d%H%M%S)
RELEASE_DIR="${RELEASES_DIR}/${TS}"

log() { echo "[$(date -u +%H:%M:%S)] $*"; }

trap 'log "DEPLOY FAILED at line $LINENO. Current symlink unchanged."; exit 1' ERR

log "=== Deploying ${REPO_URL}@${BRANCH} -> ${RELEASE_DIR} ==="

mkdir -p "${RELEASES_DIR}"
git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${RELEASE_DIR}"

log "Symlinking shared resources…"
rm -rf "${RELEASE_DIR}/storage"
ln -snf "${SHARED_DIR}/storage" "${RELEASE_DIR}/storage"
ln -snf "${SHARED_DIR}/.env"    "${RELEASE_DIR}/.env"

log "Composer install (no-dev, optimized)…"
cd "${RELEASE_DIR}"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress

log "Frontend build…"
if [ -d "${RELEASE_DIR}/public/frontend" ]; then
    pushd "${RELEASE_DIR}/public/frontend" >/dev/null
    npm ci --no-audit --no-fund
    npm run build
    popd >/dev/null
fi

log "Bootstrap cache permissions…"
chmod -R 2775 "${RELEASE_DIR}/bootstrap/cache"

log "Maintenance window…"
"${APP_ROOT}/current/artisan" down --render="errors::503" --retry=15 --refresh=15 || true

log "Running migrations…"
php "${RELEASE_DIR}/artisan" migrate --force --no-interaction

log "Caching config / routes / views / events…"
php "${RELEASE_DIR}/artisan" config:cache
php "${RELEASE_DIR}/artisan" route:cache
php "${RELEASE_DIR}/artisan" view:cache
php "${RELEASE_DIR}/artisan" event:cache || true

log "Atomic swap of 'current'…"
ln -snf "${RELEASE_DIR}" "${APP_ROOT}/current"

log "Reloading PHP-FPM + restarting queue workers…"
sudo /usr/bin/systemctl reload php8.3-fpm
sudo /usr/bin/supervisorctl restart hexugo-queue:* || true

log "Bringing app back up…"
"${APP_ROOT}/current/artisan" up || true

log "Pruning old releases (keeping last ${KEEP_RELEASES})…"
ls -1dt "${RELEASES_DIR}"/*/ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

log "=== Deploy complete: ${TS} ==="
"${APP_ROOT}/current/artisan" --version
