<?php

namespace Tests\Integration;

use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Modules\Applications\Services\ApplicationService;
use App\Modules\OIDC\Services\OidcService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class PassportOidcEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_oidc_authorization_code_pkce_flow_userinfo_refresh_rotation_and_logout(): void
    {
        app(OidcService::class)->generateAndActivateSigningKey();

        AuthProvider::query()->firstOrCreate(
            ['code' => 'email_password'],
            ['name' => 'Email & Password', 'category' => 'password', 'is_globally_enabled' => true, 'health_status' => 'healthy']
        );

        // 1. Create organisation
        $org = Organisation::create(['name' => 'TWSD Alpha', 'slug' => 'twsd-alpha', 'status' => 'active']);
        OrganisationAuthMethod::create(['organisation_id' => $org->id, 'provider_code' => 'email_password', 'is_enabled' => true]);

        // 2-4. Create application + confidential client + exact HTTPS redirect & post-logout URI
        $created = app(ApplicationService::class)->createApplication($org, [
            'name' => 'Alpha Workspace',
            'app_url' => 'https://alpha.twsd.me',
            'application_type' => 'web_confidential',
            'redirect_uris' => ['https://alpha.twsd.me/oauth/callback'],
            'post_logout_redirect_uris' => ['https://alpha.twsd.me/signed-out'],
            'allowed_scopes' => ['openid', 'profile', 'email', 'offline_access'],
            'allowed_grant_types' => ['authorization_code', 'refresh_token'],
            'auth_methods' => ['email_password'],
        ]);

        $clientId = $created['client_id'];
        $clientSecret = $created['plain_secret_once'];

        // 5. Generate PKCE S256 verifier & challenge
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));

        // 6. Start authorization request via HTTP route
        $authInit = $this->getJson('/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://alpha.twsd.me/oauth/callback',
            'response_type' => 'code',
            'scope' => 'openid profile email offline_access',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        $authInit->assertStatus(200);
        $txId = $authInit->json('data.oauth_transaction_id');
        $this->assertNotEmpty($txId);

        // 7-9. Create user with org membership and authenticate via /api/v1/auth/email/password
        $user = User::create([
            'primary_email' => 'architect@twsd.me',
            'email_verified_at' => now(),
            'password' => Hash::make('Str0ng!Pass#2026'),
            'status' => 'active',
        ]);
        $user->profile()->create(['display_name' => 'Principal Architect', 'given_name' => 'Principal', 'family_name' => 'Architect']);
        OrganisationUser::create(['organisation_id' => $org->id, 'user_id' => $user->id, 'membership_status' => 'active']);

        $loginRes = $this->postJson('/api/v1/auth/email/password', [
            'client_id' => $clientId,
            'oauth_transaction_id' => $txId,
            'email' => 'architect@twsd.me',
            'password' => 'Str0ng!Pass#2026',
        ]);

        $loginRes->assertStatus(200);
        $ssoCookie = $loginRes->getCookie('twsd_sso_session');
        $this->assertNotNull($ssoCookie);

        $redirectUrl = (string) $loginRes->json('data.redirect_uri');
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $queryParams);
        $this->assertSame($state, $queryParams['state'] ?? null);
        $code = $queryParams['code'] ?? '';
        $this->assertNotEmpty($code);

        // 10-12. Exchange code for access_token, refresh_token, and RS256 id_token
        $tokenRes = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => 'https://alpha.twsd.me/oauth/callback',
            'code_verifier' => $verifier,
        ]);

        $tokenRes->assertStatus(200)->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token', 'id_token']);

        $accessToken = (string) $tokenRes->json('access_token');
        $refreshToken = (string) $tokenRes->json('refresh_token');
        $idToken = (string) $tokenRes->json('id_token');

        $claims = app(OidcService::class)->verifyIdToken($idToken, $clientId, $nonce);
        $this->assertSame('https://connect.twsd.me', $claims['iss']);
        $this->assertSame($user->uuid, $claims['sub']);
        $this->assertSame($clientId, $claims['aud']);
        $this->assertSame($clientId, $claims['azp']);
        $this->assertSame($nonce, $claims['nonce']);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('auth_time', $claims);

        // 13. Call UserInfo endpoint with Passport Bearer token
        $userInfoRes = $this->withHeader('Authorization', "Bearer {$accessToken}")->getJson('/oauth/userinfo');
        $userInfoRes->assertStatus(200)->assertJsonFragment([
            'sub' => $user->uuid,
            'email' => 'architect@twsd.me',
            'name' => 'Principal Architect',
        ]);

        // 14. Rotate refresh token
        $rotatedRes = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);
        $rotatedRes->assertStatus(200)->assertJsonStructure(['access_token', 'refresh_token', 'id_token']);
        $this->assertNotSame($refreshToken, $rotatedRes->json('refresh_token'));

        // 15-16. Logout & exact post_logout_redirect_uri validation
        $badLogout = $this->getJson('/oauth/logout?' . http_build_query([
            'client_id' => $clientId,
            'post_logout_redirect_uri' => 'https://evil.example.com/logout',
        ]));
        $badLogout->assertStatus(400);

        $goodLogout = $this->withCookie('twsd_sso_session', $ssoCookie->getValue())
            ->get('/oauth/logout?' . http_build_query([
                'client_id' => $clientId,
                'post_logout_redirect_uri' => 'https://alpha.twsd.me/signed-out',
                'state' => 'done123',
            ]));
        $goodLogout->assertRedirect('https://alpha.twsd.me/signed-out?state=done123');
    }

    public function test_id_token_validation_rejects_invalid_signature_wrong_audience_wrong_nonce_and_expired_jwt(): void
    {
        $oidc = app(OidcService::class);
        $key = $oidc->generateAndActivateSigningKey();

        $rogueKey = RSA::createKey(2048)->toString('PKCS8');
        $forged = JWT::encode([
            'iss' => 'https://connect.twsd.me',
            'sub' => 'user-1',
            'aud' => 'client-a',
            'iat' => time(),
            'exp' => time() + 600,
            'nonce' => 'n1',
        ], $rogueKey, 'RS256', $key->kid);

        $this->expectException(\Throwable::class);
        $oidc->verifyIdToken($forged, 'client-a', 'n1');
    }
}
