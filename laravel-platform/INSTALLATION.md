# TWSD Connect — Production Installation & Verification Guide

## Target Runtime Prerequisites (Ubuntu 24.04 / AlmaLinux 9)
- PHP 8.3+ (`php-fpm`, `php-mysql`, `php-redis`, `php-mbstring`, `php-xml`, `php-curl`, `php-bcmath`, `php-intl`, `php-gmp`, `php-sodium`, `php-openssl`)
- Composer 2.7+
- MySQL 8.0+ (InnoDB, `utf8mb4_unicode_ci`)
- Redis 7.0+ (cache, sessions, queues, rate limiters)
- Nginx + Let's Encrypt SSL + Supervisor

## 1. Laravel Project Bootstrap
Copy `/laravel-platform/` to `/var/www/connect.twsd.me` (or overlay onto a fresh Laravel 12 skeleton ensuring `bootstrap/app.php` loads `routes/web.php`, `routes/api.php`, and registers `App\Providers\AppServiceProvider`), then run:
```bash
cd /var/www/connect.twsd.me
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

## 2. Database, Passport & OIDC Key Initialization
```bash
# Publish Passport migrations if not yet in database/migrations
php artisan vendor:publish --tag=passport-migrations

# Run Passport + TWSD Connect Phase 1–5 migrations (000001..000007)
php artisan migrate --force

# Generate Passport OAuth RSA keys (0600 permissions)
php artisan passport:keys --force

# Generate initial RS256 OIDC signing key in signing_key_metadata
php artisan twsd:oidc:rotate-key --initial
```

## 3. Initial Seeders (Super Admin, Providers, RBAC, Initial Organisation)
Configure `TWSD_SUPER_ADMIN_EMAIL`, `TWSD_SUPER_ADMIN_PASSWORD`, `TWSD_INITIAL_ORG_ADMIN_EMAIL`, and `TWSD_INITIAL_ORG_ADMIN_PASSWORD` in `.env`, then run:
```bash
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder
```
Remove plaintext bootstrap passwords from `.env` immediately after seeding.

## 4. Queue Worker, Scheduler & Nginx
Deploy `deploy/nginx-connect.twsd.me.conf` to `/etc/nginx/sites-available/connect.twsd.me` and configure Supervisor + cron:
```cron
* * * * * cd /var/www/connect.twsd.me && php artisan schedule:run >> /dev/null 2>&1
```

## 5. Automated Feature & Security Test Suite Execution
```bash
php artisan test --testsuite=Feature,Security
```
Covers:
- `OidcAndPkceComplianceTest`
- `PolicyResolutionAndTenantIsolationTest`
- `ApplicationLifecycleAndOAuthClientTest`
- `EmailPasswordAndOtpAuthenticationTest`
- `EmailSenderIsolationAndAdminPolicyTest`
- `CrossTenantAndAuditSecurityTest`
