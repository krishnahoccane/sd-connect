<?php

namespace App\Modules\OAuth\Services;

use App\Models\Application;
use App\Models\OAuthClientRegistry;
use App\Models\OAuthRedirectUri;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OAuth\Validators\RedirectUriValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Passport\Client;

class OAuthClientService
{
    private const ALLOWED_GRANT_TYPES = ['authorization_code', 'refresh_token'];

    public function __construct(
        private readonly RedirectUriValidator $redirectUriValidator,
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Creates a native Passport OAuth client + tenant ownership registry entry.
     * Returns the plaintext secret ONCE ONLY during generation for confidential clients.
     *
     * @param array<int, string> $redirectUris
     * @param array<int, string> $postLogoutUris
     * @return array{registry: OAuthClientRegistry, client_id: string, plain_secret_once: string|null}
     */
    public function createClientForApplication(
        Application $application,
        string $clientType,
        array $redirectUris,
        array $postLogoutUris = [],
        ?User $actor = null,
        ?Request $request = null
    ): array {
        if (!in_array($clientType, ['confidential', 'public'], true)) {
            throw new InvalidArgumentException('Client type must be confidential or public.');
        }

        if (empty($redirectUris)) {
            throw new InvalidArgumentException('At least one exact redirect URI is required.');
        }

        $validatedRedirects = array_map(
            fn (string $u) => $this->redirectUriValidator->assertValidExactUri($u),
            $redirectUris
        );
        $validatedPostLogouts = array_map(
            fn (string $u) => $this->redirectUriValidator->assertValidExactUri($u),
            $postLogoutUris
        );

        $this->assertAllowedGrantTypes($application->allowed_grant_types ?? ['authorization_code']);

        return DB::transaction(function () use ($application, $clientType, $validatedRedirects, $validatedPostLogouts, $actor, $request): array {
            $plainSecret = $clientType === 'confidential'
                ? 'twsd_cs_' . bin2hex(random_bytes(32))
                : null;

            $passportClient = new Client();
            $passportClient->id = (string) Str::uuid();
            $passportClient->name = "{$application->organisation->slug}:{$application->slug}";
            $passportClient->secret = $plainSecret; // Passport hashes automatically via Client mutator/cast
            $passportClient->redirect_uris = $validatedRedirects;
            $passportClient->grant_types = array_values(array_intersect(
                $application->allowed_grant_types ?? ['authorization_code', 'refresh_token'],
                self::ALLOWED_GRANT_TYPES
            ));
            $passportClient->revoked = false;
            $passportClient->save();

            $registry = OAuthClientRegistry::create([
                'organisation_id' => $application->organisation_id,
                'application_id' => $application->id,
                'passport_client_id' => (string) $passportClient->id,
                'client_type' => $clientType,
                'pkce_required' => $clientType === 'public' ? true : (bool) $application->require_pkce,
                'is_active' => true,
            ]);

            $this->syncRedirectUriRecords($application, $validatedRedirects, $validatedPostLogouts);

            $this->auditService->record(
                action: 'oauth.client_created',
                actor: $actor,
                organisationId: $application->organisation_id,
                targetType: 'oauth_client_registry',
                targetUuid: $registry->uuid,
                afterState: [
                    'passport_client_id' => $registry->passport_client_id,
                    'client_type' => $clientType,
                    'pkce_required' => $registry->pkce_required,
                ],
                request: $request
            );

            return [
                'registry' => $registry,
                'client_id' => (string) $passportClient->id,
                'plain_secret_once' => $plainSecret,
            ];
        });
    }

    /**
     * Rotates confidential client secret and returns new plaintext secret ONCE ONLY.
     *
     * @return array{client_id: string, plain_secret_once: string, rotated_at: string}
     */
    public function rotateSecret(
        Application $application,
        ?User $actor = null,
        ?Request $request = null
    ): array {
        $registry = $application->oauthClientRegistry()->firstOrFail();

        if ($registry->client_type !== 'confidential') {
            throw new InvalidArgumentException('Public PKCE clients do not have a client secret.');
        }

        $passportClient = Client::query()->findOrFail($registry->passport_client_id);
        $newPlainSecret = 'twsd_cs_' . bin2hex(random_bytes(32));

        DB::transaction(function () use ($passportClient, $registry, $newPlainSecret, $application, $actor, $request): void {
            $passportClient->secret = $newPlainSecret;
            $passportClient->save();

            $registry->update(['secret_rotated_at' => now()]);

            $this->auditService->record(
                action: 'oauth.client_secret_rotated',
                actor: $actor,
                organisationId: $application->organisation_id,
                targetType: 'oauth_client_registry',
                targetUuid: $registry->uuid,
                afterState: ['passport_client_id' => $registry->passport_client_id, 'rotated' => true],
                request: $request
            );
        });

        return [
            'client_id' => $registry->passport_client_id,
            'plain_secret_once' => $newPlainSecret,
            'rotated_at' => now()->toIso8601String(),
        ];
    }

    public function revokeClient(Application $application, ?User $actor = null, ?Request $request = null): void
    {
        $registry = $application->oauthClientRegistry()->first();
        if (!$registry) {
            return;
        }

        DB::transaction(function () use ($registry, $application, $actor, $request): void {
            Client::query()->where('id', $registry->passport_client_id)->update(['revoked' => true]);
            $registry->update(['is_active' => false]);

            $this->auditService->record(
                action: 'oauth.client_revoked',
                actor: $actor,
                organisationId: $application->organisation_id,
                targetType: 'oauth_client_registry',
                targetUuid: $registry->uuid,
                request: $request
            );
        });
    }

    public function syncRedirectUris(Application $application, array $redirectUris, array $postLogoutUris = []): void
    {
        $validatedRedirects = array_map(
            fn (string $u) => $this->redirectUriValidator->assertValidExactUri($u),
            $redirectUris
        );
        $validatedPostLogouts = array_map(
            fn (string $u) => $this->redirectUriValidator->assertValidExactUri($u),
            $postLogoutUris
        );

        DB::transaction(function () use ($application, $validatedRedirects, $validatedPostLogouts): void {
            $this->syncRedirectUriRecords($application, $validatedRedirects, $validatedPostLogouts);

            $registry = $application->oauthClientRegistry()->first();
            if ($registry) {
                $passportClient = Client::query()->find($registry->passport_client_id);
                if ($passportClient) {
                    $passportClient->redirect_uris = $validatedRedirects;
                    $passportClient->save();
                }
            }
        });
    }

    public function isExactRedirectUriRegistered(Application $application, string $redirectUri, string $type = 'redirect'): bool
    {
        return OAuthRedirectUri::query()
            ->where('organisation_id', $application->organisation_id)
            ->where('application_id', $application->id)
            ->where('uri_type', $type)
            ->where('uri', $redirectUri)
            ->where('is_active', true)
            ->exists();
    }

    private function syncRedirectUriRecords(Application $application, array $redirects, array $postLogouts): void
    {
        OAuthRedirectUri::query()->where('application_id', $application->id)->delete();

        foreach ($redirects as $uri) {
            OAuthRedirectUri::create([
                'organisation_id' => $application->organisation_id,
                'application_id' => $application->id,
                'uri_type' => 'redirect',
                'uri' => $uri,
                'is_active' => true,
            ]);
        }

        foreach ($postLogouts as $uri) {
            OAuthRedirectUri::create([
                'organisation_id' => $application->organisation_id,
                'application_id' => $application->id,
                'uri_type' => 'post_logout',
                'uri' => $uri,
                'is_active' => true,
            ]);
        }
    }

    private function assertAllowedGrantTypes(array $grantTypes): void
    {
        foreach ($grantTypes as $grant) {
            if (!in_array($grant, self::ALLOWED_GRANT_TYPES, true)) {
                throw new InvalidArgumentException("Grant type [{$grant}] is prohibited by platform security policy.");
            }
        }
    }
}
