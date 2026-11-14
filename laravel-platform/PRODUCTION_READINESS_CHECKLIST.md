# TWSD Connect — Production Readiness Checklist (`PRODUCTION_READINESS_CHECKLIST.md`)

**Overall Verdict:** **NOT PRODUCTION-READY YET** (PHPUnit & MySQL 8 runtime verification has not yet executed in a PHP 8.3+ / Docker host, and Phases 6–10 remain pending).

## 1. Runtime & Dependency Verification
- [x] PHP 8.3+ Dockerfile with required extensions (`pdo_mysql`, `redis`, `mbstring`, `xml`, `bcmath`, `intl`, `gmp`, `sodium`, `pcntl`, `zip`, `openssl`)
- [x] Reproducible `docker-compose.yml` (PHP-FPM, Nginx, MySQL 8.0, Redis 7, Queue Worker) with zero committed secrets
- [ ] `composer install` & `composer validate --strict` executed on PHP 8.3+ host
- [ ] `php artisan migrate:fresh` & idempotent second `php artisan migrate` verified against MySQL 8.0

## 2. OAuth 2.0 & OpenID Connect Compliance
- [x] `implicit` and `password` (ROPC) grants disabled in config, `AppServiceProvider`, and `OAuthTokenController`
- [x] PKCE `S256` enforced; `plain` rejected
- [x] Wildcard (`*`), fragment (`#`), credential-embedded, and insecure HTTP redirect URIs rejected
- [x] RSA-2048 signing key generation & rotation (`twsd:oidc:rotate-key`) with encrypted private keys at rest
- [x] Refresh token rotation & reuse detection (`oauth_refresh_token_families`)
- [ ] End-to-end PHPUnit execution (`PassportOidcEndToEndTest`, `OAuthSecurityHardeningTest`) passed on PHP 8.3+ host
- [ ] External OIDC conformance suite verification (OpenID Foundation conformance tool)

## 3. Operational & Infrastructure Security
- [x] Argon2id password hashing (`HASH_DRIVER=argon2id`)
- [x] Encrypted Redis sessions with `Secure`, `HttpOnly`, and `SameSite=Lax` flags
- [x] `SecurityHeadersMiddleware` (`X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Cache-Control: no-store`, `HSTS`)
- [x] `APP_DEBUG=false` default & JSON exception rendering on API/OAuth routes
- [x] `AuditService` automatic secret/OTP redaction
- [ ] Production SSL certificates provisioned (`connect.twsd.me`) and `TRUSTED_PROXIES` / `CORS_ALLOWED_ORIGINS` locked down
- [ ] Automated encrypted off-site backups configured for MySQL 8 and `signing_key_metadata`
