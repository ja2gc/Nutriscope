#!/bin/bash
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan reports:demographic-census-catch-up
php artisan storage:link 2>/dev/null || true
