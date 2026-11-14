<?php

namespace Tests\Security;

use App\Modules\OIDC\Services\OidcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OidcAndPkceComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pkce_s256_verification_accepts_valid_verifier_and_rejects_tampered(): void
    {
        $service = app(OidcService::class);
        $verifier = bin2hex(random_bytes(32)); // 64 chars (>= 43, <= 128)
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $this->assertTrue($service->verifyPkceS256($verifier, $challenge));
        $this->assertFalse($service->verifyPkceS256($verifier . 'tampered', $challenge));
        $this->assertFalse($service->verifyPkceS256('short', $challenge));
    }

    public function test_discovery_document_exposes_only_s256_and_disables_implicit_and_ropc(): void
    {
        $service = app(OidcService::class);
        $doc = $service->discoveryDocument();

        $this->assertSame('https://connect.twsd.me', $doc['issuer']);
        $this->assertSame(['S256'], $doc['code_challenge_methods_supported']);
        $this->assertSame(['code'], $doc['response_types_supported']);
        $this->assertNotContains('implicit', $doc['grant_types_supported']);
        $this->assertNotContains('password', $doc['grant_types_supported']);
    }

    public function test_signing_key_rotation_publishes_active_and_retiring_jwks_without_private_key(): void
    {
        $service = app(OidcService::class);
        $first = $service->generateAndActivateSigningKey();
        $second = $service->generateAndActivateSigningKey();

        $first->refresh();
        $this->assertSame('retiring', $first->status);
        $this->assertSame('active', $second->status);

        $jwks = $service->jwks();
        $kids = array_column($jwks['keys'], 'kid');

        $this->assertContains($first->kid, $kids);
        $this->assertContains($second->kid, $kids);

        foreach ($jwks['keys'] as $jwk) {
            $this->assertArrayNotHasKey('d', $jwk);
            $this->assertArrayNotHasKey('p', $jwk);
            $this->assertSame('RS256', $jwk['alg']);
        }
    }
}
