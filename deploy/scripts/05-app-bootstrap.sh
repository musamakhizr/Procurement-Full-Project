#!/usr/bin/env bash
# 05-app-bootstrap.sh — First-time application bootstrap (release dirs, .env)
# Pre-req: $REPO_URL, $DB_NAME, $DB_USER, $DB_PASS env vars
set -euo pipefail

: "${REPO_URL:?REPO_URL required}"
: "${DB_NAME:?DB_NAME required}"
: "${DB_USER:?DB_USER required}"
: "${DB_PASS:?DB_PASS required}"

APP_ROOT="/var/www/hexugo"
SHARED_DIR="${APP_ROOT}/shared"
RELEASES_DIR="${APP_ROOT}/releases"
TS=$(date -u +%Y%m%d%H%M%S)
RELEASE_DIR="${RELEASES_DIR}/${TS}"
DEPLOY_USER="deploy"

echo ">> Creating dir structure under ${APP_ROOT}…"
install -d -o "${DEPLOY_USER}" -g www-data -m 2775 "${APP_ROOT}" "${SHARED_DIR}" "${RELEASES_DIR}" \
    "${SHARED_DIR}/storage/app/public" \
    "${SHARED_DIR}/storage/framework/cache/data" \
    "${SHARED_DIR}/storage/framework/sessions" \
    "${SHARED_DIR}/storage/framework/views" \
    "${SHARED_DIR}/storage/framework/testing" \
    "${SHARED_DIR}/storage/logs"

echo ">> Cloning repository…"
sudo -u "${DEPLOY_USER}" git clone --depth 1 "${REPO_URL}" "${RELEASE_DIR}"

echo ">> Generating production .env from template…"
ENV_FILE="${SHARED_DIR}/.env"
if [ ! -f "${ENV_FILE}" ]; then
    cp /opt/hexugo-deploy/templates/env.production.template "${ENV_FILE}"
    sed -i "s|__DB_PASSWORD__|${DB_PASS}|g" "${ENV_FILE}"
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|"  "${ENV_FILE}"
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|"  "${ENV_FILE}"
    chown "${DEPLOY_USER}:www-data" "${ENV_FILE}"
    chmod 640 "${ENV_FILE}"
fi

echo ">> Symlinking shared resources into release…"
rm -rf "${RELEASE_DIR}/storage"
ln -snf "${SHARED_DIR}/storage" "${RELEASE_DIR}/storage"
ln -snf "${SHARED_DIR}/.env"    "${RELEASE_DIR}/.env"

echo ">> Composer install (production)…"
cd "${RELEASE_DIR}"
sudo -u "${DEPLOY_USER}" -H composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress

echo ">> Generating APP_KEY if missing…"
if ! grep -q "^APP_KEY=base64:" "${ENV_FILE}"; then
    sudo -u "${DEPLOY_USER}" php artisan key:generate --force
fi

echo ">> Storage permissions…"
chown -R "${DEPLOY_USER}:www-data" "${SHARED_DIR}/storage"
chmod -R 2775 "${SHARED_DIR}/storage"
find "${SHARED_DIR}/storage" -type d -exec chmod 2775 {} \;
find "${SHARED_DIR}/storage" -type f -exec chmod 0664 {} \;
chown -R "${DEPLOY_USER}:www-data" "${RELEASE_DIR}/bootstrap/cache"
chmod -R 2775 "${RELEASE_DIR}/bootstrap/cache"

echo ">> Migrate + seed (first run)…"
sudo -u "${DEPLOY_USER}" php artisan migrate --force
sudo -u "${DEPLOY_USER}" php artisan db:seed --force

echo ">> Caching config / routes / views / events…"
sudo -u "${DEPLOY_USER}" php artisan storage:link || true
sudo -u "${DEPLOY_USER}" php artisan config:cache
sudo -u "${DEPLOY_USER}" php artisan route:cache
sudo -u "${DEPLOY_USER}" php artisan view:cache
sudo -u "${DEPLOY_USER}" php artisan event:cache || true

echo ">> Building frontend (npm ci + build)…"
if [ -d "${RELEASE_DIR}/public/frontend" ]; then
    cd "${RELEASE_DIR}/public/frontend"
    sudo -u "${DEPLOY_USER}" -H npm ci --no-audit --no-fund
    sudo -u "${DEPLOY_USER}" -H npm run build
fi

echo ">> Pointing 'current' symlink…"
ln -snf "${RELEASE_DIR}" "${APP_ROOT}/current"

echo "<<< 05-app-bootstrap.sh complete — release: ${RELEASE_DIR}"
