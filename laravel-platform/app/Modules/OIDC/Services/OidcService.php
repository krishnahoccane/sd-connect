<?php

namespace App\Modules\OIDC\Services;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\SigningKeyMetadata;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use phpseclib3\Crypt\RSA;

class OidcService
{
    public function discoveryDocument(): array
    {
        $issuer = rtrim((string) config('oidc.issuer'), '/');
        $d = config('oidc.discovery');

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => "{$issuer}/oauth/authorize",
            'token_endpoint' => "{$issuer}/oauth/token",
            'userinfo_endpoint' => "{$issuer}/oauth/userinfo",
            'revocation_endpoint' => "{$issuer}/oauth/revoke",
            'introspection_endpoint' => "{$issuer}/oauth/introspect",
            'end_session_endpoint' => "{$issuer}/oauth/logout",
            'jwks_uri' => "{$issuer}/.well-known/jwks.json",
            'scopes_supported' => $d['scopes_supported'],
            'response_types_supported' => $d['response_types_supported'],
            'response_modes_supported' => $d['response_modes_supported'],
            'grant_types_supported' => $d['grant_types_supported'],
            'subject_types_supported' => $d['subject_types_supported'],
            'id_token_signing_alg_values_supported' => $d['id_token_signing_alg_values_supported'],
            'token_endpoint_auth_methods_supported' => $d['token_endpoint_auth_methods_supported'],
            'code_challenge_methods_supported' => $d['code_challenge_methods_supported'],
            'claims_supported' => $d['claims_supported'],
        ];
    }

    public function jwks(): array
    {
        $keys = SigningKeyMetadata::query()
            ->whereIn('status', ['active', 'retiring'])
            ->orderByDesc('activated_at')
            ->get();

        $jwkList = [];
        foreach ($keys as $keyRecord) {
            $details = openssl_pkey_get_details(openssl_pkey_get_public($keyRecord->public_key_pem));
            if (!$details || !isset($details['rsa'])) {
                continue;
            }
            $jwkList[] = [
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => $keyRecord->algorithm,
                'kid' => $keyRecord->kid,
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ];
        }

        return ['keys' => $jwkList];
    }

    public function issueIdToken(
        User $user,
        Organisation $organisation,
        Application $application,
        string $clientId,
        array $scopes,
        ?string $nonce = null,
        array $amr = ['pwd']
    ): string {
        $signingKey = SigningKeyMetadata::query()
            ->where('status', 'active')
            ->latest('activated_at')
            ->firstOrFail();

        $now = time();
        $ttl = (int) config('oidc.id_token_ttl_seconds', 3600);
        $issuer = rtrim((string) config('oidc.issuer'), '/');

        $payload = [
            'iss' => $issuer,
            'sub' => $user->uuid,
            'aud' => $clientId,
            'azp' => $clientId,
            'iat' => $now,
            'exp' => $now + $ttl,
            'auth_time' => $now,
            'amr' => $amr,
            'org_id' => $organisation->uuid,
            'org_slug' => $organisation->slug,
        ];

        if ($nonce !== null && $nonce !== '') {
            $payload['nonce'] = $nonce;
        }

        if (in_array('profile', $scopes, true) && $user->profile) {
            $payload['name'] = $user->profile->display_name;
            $payload['given_name'] = $user->profile->given_name;
            $payload['family_name'] = $user->profile->family_name;
        }

        if (in_array('email', $scopes, true)) {
            $payload['email'] = $user->primary_email;
            $payload['email_verified'] = $user->email_verified_at !== null;
        }

        if (in_array('phone', $scopes, true) && $user->primary_phone) {
            $payload['phone_number'] = $user->primary_phone;
            $payload['phone_number_verified'] = $user->phone_verified_at !== null;
        }

        $privateKeyPem = (string) $signingKey->encrypted_private_key_pem;
        $keyResource = openssl_pkey_get_private($privateKeyPem);
        if ($keyResource === false) {
            throw new \RuntimeException("Active OIDC signing key [{$signingKey->kid}] failed OpenSSL validation.");
        }

        return JWT::encode($payload, $keyResource, 'RS256', $signingKey->kid);
    }

    /**
     * Validates ID Token signature (via active/retiring public key), issuer, audience, and optional nonce.
     *
     * @return array<string, mixed>
     */
    public function verifyIdToken(string $idToken, string $expectedAudience, ?string $expectedNonce = null): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Malformed JWT.');
        }

        $header = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/')), true);
        $kid = $header['kid'] ?? null;
        if (!is_string($kid) || $kid === '') {
            throw new \InvalidArgumentException('Missing JWT kid header.');
        }

        $keyRecord = SigningKeyMetadata::query()
            ->where('kid', $kid)
            ->whereIn('status', ['active', 'retiring'])
            ->first();

        if (!$keyRecord) {
            throw new \InvalidArgumentException('Unknown or retired signing key.');
        }

        $decoded = (array) JWT::decode($idToken, new \Firebase\JWT\Key($keyRecord->public_key_pem, 'RS256'));
        $expectedIssuer = rtrim((string) config('oidc.issuer'), '/');

        if (($decoded['iss'] ?? null) !== $expectedIssuer) {
            throw new \InvalidArgumentException('Invalid ID Token issuer.');
        }

        $aud = $decoded['aud'] ?? null;
        if ($aud !== $expectedAudience && !(is_array($aud) && in_array($expectedAudience, $aud, true))) {
            throw new \InvalidArgumentException('Invalid ID Token audience.');
        }

        if ($expectedNonce !== null && ($decoded['nonce'] ?? null) !== $expectedNonce) {
            throw new \InvalidArgumentException('Invalid ID Token nonce.');
        }

        return $decoded;
    }

    public function verifyPkceS256(string $codeVerifier, string $storedCodeChallenge): bool
    {
        if (strlen($codeVerifier) < 43 || strlen($codeVerifier) > 128) {
            return false;
        }

        $computed = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));

        return hash_equals($storedCodeChallenge, $computed);
    }

    public function generateAndActivateSigningKey(): SigningKeyMetadata
    {
        $privateKey = RSA::createKey(2048);
        $publicKeyPem = $privateKey->getPublicKey()->toString('PKCS8');
        $privateKeyPem = $privateKey->toString('PKCS8');
        $kid = 'twsd-' . gmdate('Ymd') . '-' . Str::lower(Str::random(8));

        SigningKeyMetadata::query()
            ->where('status', 'active')
            ->update(['status' => 'retiring']);

        return SigningKeyMetadata::create([
            'kid' => $kid,
            'algorithm' => 'RS256',
            'public_key_pem' => $publicKeyPem,
            'encrypted_private_key_pem' => $privateKeyPem,
            'status' => 'active',
            'activated_at' => now(),
            'expires_at' => now()->addDays((int) config('oidc.key_rotation_days', 90)),
        ]);
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
