# OpenID Connect (OIDC) Extension Evaluation for Laravel 12 + Passport 13

## Context
Laravel Passport implements OAuth 2.0 (RFC 6749, RFC 7636 PKCE, RFC 7009 Revocation, RFC 7662 Introspection) via `league/oauth2-server`, but Passport does **not** natively implement OpenID Connect Core 1.0 (ID Tokens, `nonce` echo & validation, `/.well-known/openid-configuration`, `/.well-known/jwks.json`, `/oauth/userinfo`, `acr`/`amr` claims, or RP-Initiated Logout).

## Candidate Evaluation
1. **`jeremy379/laravel-openid-connect`** (Passport bridge package):
   - **Strengths**: Extends Passport's `AuthorizationServer` grant response to inject `id_token` when the `openid` scope is present.
   - **Incompatibilities / Gaps Identified**:
     - Upstream release cadence lags behind Laravel 12 / Passport 13 breaking container signatures.
     - Does not natively enforce key rotation metadata (`signing_key_metadata` table with `kid` lifecycle `active -> retiring -> retired`).
     - Does not enforce `post_logout_redirect_uri` exact matching against tenant-isolated `oauth_redirect_uris`.
     - Lacks multi-tenant `org_id`, `org_slug`, and `amr` custom claim injection scoped by `ResolveApplication` middleware.
2. **Selected Architecture: Dedicated OIDC Core 1.0 Layer on Passport + `firebase/php-jwt` + `phpseclib/phpseclib`**:
   - **Why**:
     - Zero custom cryptography: uses PHP OpenSSL / `phpseclib` RSA-2048 key generation and `firebase/php-jwt` RS256 signing.
     - Full Laravel 12 & Passport 13 compatibility while Passport continues to own `oauth_clients`, `oauth_access_tokens`, `oauth_refresh_tokens`, and `oauth_auth_codes`.
     - Explicit enforcement of:
       - `PKCE S256` verification (rejecting `plain` and non-PKCE public client requests).
       - `nonce` persistence on authorization transactions and exact echo in `id_token`.
       - Disabled `implicit` and `password` (ROPC) grants.
       - Encrypted private key storage at rest (`signing_key_metadata.encrypted_private_key_pem`).
       - Exact `redirect_uri` and `post_logout_redirect_uri` validation (zero wildcards).
