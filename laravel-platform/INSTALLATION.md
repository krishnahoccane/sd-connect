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

## 3. Initial Setup Options: Web Installation Wizard (`/install`) or CLI Seeder
### Option A — Interactive Web Installation Setup (`/install`)
Before any Super Admin exists and while `storage/app/twsd_installed.lock` is absent (`TWSD_INSTALLER_ENABLED=true`), browse to `https://connect.twsd.me/install`:
1. **Requirements Detection (`GET /install`, `GET /install/requirements`):** Verifies PHP 8.3+, required PHP extensions (`openssl`, `pdo_mysql`, `mbstring`, `xml`, `bcmath`, `intl`, `gmp`, `sodium`, `ctype`, `fileinfo`, `json`, `zip`), directory writability (`storage/*`, `bootstrap/cache`, `.env`), Argon2id, OpenSSL RSA-2048, and CSPRNG readiness.
2. **Database & Environment Setup (`POST /install/database/test`, `POST /install/database/migrate`):** Tests live MySQL 8 PDO connectivity, safely writes `.env` (`EnvFileWriter` blocks newline/key injection), generates `APP_KEY`, runs migrations, generates Passport & OIDC signing keys, and seeds `AuthProviderSeeder` + `RolePermissionSeeder`.
3. **Super Admin Creation (`POST /install/super-admin`):** Creates the central Argon2id Super Admin user (12+ char policy), assigns the `super_admin` platform role, optionally bootstraps the initial organisation, records `platform.installation_completed` in `audit_logs`, writes `storage/app/twsd_installed.lock`, sets `TWSD_INSTALLER_ENABLED=false`, and permanently locks all `/install/*` routes with HTTP `403`.

### Option B — CLI Seeder (Headless / Docker)
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
- `InstallationWizardTest`
- `OidcAndPkceComplianceTest`
- `PolicyResolutionAndTenantIsolationTest`
- `ApplicationLifecycleAndOAuthClientTest`
- `EmailPasswordAndOtpAuthenticationTest`
- `EmailSenderIsolationAndAdminPolicyTest`
- `CrossTenantAndAuditSecurityTest`

