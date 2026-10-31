#!/usr/bin/env bash
set -e

echo "=== Starting Inkwell Deployment ==="

# 1. Ensure correct directory
cd "$(dirname "$0")/.."

# 2. Put app into maintenance mode
echo "Setting application to maintenance mode..."
php artisan down --render="errors.503" --retry=60 || true

# 3. Pull latest git changes
echo "Pulling latest code..."
git pull origin main

# 4. Install PHP dependencies
echo "Installing Composer dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 5. Build frontend production assets
echo "Building frontend assets..."
npm ci
npm run build

# 6. Database migrations
echo "Running database migrations..."
php artisan migrate --force

# 7. SQLite Maintenance & Checkpoint
echo "Checkpointing and snapshotting SQLite database..."
php artisan sqlite:checkpoint --mode=truncate
php artisan sqlite:backup

# 8. Ensure storage symlink exists
echo "Verifying storage link..."
php artisan storage:link || true

# 9. Cache configuration, routes, and views
echo "Caching configurations, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 10. Generate search engine sitemap
echo "Regenerating sitemap..."
php artisan sitemap:generate

# 11. Restart queue workers (Supervisor will restart them)
echo "Restarting background queue workers..."
php artisan queue:restart

# 12. Bring application out of maintenance mode
echo "Bringing application online..."
php artisan up

echo "=== Inkwell Deployment Completed Successfully ==="
