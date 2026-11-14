# TWSD Connect — Security Implementation Specification

1. **OAuth 2.0 & OIDC Hardening:**
   - `implicit` and `password` (ROPC) grants are disabled globally (`config/twsd_connect.php`, `AppServiceProvider`, `OAuthTokenController`).
   - PKCE `S256` is mandatory for public clients and configurable (default `true`) for confidential clients; `plain` is rejected (`OAuthAuthorizationBridgeService`, `OidcService::verifyPkceS256`).
   - `RedirectUriValidator` prohibits wildcard (`*`) URIs, URL fragments (`#`), embedded credentials, and non-HTTPS schemes (except loopback `localhost`/`127.0.0.1`).
   - OAuth client secrets (`twsd_cs_*`, 256-bit CSPRNG) are hashed by Passport and returned **only once** at creation or rotation; never serialized in Eloquent models or audit logs.
2. **RS256 Signing & Key Rotation:**
   - RSA-2048 keys generated via `phpseclib3\Crypt\RSA` (`twsd:oidc:rotate-key`).
   - Private keys are stored encrypted at rest (`signing_key_metadata.encrypted_private_key_pem`, hidden from serialization).
   - JWKS (`/.well-known/jwks.json`) exposes `active` and `retiring` public keys only (`kty`, `use`, `alg`, `kid`, `n`, `e`).
3. **Tenant Isolation & RBAC:**
   - `ResolveApplication` resolves `organisation` and `application` strictly from `client_id` on the server—never from browser-supplied org headers or query params.
   - `ResolveOrganisation` + `ApplicationService::assertOwnership` reject cross-tenant access attempts.
   - Central `users.status === 'suspended'` immediately blocks authentication and API token usage across all organisations (`EnsureActiveUser`), while `organisation_users.membership_status` isolates single-tenant suspensions.
4. **OTP & Password Security:**
   - Passwords hashed with Argon2id; account lockout enforced after 5 failed attempts (`EmailPasswordProvider`).
   - 6-digit OTPs generated with `random_int()`, stored only as HMAC-SHA256 digests bound to `otp_transactions.uuid`, verified under pessimistic DB row locking (`lockForUpdate()`), single-use, bound to `(organisation_id, application_id, recipient_hash, purpose)`.
   - `AuditService::sanitize()` automatically redacts sensitive keys (`password`, `client_secret`, `otp`, `smtp_password`, `encrypted_credentials`, tokens) before writing `audit_logs` or `security_events`.
5. **Email Sender Isolation:**
   - `EmailConfigurationService::resolveEffectiveSender()` uses an organisation's verified SMTP/Google Workspace config or explicitly configured platform default—never falling back to another tenant's sender.
