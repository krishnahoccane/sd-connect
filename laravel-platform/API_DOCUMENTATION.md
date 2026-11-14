# TWSD Connect — Versioned REST & OIDC API Documentation

Base URL: `https://connect.twsd.me`

## 1. OpenID Connect & OAuth 2.0 Endpoints
| Method | Path | Middleware | Purpose |
|---|---|---|---|
| `GET` | `/.well-known/openid-configuration` | Public | OIDC Discovery metadata (RS256, PKCE S256 only) |
| `GET` | `/.well-known/jwks.json` | Public | Active & retiring RSA public signing keys |
| `GET` | `/oauth/authorize` | `ResolveApplication`, `throttle:60,1` | Initiates `OAuthTransaction` with exact redirect URI & PKCE S256 validation |
| `POST` | `/oauth/token` | `throttle:60,1` | Exchanges single-use auth code + `code_verifier` for Bearer token + `id_token` |
| `GET` | `/oauth/userinfo` | `auth:api`, `EnsureActiveUser` | Returns scope-filtered identity claims (`sub`, `name`, `email`, `phone_number`) |
| `POST` | `/oauth/revoke` | `throttle:60,1` | Revokes Passport token |
| `POST` | `/oauth/introspect` | `throttle:60,1` | Returns token active status, scopes, client_id, exp |
| `GET` | `/oauth/logout` | Public | Revokes SSO & child user sessions; validates `post_logout_redirect_uri` |

## 2. Public Authentication API (`/api/v1/auth/*`)
*All endpoints require `ResolveApplication` (`client_id` resolved server-side).*
- `POST /api/v1/auth/methods` — Returns effective auth methods (`GLOBAL ∩ ORG ∩ APP ∩ HEALTHY`).
- `POST /api/v1/auth/email/password` — Verifies Argon2id password, enforces lockout, returns MFA stage or OAuth redirect code.
- `POST /api/v1/auth/email/otp/send` — Generates 6-digit hashed OTP, sends via tenant-isolated sender, returns `transaction_uuid` & masked email.
- `POST /api/v1/auth/email/otp/verify` — Atomically verifies single-use OTP bound to `(org, app, email, purpose)`.
- `POST /api/v1/auth/password/forgot` — Queues password reset link.
- `POST /api/v1/auth/password/reset` — Enforces org/global password policy and updates password hash.
- `POST /api/v1/auth/logout` — Revokes central SSO session cookie.

## 3. Organisation Admin API (`/api/v1/organisation/{organisation}/*`)
*Middleware: `auth:api`, `EnsureActiveUser`, `ResolveOrganisation`, `EnsureOrganisationActive`, `RequireOrganisationPermission`*
- `GET /applications` — Paginated tenant applications.
- `POST /applications` — Registers application + Passport client; returns `client_secret_once` **only once**.
- `GET /applications/{application}` — Application details (no secret returned).
- `PUT /applications/{application}` — Updates metadata, verified custom domain, redirect URIs, and hierarchy-checked auth methods.
- `POST /applications/{application}/suspend` / `activate` / `archive`
- `POST /applications/{application}/rotate-secret` — Rotates confidential secret and returns `client_secret_once` once.
- `GET /applications/{application}/audit-logs` — Paginated audit history.
- `GET /authentication` — Organisation provider toggles, email config metadata (secrets hidden), password/OTP policy.
- `PUT /authentication/methods` — Enables/disables methods (blocked with `422` if disabled globally).
- `PUT /authentication/policies` — Configures password/OTP policy within global floors/ceilings.
- `PUT /authentication/email` — Configures `platform_google_workspace`, `org_smtp`, or `org_google_workspace` with encrypted transport secrets.
- `POST /authentication/email/test` — Dispatches test email and marks config verified.
- `GET /authentication/logs` — Paginated organisation login attempts.

## 4. Super Admin API (`/api/v1/super-admin/*`)
*Middleware: `auth:api`, `EnsureActiveUser`, `EnsureSuperAdmin`*
- `GET /dashboard` — Global providers, daily login/OTP stats, recent security events.
- `PUT /providers/{providerCode}` — Level 1 global provider enable/disable toggle.
- `PUT /providers/email/platform-default` — Configures platform default Google Workspace sender.
- `POST /providers/email/platform-default/test` — Verifies platform email sender.
- `GET /security/events` — Paginated security alerts.
