#!/usr/bin/env bash
set -euo pipefail

echo "==> Checking PHP & required extensions"
php -v
php -m | grep -E "openssl|pdo_mysql|redis|mbstring|sodium|gmp|intl|bcmath|xml"

echo "==> Installing Composer dependencies"
composer install --no-interaction --prefer-dist --optimize-autoloader

if [ ! -f .env ]; then
  echo "ERROR: .env file missing. Copy .env.example to .env and configure credentials first." >&2
  exit 1
fi

echo "==> Generating application key if missing"
php artisan key:generate --ansi --no-interaction || true

echo "==> Publishing Laravel Passport migrations (if not published)"
php artisan vendor:publish --tag=passport-migrations --no-interaction || true

echo "==> Running migrations against MySQL 8"
php artisan migrate --force --no-interaction

echo "==> Verifying idempotent second migration run"
php artisan migrate --force --no-interaction

echo "==> Generating Passport OAuth RSA keys & initial OIDC signing key"
php artisan passport:keys --force
php artisan twsd:oidc:rotate-key --initial

echo "==> Running database seeders"
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force --no-interaction

echo "==> Installation bootstrap complete"
