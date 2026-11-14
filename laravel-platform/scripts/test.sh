#!/usr/bin/env bash
set -euo pipefail

echo "==> Verifying Composer validation"
composer validate --strict

echo "==> Testing clean database migration + re-migration idempotency"
php artisan migrate:fresh --force --no-interaction
php artisan migrate --force --no-interaction
php artisan passport:keys --force
php artisan twsd:oidc:rotate-key --initial

echo "==> Running full PHPUnit Feature, Integration & Security test suites"
php artisan test --testsuite=Feature,Integration,Security --stop-on-failure
