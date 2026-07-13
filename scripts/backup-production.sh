#!/usr/bin/env bash
set -Eeuo pipefail

BASE_DIR="${BASE_DIR:-/var/www/golden-path}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/golden-path}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
COMPOSE_FILE="${BASE_DIR}/current/docker-compose.production.yml"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run this script as root." >&2
    exit 1
fi

umask 077
install -d -m 700 "${BACKUP_DIR}"

docker compose \
    --env-file "${BASE_DIR}/shared/.env" \
    --env-file "${BASE_DIR}/shared/deploy.env" \
    -f "${COMPOSE_FILE}" \
    exec -T mysql sh -c \
    'exec mysqldump --single-transaction --quick --lock-tables=false -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip -9 > "${BACKUP_DIR}/mysql-${STAMP}.sql.gz"

tar -C "${BASE_DIR}/shared/storage/app" -czf \
    "${BACKUP_DIR}/images-${STAMP}.tar.gz" public

gzip -t "${BACKUP_DIR}/mysql-${STAMP}.sql.gz"
tar -tzf "${BACKUP_DIR}/images-${STAMP}.tar.gz" >/dev/null
find "${BACKUP_DIR}" -type f -mtime +6 -delete

printf 'Created %s and %s\n' \
    "${BACKUP_DIR}/mysql-${STAMP}.sql.gz" \
    "${BACKUP_DIR}/images-${STAMP}.tar.gz"
