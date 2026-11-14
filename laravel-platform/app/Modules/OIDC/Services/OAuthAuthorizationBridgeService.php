<?php

namespace App\Modules\OIDC\Services;

use App\Models\OAuthClientRegistry;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\OAuthTransaction;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OAuth\Services\OAuthClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

class OAuthAuthorizationBridgeService
{
    public function __construct(
        private readonly OAuthClientService $oauthClientService,
        private readonly OidcService $oidcService,
        private readonly AuditService $auditService
    ) {
    }

    public function initiateAuthorization(
        OAuthClientRegistry $registry,
        string $redirectUri,
        array $requestedScopes,
        ?string $state,
        ?string $nonce,
        ?string $codeChallenge,
        ?string $codeChallengeMethod
    ): OAuthTransaction {
        $application = $registry->application;
        $organisation = $application->organisation;

        if (!$registry->is_active || !$application->isActive()) {
            throw new InvalidArgumentException('Application or organisation is suspended.');
        }

        $passportClient = Client::query()->find($registry->passport_client_id);
        if (!$passportClient || $passportClient->revoked) {
            throw new InvalidArgumentException('OAuth client has been revoked.');
        }

        if (!$this->oauthClientService->isExactRedirectUriRegistered($application, $redirectUri, 'redirect')) {
            throw new InvalidArgumentException('redirect_uri does not exactly match any registered URI for this application.');
        }

        if ($registry->pkce_required || $application->require_pkce) {
            if (empty($codeChallenge) || strtoupper((string) $codeChallengeMethod) !== 'S256') {
                throw new InvalidArgumentException('PKCE S256 code_challenge is mandatory for this application.');
            }
        }

        $allowedScopes = $application->allowed_scopes ?? ['openid'];
        foreach ($requestedScopes as $scope) {
            if (!in_array($scope, $allowedScopes, true)) {
                throw new InvalidArgumentException("Requested scope [{$scope}] is not permitted for this application.");
            }
        }

        return OAuthTransaction::create([
            'organisation_id' => $organisation->id,
            'application_id' => $application->id,
            'passport_client_id' => $registry->passport_client_id,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'requested_scopes' => $requestedScopes,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function issueAuthorizationCode(OAuthTransaction $transaction, User $user, array $amr): string
    {
        if ($transaction->expires_at->isPast() || !in_array($transaction->status, ['pending', 'authenticated', 'consented'], true)) {
            throw new InvalidArgumentException('Authorization transaction is expired or invalid.');
        }

        if (!$user->hasActiveMembershipIn($transaction->organisation_id)) {
            throw new InvalidArgumentException('User or organisation membership is inactive.');
        }

        $code = 'twsd_ac_' . bin2hex(random_bytes(32));

        $transaction->update([
            'authenticated_user_id' => $user->id,
            'amr' => $amr,
            'authorization_code_hash' => hash('sha256', $code),
            'status' => 'code_issued',
        ]);

        return $code;
    }

    /**
     * Exchanges single-use authorization code + PKCE S256 verifier for Passport access token, rotating refresh token, and OIDC id_token.
     *
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(
        string $clientId,
        ?string $clientSecret,
        string $code,
        string $redirectUri,
        ?string $codeVerifier,
        ?Request $request = null
    ): array {
        return DB::transaction(function () use ($clientId, $clientSecret, $code, $redirectUri, $codeVerifier, $request): array {
            $registry = $this->authenticateClient($clientId, $clientSecret, $request);

            $codeHash = hash('sha256', $code);
            $transaction = OAuthTransaction::query()
                ->where('authorization_code_hash', $codeHash)
                ->where('passport_client_id', $clientId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                throw new InvalidArgumentException('invalid_grant');
            }

            if ($transaction->status === 'exchanged') {
                $this->auditService->recordSecurityEvent('oauth.authorization_code_replay', 'critical', $registry->organisation_id, $transaction->authenticated_user_id, ['tx' => $transaction->uuid], $request);
                throw new InvalidArgumentException('authorization_code_reused');
            }

            if ($transaction->status !== 'code_issued' || $transaction->expires_at->isPast()) {
                throw new InvalidArgumentException('authorization_code_expired');
            }

            if (!hash_equals($transaction->redirect_uri, $redirectUri)) {
                throw new InvalidArgumentException('redirect_uri_mismatch');
            }

            if ($registry->pkce_required && !$this->oidcService->verifyPkceS256((string) $codeVerifier, (string) $transaction->code_challenge)) {
                throw new InvalidArgumentException('pkce_verification_failed');
            }

            $user = $transaction->user()->firstOrFail();
            if (!$user->hasActiveMembershipIn($registry->organisation_id)) {
                throw new InvalidArgumentException('user_or_membership_suspended');
            }

            $transaction->update([
                'status' => 'exchanged',
                'exchanged_at' => now(),
            ]);

            $scopes = $transaction->requested_scopes ?? ['openid'];
            return $this->mintTokenBundle(
                user: $user,
                registry: $registry,
                clientId: $clientId,
                scopes: $scopes,
                nonce: $transaction->nonce,
                amr: $transaction->amr ?? ['pwd'],
                familyUuid: (string) Str::uuid()
            );
        });
    }

    /**
     * Rotates refresh token and detects refresh-token reuse across the entire token family.
     *
     * @return array<string, mixed>
     */
    public function rotateRefreshToken(
        string $clientId,
        ?string $clientSecret,
        string $refreshToken,
        ?Request $request = null
    ): array {
        return DB::transaction(function () use ($clientId, $clientSecret, $refreshToken, $request): array {
            $registry = $this->authenticateClient($clientId, $clientSecret, $request);
            $tokenHash = hash('sha256', $refreshToken);

            $record = OAuthRefreshTokenFamily::query()
                ->where('token_hash', $tokenHash)
                ->where('passport_client_id', $clientId)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                throw new InvalidArgumentException('invalid_refresh_token');
            }

            if ($record->status === 'rotated' || $record->status === 'compromised') {
                // Reuse detected! Revoke entire family + all issued Passport access tokens
                $familyTokens = OAuthRefreshTokenFamily::query()
                    ->where('family_uuid', $record->family_uuid)
                    ->get();

                $passportTokenIds = $familyTokens->pluck('passport_token_id')->filter()->all();
                if (!empty($passportTokenIds)) {
                    Token::query()->whereIn('id', $passportTokenIds)->update(['revoked' => true]);
                }

                OAuthRefreshTokenFamily::query()
                    ->where('family_uuid', $record->family_uuid)
                    ->update(['status' => 'compromised', 'revoked_at' => now()]);

                $this->auditService->recordSecurityEvent(
                    eventType: 'oauth.refresh_token_reuse_detected',
                    severity: 'critical',
                    organisationId: $registry->organisation_id,
                    userId: $record->user_id,
                    context: ['family_uuid' => $record->family_uuid, 'client_id' => $clientId],
                    request: $request
                );

                throw new InvalidArgumentException('refresh_token_reuse_detected');
            }

            if ($record->status !== 'active' || $record->expires_at->isPast()) {
                throw new InvalidArgumentException('refresh_token_expired_or_revoked');
            }

            $user = $record->user()->firstOrFail();
            if (!$user->hasActiveMembershipIn($registry->organisation_id)) {
                throw new InvalidArgumentException('user_or_membership_suspended');
            }

            if ($record->passport_token_id) {
                Token::query()->where('id', $record->passport_token_id)->update(['revoked' => true]);
            }

            $record->update([
                'status' => 'rotated',
                'rotated_at' => now(),
            ]);

            return $this->mintTokenBundle(
                user: $user,
                registry: $registry,
                clientId: $clientId,
                scopes: $record->scopes ?? ['openid'],
                nonce: null,
                amr: $record->amr ?? ['pwd'],
                familyUuid: $record->family_uuid
            );
        });
    }

    private function authenticateClient(string $clientId, ?string $clientSecret, ?Request $request): OAuthClientRegistry
    {
        $registry = OAuthClientRegistry::query()
            ->with(['application.organisation'])
            ->where('passport_client_id', $clientId)
            ->where('is_active', true)
            ->first();

        if (!$registry || !$registry->application || !$registry->application->isActive()) {
            throw new InvalidArgumentException('invalid_client');
        }

        $passportClient = Client::query()->find($clientId);
        if (!$passportClient || $passportClient->revoked) {
            throw new InvalidArgumentException('client_revoked');
        }

        if ($registry->client_type === 'confidential') {
            if (empty($clientSecret) || !password_verify($clientSecret, (string) $passportClient->secret)) {
                $this->auditService->recordSecurityEvent('oauth.invalid_client_secret', 'warning', $registry->organisation_id, null, ['client_id' => $clientId], $request);
                throw new InvalidArgumentException('invalid_client_credentials');
            }
        }

        return $registry;
    }

    private function mintTokenBundle(
        User $user,
        OAuthClientRegistry $registry,
        string $clientId,
        array $scopes,
        ?string $nonce,
        array $amr,
        string $familyUuid
    ): array {
        $tokenResult = $user->createToken("oidc:{$registry->application->slug}", $scopes);

        $response = [
            'token_type' => 'Bearer',
            'expires_in' => (int) ($registry->application->access_token_ttl_minutes * 60),
            'access_token' => $tokenResult->accessToken,
            'scope' => implode(' ', $scopes),
        ];

        $allowedGrants = $registry->application->allowed_grant_types ?? ['authorization_code'];
        if (in_array('refresh_token', $allowedGrants, true)) {
            $plainRefresh = 'twsd_rt_' . bin2hex(random_bytes(32));
            OAuthRefreshTokenFamily::create([
                'family_uuid' => $familyUuid,
                'organisation_id' => $registry->organisation_id,
                'application_id' => $registry->application_id,
                'user_id' => $user->id,
                'passport_client_id' => $clientId,
                'passport_token_id' => (string) $tokenResult->token->id,
                'token_hash' => hash('sha256', $plainRefresh),
                'scopes' => $scopes,
                'amr' => $amr,
                'status' => 'active',
                'expires_at' => now()->addDays((int) ($registry->application->refresh_token_ttl_days ?? 30)),
            ]);
            $response['refresh_token'] = $plainRefresh;
        }

        if (in_array('openid', $scopes, true)) {
            $response['id_token'] = $this->oidcService->issueIdToken(
                user: $user,
                organisation: $registry->application->organisation,
                application: $registry->application,
                clientId: $clientId,
                scopes: $scopes,
                nonce: $nonce,
                amr: $amr
            );
        }

        return $response;
    }
}
