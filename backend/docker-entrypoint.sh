#!/bin/bash
set -e

# All startup logging goes to stderr so it never corrupts a stdout consumer
# (e.g. an MCP server speaking JSON-RPC over stdout).
log() { echo "$@" >&2; }

log "Waiting for MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    sleep 2
done
log "MySQL ready."

private_uploads_path=/var/www/html/storage/app/private-uploads
mkdir -p "$private_uploads_path"
chown www-data:www-data "$private_uploads_path"
chmod 0770 "$private_uploads_path"

php artisan config:cache >&2
php artisan view:cache >&2
php artisan storage:link >&2 2>/dev/null || true

exec "$@"
