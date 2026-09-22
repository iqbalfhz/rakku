#!/bin/sh
set -e

# Persiapan setiap container start; bergantung pada environment dan database yang baru ada saat runtime.

# Volume storage/app/private mulanya kosong, dan folder lain harus ada agar aplikasi bisa jalan.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod -R 775 storage bootstrap/cache

php artisan migrate --force --no-interaction

# config:cache membekukan environment, jadi perubahan env baru berlaku setelah Restart.
php artisan optimize

exec "$@"
