#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_ID="${1:-$(date -u +%Y%m%d%H%M%S)}"
IMAGE_NAME="golden-path-app:${RELEASE_ID}"
ARTIFACT_DIR="${ROOT_DIR}/artifacts"

cd "${ROOT_DIR}"

command -v docker >/dev/null

# Development containers may leave generated files owned by root. Normalize
# only disposable build directories before running the reproducible builders.
docker run --rm \
    --volume "${ROOT_DIR}:/var/www" \
    alpine:3.22 sh -c \
    "chown -R $(id -u):$(id -g) /var/www/node_modules /var/www/public/build /var/www/.build 2>/dev/null || true"

echo "[1/5] Installing exact frontend dependencies"
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --env HOME=/tmp \
    --volume "${ROOT_DIR}:/var/www" \
    --workdir /var/www \
    node:22-alpine npm ci

echo "[2/5] Building Vite assets"
rm -f public/hot
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --env HOME=/tmp \
    --volume "${ROOT_DIR}:/var/www" \
    --workdir /var/www \
    node:22-alpine npm run build

echo "[3/5] Preparing production Composer dependencies"
rm -rf .build/production
mkdir -p .build/production/app .build/production/database
cp composer.json composer.lock .build/production/
cp -R app/. .build/production/app/
cp -R database/. .build/production/database/
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --env HOME=/tmp \
    --volume "${ROOT_DIR}:/var/www" \
    --workdir /var/www/.build/production \
    composer:2 install \
        --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-scripts

echo "[4/5] Building ${IMAGE_NAME} for linux/amd64"
docker buildx build \
    --platform linux/amd64 \
    --load \
    --file Dockerfile.production \
    --tag "${IMAGE_NAME}" \
    .

test "$(docker image inspect "${IMAGE_NAME}" --format '{{.Architecture}}')" = "amd64"
docker run --rm "${IMAGE_NAME}" php -r \
    'require "vendor/autoload.php"; exit(class_exists("Database\\Seeders\\DatabaseSeeder") ? 0 : 1);'

echo "[5/5] Exporting image"
mkdir -p "${ARTIFACT_DIR}"
docker save "${IMAGE_NAME}" | gzip -9 > "${ARTIFACT_DIR}/${IMAGE_NAME/:/-}.tar.gz"
(
    cd "${ARTIFACT_DIR}"
    sha256sum "${IMAGE_NAME/:/-}.tar.gz" > "${IMAGE_NAME/:/-}.tar.gz.sha256"
)

printf 'RELEASE_ID=%s\nIMAGE=%s\nARTIFACT=%s\n' \
    "${RELEASE_ID}" "${IMAGE_NAME}" "${ARTIFACT_DIR}/${IMAGE_NAME/:/-}.tar.gz"
