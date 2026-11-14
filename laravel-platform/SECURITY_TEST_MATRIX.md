# TWSD Connect — Security Test Matrix (`SECURITY_TEST_MATRIX.md`)

| Domain | Security Control | Test File & Method | Execution Status in Sandbox |
|---|---|---|---|
| **OIDC & PKCE** | PKCE `S256` verification & tamper rejection | `OidcAndPkceComplianceTest::test_pkce_s256_verification_accepts_valid_verifier_and_rejects_tampered` | NOT EXECUTED (No PHP) |
| **OIDC Discovery** | `S256` only, `implicit` & `password` absent | `OidcAndPkceComplianceTest::test_discovery_document_exposes_only_s256_and_disables_implicit_and_ropc` | NOT EXECUTED (No PHP) |
| **JWKS & Key Rotation** | Active + retiring keys; zero private key fields exposed | `OidcAndPkceComplianceTest::test_signing_key_rotation_publishes_active_and_retiring_jwks_without_private_key` | NOT EXECUTED (No PHP) |
| **End-to-End OIDC** | Auth -> Code -> Token -> ID Token (`iss`,`sub`,`aud`,`azp`,`nonce`,`exp`) -> UserInfo -> Refresh -> Logout | `PassportOidcEndToEndTest::test_complete_oidc_authorization_code_pkce_flow_userinfo_refresh_rotation_and_logout` | NOT EXECUTED (No PHP) |
| **JWT Forgery** | Invalid RSA signature rejected | `PassportOidcEndToEndTest::test_id_token_validation_rejects_invalid_signature_wrong_audience_wrong_nonce_and_expired_jwt` | NOT EXECUTED (No PHP) |
| **OAuth Grants** | `implicit` & `password` grants blocked (`400`) | `OAuthSecurityHardeningTest::test_implicit_and_password_grants_are_rejected` | NOT EXECUTED (No PHP) |
| **Redirect URIs** | Wildcards, mismatches, and non-loopback HTTP rejected | `ApplicationLifecycleAndOAuthClientTest`, `OAuthSecurityHardeningTest` | NOT EXECUTED (No PHP) |
| **Replay & Refresh Reuse** | Auth code replay + old refresh token compromises entire family | `OAuthSecurityHardeningTest::test_pkce_downgrade_authorization_code_replay_and_refresh_reuse_detection` | NOT EXECUTED (No PHP) |
| **Password & Lockout** | Argon2id login + 5-attempt lockout | `EmailPasswordAndOtpAuthenticationTest::test_email_password_login_succeeds_and_invalid_password_triggers_lockout` | NOT EXECUTED (No PHP) |
| **OTP Hardening** | Expiry, replay, brute force, cooldown, purpose/recipient/app/org mismatch | `EmailPasswordAndOtpAuthenticationTest`, `AuthenticationAndOtpEdgeCasesTest` | NOT EXECUTED (No PHP) |
| **Sender & Secret Hygiene** | Tenant email isolation + audit log secret redaction | `EmailSenderIsolationAndAdminPolicyTest`, `AuthenticationAndOtpEdgeCasesTest` | NOT EXECUTED (No PHP) |
| **Tenant Isolation & RBAC** | 2-tenant API probes, ID tampering, Org->SuperAdmin privilege escalation, suspended org/user | `TenantIsolationAndAuthorizationMatrixTest`, `CrossTenantAndAuditSecurityTest` | NOT EXECUTED (No PHP) |
