#!/bin/sh
set -e

cd /var/www/html

# Render's disk is ephemeral on the free tier, so re-check/create the
# storage symlink and run migrations on every boot — both are safe to
# repeat (migrate only applies new migrations, storage:link is a no-op
# if the link already exists).
php artisan storage:link || true
php artisan migrate --force

exec "$@"
