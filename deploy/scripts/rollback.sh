#!/usr/bin/env bash
# rollback.sh — Roll back to the previous release
set -euo pipefail

APP_ROOT="/var/www/hexugo"
RELEASES_DIR="${APP_ROOT}/releases"

CURRENT=$(readlink -f "${APP_ROOT}/current")
PREVIOUS=$(ls -1dt "${RELEASES_DIR}"/*/ | grep -v "$(basename ${CURRENT})" | head -1)
PREVIOUS=${PREVIOUS%/}

if [ -z "${PREVIOUS}" ]; then
    echo "No previous release available." >&2
    exit 1
fi

echo "Rolling back  current=${CURRENT}  ->  ${PREVIOUS}"
ln -snf "${PREVIOUS}" "${APP_ROOT}/current"
sudo /usr/bin/systemctl reload php8.3-fpm
sudo /usr/bin/supervisorctl restart hexugo-queue:* || true
echo "Rollback complete."
