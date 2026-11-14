# TWSD Connect — Runtime Validation & Security Hardening Report (`RUNTIME_VALIDATION_REPORT.md`)

**Report Date:** 2026-11-13  
**Repository:** `/laravel-platform/`  
**Sandbox Environment:** `Linux x86_64`, `Node.js v22.23.2`, `OpenSSL 3.0.22`, `nginx`  
**Missing Sandbox Binaries:** `php` (8.3+), `composer`, `mysql`/`mysqld` (MySQL 8), `redis-server`, `docker`, `docker-compose`

---

## 1. Static & Architectural Defects Found & Corrected During Validation

| Defect ID | Severity | Finding | Corrective Action Applied |
|---|---|---|---|
| **VAL-01** | **Critical** | Missing Laravel 12 executable bootstrap (`artisan`, `public/index.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `phpunit.xml`, `config/app.php`, `config/database.php`, `config/session.php`, `config/hashing.php`, `config/cors.php`) and `password_reset_tokens` / `sessions` / `cache` / `jobs` tables. | Created full Laravel 12 bootstrap skeleton, system tables migration (`2026_01_01_000000`), and operational security configs. |
| **VAL-02** | **Critical** | `App\Models\User` uses `primary_email`, causing Laravel's `CanResetPassword::getEmailForPasswordReset()` and `routeNotificationForMail()` to return `null` on password reset and verification. | Overrode `getEmailForPasswordReset()` and `routeNotificationForMail()` in `app/Models/User.php`. |
| **VAL-03** | **Critical** | `OAuthTokenController::token()` rejected `refresh_token` grants, and no refresh-token rotation or reuse-detection mechanism existed. | Implemented `oauth_refresh_token_families` migration (`000008`), `OAuthRefreshTokenFamily` model, `rotateRefreshToken()`, family revocation + `critical` `SecurityEvent` on reuse, and `azp` + `verifyIdToken()` in `OidcService`. |
| **VAL-04** | **High** | `ApplicationController` and `AuthenticationController` allowed `InvalidArgumentException` from services to bubble as HTTP 500 instead of 403/422 JSON responses; `AuthenticationController` did not create `UserSession` rows or regenerate session IDs. | Added clean HTTP `403`/`422` handling, `$request->session()->regenerate()` fixation protection, and `UserSession` creation on OAuth auth completion. |
| **VAL-05** | **High** | `EmailConfigurationService::upsertOrganisationConfiguration()` overwrote existing `encrypted_transport_secrets` with `null` when omitted on update. | Preserved existing encrypted transport secrets when omitted during configuration updates. |
| **VAL-06** | **Medium** | Missing explicit HTTP security headers (`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store`, `HSTS`) and `TrustProxies` configuration. | Created `SecurityHeadersMiddleware` and wired `TRUSTED_PROXIES` + JSON error rendering in `bootstrap/app.php`. |

---

## 2. Execution Test Matrix (Honest Reporting — Zero Fabricated Results)

| Test Name / Check | Environment | Expected Result | Actual Result | Status | Failure Details / Corrective Action |
|---|---|---|---|---|---|
| `composer validate --strict` & `composer install` | Sandbox (No PHP/Composer) | Resolve Laravel 12 + Passport 13 cleanly | Not executed (`composer` unavailable) | **NOT EXECUTED** | Provisioned `Dockerfile` + `docker-compose.yml`; run inside `app` container |
| `php artisan migrate:fresh` & idempotent re-run | Sandbox (No PHP/MySQL) | Create 36+ tables + FKs cleanly | Not executed (`php`/`mysql` unavailable) | **NOT EXECUTED** | Run `./scripts/install.sh` inside Docker `app` container |
| `PassportOidcEndToEndTest` (16-step OIDC + PKCE + rotation + logout) | Sandbox (No PHP) | All assertions pass via HTTP routes | Not executed (`php` unavailable) | **NOT EXECUTED** | Executable PHPUnit suite written; run `./scripts/test.sh` in Docker |
| `OAuthSecurityHardeningTest` (grants, PKCE downgrade, replay, refresh reuse) | Sandbox (No PHP) | All assertions pass | Not executed (`php` unavailable) | **NOT EXECUTED** | Executable PHPUnit suite written; run `./scripts/test.sh` in Docker |
| `AuthenticationAndOtpEdgeCasesTest` (lockout, OTP expiry/replay/context/redaction) | Sandbox (No PHP) | All assertions pass | Not executed (`php` unavailable) | **NOT EXECUTED** | Executable PHPUnit suite written; run `./scripts/test.sh` in Docker |
| `TenantIsolationAndAuthorizationMatrixTest` (2-tenant probes, suspension) | Sandbox (No PHP) | All assertions pass | Not executed (`php` unavailable) | **NOT EXECUTED** | Executable PHPUnit suite written; run `./scripts/test.sh` in Docker |
| `OidcAndPkceComplianceTest` & `PolicyResolutionAndTenantIsolationTest` | Sandbox (No PHP) | All assertions pass | Not executed (`php` unavailable) | **NOT EXECUTED** | Executable PHPUnit suites written; run `./scripts/test.sh` in Docker |
| `ApplicationLifecycleAndOAuthClientTest`, `EmailPasswordAndOtpAuthenticationTest`, `EmailSenderIsolationAndAdminPolicyTest`, `CrossTenantAndAuditSecurityTest` | Sandbox (No PHP) | All assertions pass | Not executed (`php` unavailable) | **NOT EXECUTED** | Executable PHPUnit suites written; run `./scripts/test.sh` in Docker |
| Node Workspace `tsc --noEmit` & `vite build` | Sandbox (`Node v22.23.2`) | Zero TS/build errors | Compiled and linted cleanly | **PASS** | Verified in container |

---

## 3. Readiness Decision for Phase 6–7 (Firebase, WhatsApp, Google, Microsoft)
- **Status:** **Code-ready for runtime verification; NOT yet runtime-verified.**
- **Requirement Before Phase 6:** Execute `docker compose up -d --build` + `./scripts/install.sh` + `./scripts/test.sh` on a Docker/PHP 8.3+ host and confirm 100% PHPUnit `PASS` across `Feature`, `Integration`, and `Security` suites before claiming production readiness or proceeding to Phase 6.
