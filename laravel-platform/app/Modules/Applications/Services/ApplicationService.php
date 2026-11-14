<?php

namespace App\Modules\Applications\Services;

use App\Models\Application;
use App\Models\ApplicationAuthMethod;
use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OrganisationDomain;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OAuth\Services\OAuthClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ApplicationService
{
    public function __construct(
        private readonly OAuthClientService $oauthClientService,
        private readonly AuditService $auditService
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{application: Application, client_id: string, plain_secret_once: string|null}
     */
    public function createApplication(
        Organisation $organisation,
        array $data,
        ?User $actor = null,
        ?Request $request = null
    ): array {
        $slug = Str::slug($data['slug'] ?? $data['name']);
        if (Application::query()->where('organisation_id', $organisation->id)->where('slug', $slug)->exists()) {
            throw new InvalidArgumentException("Application slug [{$slug}] already exists in this organisation.");
        }

        $twsdSubdomain = $this->normalizeSubdomain($data['twsd_subdomain'] ?? "{$organisation->slug}-{$slug}");
        $customDomain = $this->validateCustomDomainForOrg($organisation, $data['custom_domain'] ?? null);

        return DB::transaction(function () use ($organisation, $data, $slug, $twsdSubdomain, $customDomain, $actor, $request): array {
            $appType = $data['application_type'] ?? 'web_confidential';
            $clientType = in_array($appType, ['spa_public', 'mobile_public'], true) ? 'public' : 'confidential';

            $application = Application::create([
                'organisation_id' => $organisation->id,
                'name' => $data['name'],
                'slug' => $slug,
                'twsd_subdomain' => $twsdSubdomain,
                'custom_domain' => $customDomain,
                'custom_domain_verified' => $customDomain !== null,
                'app_url' => $data['app_url'],
                'description' => $data['description'] ?? null,
                'logo_path' => $data['logo_path'] ?? null,
                'application_type' => $appType,
                'status' => 'active',
                'jit_provisioning_override' => $data['jit_provisioning_override'] ?? 'inherit',
                'require_pkce' => $clientType === 'public' ? true : (bool) ($data['require_pkce'] ?? true),
                'allowed_scopes' => $data['allowed_scopes'] ?? ['openid', 'profile', 'email'],
                'allowed_grant_types' => $data['allowed_grant_types'] ?? ['authorization_code', 'refresh_token'],
                'access_token_ttl_minutes' => (int) ($data['access_token_ttl_minutes'] ?? 15),
                'refresh_token_ttl_days' => (int) ($data['refresh_token_ttl_days'] ?? 30),
                'access_policy' => $data['access_policy'] ?? ['mode' => 'all_org_members'],
                'branding_overrides' => $data['branding_overrides'] ?? null,
            ]);

            $clientBundle = $this->oauthClientService->createClientForApplication(
                application: $application,
                clientType: $clientType,
                redirectUris: $data['redirect_uris'],
                postLogoutUris: $data['post_logout_redirect_uris'] ?? [],
                actor: $actor,
                request: $request
            );

            $requestedMethods = $data['auth_methods'] ?? ['email_password'];
            $this->syncAuthMethods($organisation, $application, $requestedMethods, $actor, $request);

            $this->auditService->record(
                action: 'application.created',
                actor: $actor,
                organisationId: $organisation->id,
                targetType: 'application',
                targetUuid: $application->uuid,
                afterState: $application->only(['name', 'slug', 'twsd_subdomain', 'custom_domain', 'application_type', 'status']),
                request: $request
            );

            return [
                'application' => $application->fresh(['oauthClientRegistry', 'redirectUris', 'authMethods']),
                'client_id' => $clientBundle['client_id'],
                'plain_secret_once' => $clientBundle['plain_secret_once'],
            ];
        });
    }

    public function updateApplication(
        Organisation $organisation,
        Application $application,
        array $data,
        ?User $actor = null,
        ?Request $request = null
    ): Application {
        $this->assertOwnership($organisation, $application);

        $before = $application->only(['name', 'app_url', 'description', 'status', 'access_policy', 'custom_domain']);

        return DB::transaction(function () use ($organisation, $application, $data, $before, $actor, $request): Application {
            if (array_key_exists('custom_domain', $data)) {
                $data['custom_domain'] = $this->validateCustomDomainForOrg($organisation, $data['custom_domain']);
                $data['custom_domain_verified'] = $data['custom_domain'] !== null;
            }

            $application->fill(collect($data)->only([
                'name',
                'app_url',
                'description',
                'logo_path',
                'custom_domain',
                'custom_domain_verified',
                'jit_provisioning_override',
                'require_pkce',
                'allowed_scopes',
                'access_token_ttl_minutes',
                'refresh_token_ttl_days',
                'access_policy',
                'branding_overrides',
            ])->all());
            $application->save();

            if (isset($data['redirect_uris'])) {
                $this->oauthClientService->syncRedirectUris(
                    $application,
                    $data['redirect_uris'],
                    $data['post_logout_redirect_uris'] ?? []
                );
            }

            if (isset($data['auth_methods'])) {
                $this->syncAuthMethods($organisation, $application, $data['auth_methods'], $actor, $request);
            }

            $this->auditService->record(
                action: 'application.updated',
                actor: $actor,
                organisationId: $organisation->id,
                targetType: 'application',
                targetUuid: $application->uuid,
                beforeState: $before,
                afterState: $application->only(['name', 'app_url', 'description', 'status', 'access_policy', 'custom_domain']),
                request: $request
            );

            return $application->fresh(['oauthClientRegistry', 'redirectUris', 'authMethods']);
        });
    }

    public function suspendApplication(Organisation $organisation, Application $application, ?User $actor = null, ?Request $request = null): Application
    {
        $this->assertOwnership($organisation, $application);
        $application->update(['status' => 'suspended']);
        $this->auditService->record('application.suspended', $actor, $organisation->id, 'application', $application->uuid, null, ['status' => 'suspended'], $request);
        return $application;
    }

    public function activateApplication(Organisation $organisation, Application $application, ?User $actor = null, ?Request $request = null): Application
    {
        $this->assertOwnership($organisation, $application);
        if ($application->archived_at !== null) {
            throw new InvalidArgumentException('Archived applications cannot be reactivated.');
        }
        $application->update(['status' => 'active']);
        $this->auditService->record('application.activated', $actor, $organisation->id, 'application', $application->uuid, null, ['status' => 'active'], $request);
        return $application;
    }

    public function archiveApplication(Organisation $organisation, Application $application, ?User $actor = null, ?Request $request = null): void
    {
        $this->assertOwnership($organisation, $application);
        DB::transaction(function () use ($organisation, $application, $actor, $request): void {
            $application->update(['status' => 'suspended', 'archived_at' => now()]);
            $this->oauthClientService->revokeClient($application, $actor, $request);
            $this->auditService->record('application.archived', $actor, $organisation->id, 'application', $application->uuid, null, ['archived_at' => $application->archived_at?->toIso8601String()], $request);
        });
    }

    /**
     * Enforces hierarchy: an application can only enable methods permitted by BOTH global and organisation levels.
     *
     * @param array<int, string|array<string, mixed>> $methods
     */
    public function syncAuthMethods(
        Organisation $organisation,
        Application $application,
        array $methods,
        ?User $actor = null,
        ?Request $request = null
    ): void {
        $this->assertOwnership($organisation, $application);

        $globalEnabled = AuthProvider::query()->where('is_globally_enabled', true)->pluck('code')->all();
        $orgEnabled = OrganisationAuthMethod::query()
            ->where('organisation_id', $organisation->id)
            ->where('is_enabled', true)
            ->pluck('provider_code')
            ->all();

        ApplicationAuthMethod::query()->where('application_id', $application->id)->delete();

        foreach ($methods as $index => $entry) {
            $code = is_array($entry) ? (string) ($entry['provider_code'] ?? '') : (string) $entry;
            $mandatoryStages = is_array($entry) ? ($entry['mandatory_stages'] ?? null) : null;

            if (!in_array($code, $globalEnabled, true)) {
                throw new InvalidArgumentException("Provider [{$code}] is disabled globally and cannot be enabled on an application.");
            }
            if (!in_array($code, $orgEnabled, true)) {
                throw new InvalidArgumentException("Provider [{$code}] is disabled by the organisation and cannot be enabled on an application.");
            }

            ApplicationAuthMethod::create([
                'organisation_id' => $organisation->id,
                'application_id' => $application->id,
                'provider_code' => $code,
                'is_enabled' => true,
                'sort_order' => $index,
                'mandatory_stages' => $mandatoryStages,
            ]);
        }

        $this->auditService->record(
            action: 'application.auth_methods_updated',
            actor: $actor,
            organisationId: $organisation->id,
            targetType: 'application',
            targetUuid: $application->uuid,
            afterState: ['methods' => $methods],
            request: $request
        );
    }

    private function assertOwnership(Organisation $organisation, Application $application): void
    {
        if ($application->organisation_id !== $organisation->id) {
            throw new InvalidArgumentException('Cross-tenant application modification rejected.');
        }
    }

    private function normalizeSubdomain(string $raw): string
    {
        $label = Str::slug($raw);
        if ($label === '') {
            throw new InvalidArgumentException('Invalid TWSD subdomain label.');
        }
        return "{$label}.connect.twsd.me";
    }

    private function validateCustomDomainForOrg(Organisation $organisation, ?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }
        $normalized = strtolower(trim($domain));
        $verified = OrganisationDomain::query()
            ->where('organisation_id', $organisation->id)
            ->where('domain', $normalized)
            ->where('is_verified', true)
            ->exists();

        if (!$verified) {
            throw new InvalidArgumentException("Custom domain [{$normalized}] must be verified under this organisation before binding.");
        }

        return $normalized;
    }
}
