<?php

namespace App\Modules\Authentication\Services;

use App\Models\Application;
use App\Models\ApplicationAuthMethod;
use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\ProviderConfiguration;

class AuthenticationPolicyService
{
    /**
     * Resolves permitted authentication methods using strict hierarchical intersection:
     * FINAL = GLOBAL_ENABLED ∩ ORGANISATION_ENABLED ∩ APPLICATION_ENABLED ∩ PROVIDER_HEALTHY
     *
     * @return array{
     *   methods: array<int, array<string, mixed>>,
     *   rejected: array<string, string>
     * }
     */
    public function resolveEffectiveMethods(Organisation $organisation, Application $application): array
    {
        if (!$organisation->isActive() || !$application->isActive()) {
            return ['methods' => [], 'rejected' => ['tenant' => 'Organisation or application suspended']];
        }

        if ($application->organisation_id !== $organisation->id) {
            throw new \RuntimeException('Cross-tenant application resolution rejected.');
        }

        $globalProviders = AuthProvider::query()->get()->keyBy('code');
        $orgMethods = OrganisationAuthMethod::query()
            ->where('organisation_id', $organisation->id)
            ->get()
            ->keyBy('provider_code');
        $appMethods = ApplicationAuthMethod::query()
            ->where('organisation_id', $organisation->id)
            ->where('application_id', $application->id)
            ->orderBy('sort_order')
            ->get();

        $effective = [];
        $rejected = [];

        foreach ($appMethods as $appMethod) {
            $code = $appMethod->provider_code;
            $global = $globalProviders->get($code);

            if (!$global || !$global->is_globally_enabled) {
                $rejected[$code] = 'disabled_globally';
                continue;
            }

            $orgMethod = $orgMethods->get($code);
            if (!$orgMethod || !$orgMethod->is_enabled) {
                $rejected[$code] = 'disabled_by_organisation';
                continue;
            }

            if (!$appMethod->is_enabled) {
                $rejected[$code] = 'disabled_by_application';
                continue;
            }

            if (!$this->isProviderHealthyForOrganisation($global, $organisation)) {
                $rejected[$code] = 'provider_configuration_unhealthy';
                continue;
            }

            $mandatoryStages = $appMethod->mandatory_stages ?? [];
            if ($organisation->mfa_required && empty($mandatoryStages)) {
                $mandatoryStages = $this->resolveMfaStages($code, $orgMethods, $globalProviders, $organisation);
            }

            $effective[] = [
                'provider_code' => $code,
                'name' => $global->name,
                'category' => $global->category,
                'mandatory_stages' => $mandatoryStages,
                'sort_order' => $appMethod->sort_order,
            ];
        }

        return [
            'methods' => $effective,
            'rejected' => $rejected,
        ];
    }

    public function isProviderHealthyForOrganisation(AuthProvider $provider, Organisation $organisation): bool
    {
        if (!$provider->requires_external_health) {
            return true;
        }

        $orgConfig = ProviderConfiguration::query()
            ->where('scope', 'organisation')
            ->where('organisation_id', $organisation->id)
            ->where('provider_code', $provider->code)
            ->where('is_active', true)
            ->first();

        if ($orgConfig && !$orgConfig->use_platform_default) {
            return $orgConfig->health_status === 'healthy';
        }

        $globalConfig = ProviderConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->where('provider_code', $provider->code)
            ->where('is_active', true)
            ->first();

        return $globalConfig?->health_status === 'healthy' || $provider->health_status === 'healthy';
    }

    private function resolveMfaStages(string $primaryCode, $orgMethods, $globalProviders, Organisation $organisation): array
    {
        $secondFactor = $orgMethods
            ->where('require_as_mfa_second_stage', true)
            ->sortBy('priority')
            ->first(function ($m) use ($primaryCode, $globalProviders, $organisation) {
                $p = $globalProviders->get($m->provider_code);
                return $m->provider_code !== $primaryCode
                    && $m->is_enabled
                    && $p
                    && $p->is_globally_enabled
                    && $this->isProviderHealthyForOrganisation($p, $organisation);
            });

        return $secondFactor ? [$primaryCode, $secondFactor->provider_code] : [$primaryCode];
    }
}
