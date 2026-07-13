#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSH_TARGET="${SSH_TARGET:-root@167.233.42.11}"
SSH_PORT="${SSH_PORT:-17}"
BASE_DIR="${BASE_DIR:-/var/www/golden-path}"
RELEASE_ID="${1:?Usage: SSH_TARGET=root@host $0 RELEASE_ID}"
IMAGE_NAME="golden-path-app:${RELEASE_ID}"
ARTIFACT="${ROOT_DIR}/artifacts/golden-path-app-${RELEASE_ID}.tar.gz"
REMOTE_RELEASE="${BASE_DIR}/releases/${RELEASE_ID}"
SSH=(ssh -p "${SSH_PORT}" "${SSH_TARGET}")
RSYNC_SSH="ssh -p ${SSH_PORT}"

test -s "${ARTIFACT}"
test -s "${ARTIFACT}.sha256"

"${SSH[@]}" "install -d -m 755 '${BASE_DIR}/releases' '${BASE_DIR}/images' && install -d -m 700 '${BASE_DIR}/shared' && mkdir -p '${REMOTE_RELEASE}' '${BASE_DIR}/shared/storage/app/public'"

rsync -az --delete -e "${RSYNC_SSH}" \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='.build/' \
    --exclude='artifacts/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='public/hot' \
    --exclude='public/storage' \
    --exclude='storage/app/public/' \
    --exclude='storage/logs/' \
    --exclude='storage/framework/' \
    --exclude='.phpunit.result.cache' \
    --exclude='qa/' \
    "${ROOT_DIR}/" "${SSH_TARGET}:${REMOTE_RELEASE}/"

rsync -az -e "${RSYNC_SSH}" \
    "${ROOT_DIR}/storage/app/public/" \
    "${SSH_TARGET}:${BASE_DIR}/shared/storage/app/public/"

rsync -az -e "${RSYNC_SSH}" \
    "${ARTIFACT}" "${ARTIFACT}.sha256" \
    "${SSH_TARGET}:${BASE_DIR}/images/"

"${SSH[@]}" bash -s -- "${BASE_DIR}" "${RELEASE_ID}" "${IMAGE_NAME}" <<'REMOTE'
set -Eeuo pipefail
BASE_DIR="$1"
RELEASE_ID="$2"
IMAGE_NAME="$3"
RELEASE_DIR="${BASE_DIR}/releases/${RELEASE_ID}"
ARTIFACT="${BASE_DIR}/images/golden-path-app-${RELEASE_ID}.tar.gz"

bash "${RELEASE_DIR}/scripts/initialize-production.sh"
printf 'APP_IMAGE=%s\n' "${IMAGE_NAME}" > "${BASE_DIR}/shared/deploy.env"
chmod 600 "${BASE_DIR}/shared/deploy.env"

cd "${BASE_DIR}/images"
sha256sum -c "$(basename "${ARTIFACT}").sha256"
gzip -dc "${ARTIFACT}" | docker load

docker compose \
    --env-file "${BASE_DIR}/shared/.env" \
    --env-file "${BASE_DIR}/shared/deploy.env" \
    -f "${RELEASE_DIR}/docker-compose.production.yml" config --quiet

ln -sfn "${RELEASE_DIR}" "${BASE_DIR}/current"
rm -f "${BASE_DIR}/current/public/storage"
mkdir -p "${BASE_DIR}/current/public/storage"

compose=(docker compose --env-file "${BASE_DIR}/shared/.env" --env-file "${BASE_DIR}/shared/deploy.env" -f "${BASE_DIR}/current/docker-compose.production.yml")
# Bind mounts that pass through the `current` symlink are resolved when the
# container is created. Recreate app and nginx so every release reads the new
# code and public assets instead of retaining the previous symlink target.
"${compose[@]}" up -d --remove-orphans --force-recreate app nginx
"${compose[@]}" exec -T app php artisan migrate --force </dev/null

if [[ ! -f "${BASE_DIR}/shared/.seeded" ]]; then
    "${compose[@]}" exec -T app php artisan db:seed --force </dev/null
    touch "${BASE_DIR}/shared/.seeded"
    chmod 600 "${BASE_DIR}/shared/.seeded"
fi

"${compose[@]}" exec -T app php artisan optimize </dev/null
"${compose[@]}" ps

printf '%s\n' '17 3 * * * root /var/www/golden-path/current/scripts/backup-production.sh >> /var/log/golden-path-backup.log 2>&1' \
    > /etc/cron.d/golden-path-backup
chmod 644 /etc/cron.d/golden-path-backup
install -d -m 700 /var/backups/golden-path
REMOTE

echo "Release ${RELEASE_ID} deployed. Run configure-host-nginx.sh once if HTTPS is not configured."
