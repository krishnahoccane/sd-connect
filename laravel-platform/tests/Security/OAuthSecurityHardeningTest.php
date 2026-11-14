<?php

namespace Tests\Security;

use App\Models\AuthProvider;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OrganisationUser;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Modules\Applications\Services\ApplicationService;
use App\Modules\OAuth\Services\OAuthClientService;
use App\Modules\OIDC\Services\OAuthAuthorizationBridgeService;
use App\Modules\OIDC\Services\OidcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OAuthSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenantApp(string $type = 'web_confidential'): array
    {
        app(OidcService::class)->generateAndActivateSigningKey();
        AuthProvider::query()->firstOrCreate(
            ['code' => 'email_password'],
            ['name' => 'Email & Password', 'category' => 'password', 'is_globally_enabled' => true, 'health_status' => 'healthy']
        );

        $org = Organisation::create(['name' => 'Sec Org', 'slug' => 'sec-org-' . uniqid(), 'status' => 'active']);
        OrganisationAuthMethod::create(['organisation_id' => $org->id, 'provider_code' => 'email_password', 'is_enabled' => true]);

        $created = app(ApplicationService::class)->createApplication($org, [
            'name' => 'Sec App',
            'app_url' => 'https://sec.twsd.me',
            'application_type' => $type,
            'redirect_uris' => ['https://sec.twsd.me/callback'],
            'allowed_scopes' => ['openid', 'offline_access'],
            'allowed_grant_types' => ['authorization_code', 'refresh_token'],
        ]);

        $user = User::create(['primary_email' => 'u-' . uniqid() . '@twsd.me', 'status' => 'active']);
        OrganisationUser::create(['organisation_id' => $org->id, 'user_id' => $user->id, 'membership_status' => 'active']);

        return [$org, $created['application'], $created['client_id'], $created['plain_secret_once'], $user];
    }

    public function test_implicit_and_password_grants_are_rejected(): void
    {
        $this->postJson('/oauth/token', ['grant_type' => 'implicit'])
            ->assertStatus(400)
            ->assertJsonFragment(['error' => 'unsupported_grant_type']);

        $this->postJson('/oauth/token', ['grant_type' => 'password', 'username' => 'a@b.com', 'password' => 'secret'])
            ->assertStatus(400)
            ->assertJsonFragment(['error' => 'unsupported_grant_type']);
    }

    public function test_non_https_non_loopback_redirect_uri_is_rejected(): void
    {
        AuthProvider::query()->firstOrCreate(
            ['code' => 'email_password'],
            ['name' => 'Email & Password', 'category' => 'password', 'is_globally_enabled' => true, 'health_status' => 'healthy']
        );
        $org = Organisation::create(['name' => 'HTTP Test', 'slug' => 'http-test', 'status' => 'active']);
        OrganisationAuthMethod::create(['organisation_id' => $org->id, 'provider_code' => 'email_password', 'is_enabled' => true]);

        $this->expectException(InvalidArgumentException::class);
        app(ApplicationService::class)->createApplication($org, [
            'name' => 'Insecure App',
            'app_url' => 'https://app.twsd.me',
            'application_type' => 'spa_public',
            'redirect_uris' => ['http://app.twsd.me/callback'],
        ]);
    }

    public function test_pkce_downgrade_authorization_code_replay_and_refresh_reuse_detection(): void
    {
        [$org, $app, $clientId, $clientSecret, $user] = $this->setupTenantApp('web_confidential');

        // PKCE downgrade (no code_challenge / plain method) rejected
        $this->getJson('/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://sec.twsd.me/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'code_challenge' => 'plainvalue',
            'code_challenge_method' => 'plain',
        ]))->assertStatus(422);

        // Valid PKCE initiation
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $bridge = app(OAuthAuthorizationBridgeService::class);

        $tx = $bridge->initiateAuthorization(
            $app->oauthClientRegistry,
            'https://sec.twsd.me/callback',
            ['openid', 'offline_access'],
            'st1',
            'nn1',
            $challenge,
            'S256'
        );
        $code = $bridge->issueAuthorizationCode($tx, $user, ['pwd']);

        // First exchange succeeds
        $first = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => 'https://sec.twsd.me/callback',
            'code_verifier' => $verifier,
        ])->assertStatus(200);

        // Authorization code replay rejected + security event logged
        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => 'https://sec.twsd.me/callback',
            'code_verifier' => $verifier,
        ])->assertStatus(400)->assertJsonFragment(['error' => 'authorization_code_reused']);

        $this->assertTrue(SecurityEvent::query()->where('event_type', 'oauth.authorization_code_replay')->exists());

        // Refresh token rotation + reuse detection
        $rt1 = (string) $first->json('refresh_token');
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $rt1,
        ])->assertStatus(200);

        // Replaying old rt1 must compromise entire family
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $rt1,
        ])->assertStatus(400)->assertJsonFragment(['error' => 'refresh_token_reuse_detected']);

        $this->assertTrue(OAuthRefreshTokenFamily::query()->where('status', 'compromised')->exists());
        $this->assertTrue(SecurityEvent::query()->where('event_type', 'oauth.refresh_token_reuse_detected')->exists());
    }

    public function test_revoked_client_and_public_client_secret_rotation_restrictions(): void
    {
        [$org, $pubApp, $pubClientId] = $this->setupTenantApp('spa_public');

        $this->expectException(InvalidArgumentException::class);
        app(OAuthClientService::class)->rotateSecret($pubApp);

        app(OAuthClientService::class)->revokeClient($pubApp);
        $this->getJson('/oauth/authorize?' . http_build_query([
            'client_id' => $pubClientId,
            'redirect_uri' => 'https://sec.twsd.me/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))->assertStatus(403);
    }
}
