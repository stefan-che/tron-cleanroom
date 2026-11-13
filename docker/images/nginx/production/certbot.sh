#!/bin/bash
# Issue / renew a Let's Encrypt certificate for the production nginx config.
# Usage: APP_DOMAIN=example.com CERTBOT_EMAIL=admin@example.com ./certbot.sh
set -e

APP_DOMAIN="${APP_DOMAIN:-example.com}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-admin@example.com}"
WEBROOT="${WEBROOT:-/var/www/app/public}"

certbot certonly --webroot -w "$WEBROOT" -d "$APP_DOMAIN" --agree-tos -n --email "$CERTBOT_EMAIL" --force-renewal

# production.conf expects /etc/nginx/ssl/<domain>.crt and .key
cp "/etc/letsencrypt/live/$APP_DOMAIN/fullchain.pem" "/etc/nginx/ssl/$APP_DOMAIN.crt"
cp "/etc/letsencrypt/live/$APP_DOMAIN/privkey.pem" "/etc/nginx/ssl/$APP_DOMAIN.key"
