# TWSD Connect — MySQL 8 Database Schema Documentation

## Design Principles
- **Dual Identifiers:** Internal `BIGINT UNSIGNED` primary keys for high-speed joins; public `CHAR(36)` `uuid` columns (`HasPublicUuid`) for all external routes and JWT `sub`/`org_id` claims.
- **Passport Boundary:** Laravel Passport owns `oauth_clients`, `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_auth_codes`, and `oauth_device_codes`. `oauth_client_registry` links Passport clients to `organisations` and `applications` without altering Passport tables.
- **Encryption & Hashing at Rest:** Passwords use Argon2id; OTPs use HMAC-SHA256 bound to `otp_transactions.uuid`; SSO session tokens & auth codes use SHA-256; provider credentials, email transport secrets, and RSA private keys use Laravel `Crypt` (`encrypted` / `encrypted:array` Eloquent casts).

## Migration Index
1. `2026_01_01_000001_create_users_and_identities_tables.php`: `users`, `user_profiles`, `user_identities`, `identity_linking_challenges`
2. `2026_01_01_000002_create_organisations_tables.php`: `organisations`, `organisation_domains`, `organisation_users`, `organisation_branding`, `branding_versions`
3. `2026_01_01_000003_create_rbac_tables.php`: `roles`, `permissions`, `role_permissions`, `user_roles`
4. `2026_01_01_000004_create_applications_and_oauth_registry_tables.php`: `applications`, `oauth_client_registry`, `oauth_redirect_uris`, `application_auth_methods`
5. `2026_01_01_000005_create_auth_providers_and_policies_tables.php`: `auth_providers`, `provider_configurations`, `organisation_auth_methods`, `email_configurations`
6. `2026_01_01_000006_create_otp_sessions_and_audit_tables.php`: `otp_transactions`, `otp_delivery_logs`, `otp_attempts`, `sso_sessions`, `user_sessions`, `login_attempts`, `security_events`, `audit_logs`, `invitations`, `consent_records`, `provider_webhook_events`, `signing_key_metadata`, `configuration_history`
7. `2026_01_01_000007_add_cross_module_foreign_keys_and_oauth_transactions.php`: Adds `applications.twsd_subdomain`, `applications.custom_domain`, `applications.custom_domain_verified`, `applications.access_policy`, `applications.branding_overrides`, `applications.archived_at`; enforces cross-module FKs on `user_identities.organisation_id`, `user_roles.application_id`, `organisation_branding.*_version_id`; creates `oauth_transactions`.
