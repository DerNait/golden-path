#!/usr/bin/env bash
set -Eeuo pipefail

BASE_DIR="${BASE_DIR:-/var/www/golden-path}"
DOMAIN="${DOMAIN:-gym.dernait.com}"
EMAIL="${CERTBOT_EMAIL:-darkcsjuegos1@gmail.com}"
SOURCE="${BASE_DIR}/current/deploy/nginx/gym.dernait.com.conf"
AVAILABLE="/etc/nginx/sites-available/${DOMAIN}"
ENABLED="/etc/nginx/sites-enabled/${DOMAIN}"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run this script as root." >&2
    exit 1
fi

command -v nginx >/dev/null
command -v certbot >/dev/null

install -m 644 "${SOURCE}" "${AVAILABLE}"
ln -sfn "${AVAILABLE}" "${ENABLED}"
nginx -t
systemctl reload nginx

certbot --nginx --non-interactive --agree-tos --redirect \
    --email "${EMAIL}" -d "${DOMAIN}"

nginx -t
systemctl reload nginx
