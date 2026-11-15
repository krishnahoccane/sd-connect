# TWSD Connect — Development Status (Phase 1–5)

**Target Host:** https://connect.twsd.me  
**Current Milestone:** Phase 1–2 Audit Remediation + Phase 3 (Applications & OAuth Clients) + Phase 4 (Email Password & Email OTP) + Phase 5 (Super Admin & Organisation Admin Auth Administration)

---

## Implementation Status Classification

### A. Implemented & Tested (in current Node.js container)
- Express + Vite Source Explorer (`/api/repo/tree`, `/api/repo/file`)
- Node cryptographic reference verification (`/api/protocol/pkce-generate`, `/api/protocol/pkce-verify`, `/api/protocol/policy-resolve`)
- TypeScript lint (`tsc --noEmit`) & Vite build (`vite build`)

### B. Implemented in Laravel 12 Source, Pending Execution on PHP 8.3+ / MySQL 8 Host
*(PHP 8.3+, Composer, MySQL 8, and Redis are unavailable in this container; all Laravel migrations, services, controllers, Blade templates, and PHPUnit tests are written and ready for execution on the target host.)*
- **Phase 1–2 Audit Fixes (`AUDIT_PHASE_1_2.md`):**
  - `AppServiceProvider.php` (Passport token TTLs, OIDC scope definitions, ROPC disabled) & `config/auth.php` (`api` Passport guard)
  - Missing Eloquent models created (`OAuthRedirectUri`, `OrganisationDomain`, `OrganisationBranding`, `BrandingVersion`, `EmailConfiguration`, `OtpTransaction`, `OtpDeliveryLog`, `OtpAttempt`, `SsoSession`, `UserSession`, `LoginAttempt`, `SecurityEvent`, `AuditLog`, `ConfigurationHistory`, `ConsentRecord`, `OAuthTransaction`)
  - Migration `2026_01_01_000007_add_cross_module_foreign_keys_and_oauth_transactions.php`
  - `OAuthAuthorizationBridgeService.php`, `RotateOidcSigningKeyCommand.php`, `AuditService.php`
  - Complete middleware suite: `ResolveApplication`, `ResolveOrganisation`, `EnsureOrganisationActive`, `EnsureActiveUser`, `EnsureApplicationAccess`, `RequireOrganisationPermission`, `EnsureSuperAdmin`
- **Phase 3 — Application Management & OAuthClientService:**
  - `RedirectUriValidator.php` (wildcards, fragments, credentials, insecure HTTP rejected)
  - `OAuthClientService.php` (native Passport client creation, 64-hex secret displayed once, secret rotation, revocation, exact redirect URI sync)
  - `ApplicationService.php` & `ApplicationController.php` (CRUD, suspend/activate/archive, TWSD subdomain & verified custom domain binding, app auth methods, audit logs)
- **Phase 4 — Email Authentication & OTP:**
  - `OtpGenerator.php`, `OtpHasher.php` (HMAC-SHA256 bound to transaction UUID), `OtpVerificationService.php` (pessimistic locking, single-use replay prevention, expiry, brute-force exhaustion, org/app/purpose context binding)
  - `EmailConfigurationService.php` (Platform Google Workspace, Org SMTP, Org Google Workspace; encrypted credentials; test email; strict sender tenant isolation)
  - `OtpDeliveryService.php`, `EmailPasswordProvider.php` (Argon2id verification, lockout policy, password policy), `EmailOtpProvider.php`
- **Phase 5 — Authentication Administration & Blade + Bootstrap 5 UI:**
  - `GlobalAuthAdminController.php`, `AuthenticationAdminController.php`, `AuthenticationController.php`, `OAuthTokenController.php`
  - Blade + Bootstrap 5 views (`layouts/admin.blade.php`, `super-admin/auth-settings.blade.php`, `organisation-admin/applications.blade.php`, `organisation-admin/email-settings.blade.php`, updated `authentication/login.blade.php`)
- **Web Installation Setup Wizard (`/install`):**
  - `InstallationCheckService.php` (PHP 8.3+, extensions, writable paths, Argon2id/OpenSSL/CSPRNG detection)
  - `EnvFileWriter.php` (strict env key validation & newline/null-byte injection protection)
  - `InstallationExecutionService.php` (isolated MySQL PDO test, `.env` write, `APP_KEY` generation, migrations, Passport/OIDC keys, seeders, Argon2id Super Admin creation, `twsd_installed.lock`)
  - `EnsureNotInstalled.php` (locks `/install/*` with HTTP `403` once installed) & `InstallationController.php` + `resources/views/installer/wizard.blade.php`
- **Executable Laravel Feature & Security Test Suites (`tests/`):**
  - `InstallationWizardTest.php`
  - `ApplicationLifecycleAndOAuthClientTest.php`
  - `EmailPasswordAndOtpAuthenticationTest.php`
  - `EmailSenderIsolationAndAdminPolicyTest.php`
  - `CrossTenantAndAuditSecurityTest.php`
  - `OidcAndPkceComplianceTest.php`
  - `PolicyResolutionAndTenantIsolationTest.php`

### C. Partially Implemented
- **End-to-End OIDC Conformance Validation:** Discovery, JWKS, PKCE S256, `OAuthTransaction`, and `id_token` issuance are implemented in code (`OidcService`, `OAuthAuthorizationBridgeService`) but require live execution against Passport migrations & MySQL 8 on a PHP 8.3+ runtime to run full OIDC conformance tests.
- **Password Reset Email Dispatch:** `AuthenticationController::forgotPassword()` & `resetPassword()` are implemented using Laravel's `Password` broker + policy enforcement; requires configured `password_reset_tokens` table (standard Laravel migration) and active mail driver.

### D. Not Implemented Yet (Phases 6–10)
- Phase 6: `FirebaseSmsProvider` (Firebase Admin ID Token verification) & `WhatsAppOtpProvider` (Webglit multipart API)
- Phase 7: `GoogleOAuthProvider`, `MicrosoftOAuthProvider`, `IdentityLinkingService`, `UserProvisioningService` (JIT)
- Phase 8–10: Full Super Admin & Org Admin user/branding consoles, Laravel Client SDK, production deployment runbooks
