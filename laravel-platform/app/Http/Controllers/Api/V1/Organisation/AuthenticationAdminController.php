<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailConfigurationRequest;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\LoginAttempt;
use App\Models\OrganisationAuthMethod;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Providers\Email\Services\EmailConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationAdminController extends Controller
{
    public function __construct(
        private readonly EmailConfigurationService $emailConfigService,
        private readonly AuditService $auditService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');

        $globalProviders = AuthProvider::query()->get();
        $orgMethods = OrganisationAuthMethod::query()
            ->where('organisation_id', $organisation->id)
            ->get()
            ->keyBy('provider_code');

        $emailConfig = EmailConfiguration::query()
            ->where('scope', 'organisation')
            ->where('organisation_id', $organisation->id)
            ->first();

        return response()->json([
            'data' => [
                'providers' => $globalProviders->map(fn ($p) => [
                    'code' => $p->code,
                    'name' => $p->name,
                    'globally_enabled' => $p->is_globally_enabled,
                    'org_enabled' => (bool) ($orgMethods->get($p->code)?->is_enabled ?? false),
                    'require_as_mfa_second_stage' => (bool) ($orgMethods->get($p->code)?->require_as_mfa_second_stage ?? false),
                    'global_health_status' => $p->health_status,
                ]),
                'email_configuration' => $emailConfig?->only(['uuid', 'driver', 'sender_name', 'sender_email', 'smtp_settings', 'is_verified', 'last_tested_at']),
                'password_policy' => $organisation->password_policy,
                'otp_policy' => $organisation->otp_policy,
            ],
        ]);
    }

    public function updateMethods(Request $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        $validated = $request->validate([
            'methods' => ['required', 'array'],
            'methods.*.provider_code' => ['required', 'string', 'exists:auth_providers,code'],
            'methods.*.is_enabled' => ['required', 'boolean'],
            'methods.*.require_as_mfa_second_stage' => ['nullable', 'boolean'],
        ]);

        foreach ($validated['methods'] as $item) {
            $global = AuthProvider::query()->where('code', $item['provider_code'])->firstOrFail();
            if ($item['is_enabled'] && !$global->is_globally_enabled) {
                return response()->json([
                    'error' => 'global_provider_disabled',
                    'message' => "Cannot enable [{$item['provider_code']}] because it is disabled globally by Super Admin.",
                ], 422);
            }

            OrganisationAuthMethod::query()->updateOrCreate(
                ['organisation_id' => $organisation->id, 'provider_code' => $item['provider_code']],
                [
                    'is_enabled' => (bool) $item['is_enabled'],
                    'require_as_mfa_second_stage' => (bool) ($item['require_as_mfa_second_stage'] ?? false),
                ]
            );
        }

        $this->auditService->record(
            action: 'organisation.auth_methods_updated',
            actor: $request->user(),
            organisationId: $organisation->id,
            targetType: 'organisation',
            targetUuid: $organisation->uuid,
            afterState: $validated['methods'],
            request: $request
        );

        return response()->json(['message' => 'Organisation authentication methods updated.']);
    }

    public function updatePolicies(Request $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        $globalMinLength = (int) config('twsd_connect.password.min_length', 12);
        $globalMaxOtpExpiry = (int) config('twsd_connect.otp.expiry_seconds', 300);

        $validated = $request->validate([
            'password_policy.min_length' => ['nullable', 'integer', "min:{$globalMinLength}", 'max:128'],
            'otp_policy.expiry_seconds' => ['nullable', 'integer', 'min:60', "max:{$globalMaxOtpExpiry}"],
            'otp_policy.max_verification_attempts' => ['nullable', 'integer', 'min:1', 'max:5'],
            'otp_policy.resend_cooldown_seconds' => ['nullable', 'integer', 'min:60', 'max:300'],
        ]);

        $organisation->update([
            'password_policy' => $validated['password_policy'] ?? $organisation->password_policy,
            'otp_policy' => $validated['otp_policy'] ?? $organisation->otp_policy,
        ]);

        $this->auditService->record(
            action: 'organisation.security_policies_updated',
            actor: $request->user(),
            organisationId: $organisation->id,
            targetType: 'organisation',
            targetUuid: $organisation->uuid,
            afterState: $validated,
            request: $request
        );

        return response()->json(['data' => $organisation->only(['password_policy', 'otp_policy'])]);
    }

    public function updateEmailConfiguration(UpdateEmailConfigurationRequest $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        $config = $this->emailConfigService->upsertOrganisationConfiguration(
            organisation: $organisation,
            data: $request->validated(),
            actor: $request->user(),
            request: $request
        );

        return response()->json([
            'data' => $config->only(['uuid', 'driver', 'sender_name', 'sender_email', 'smtp_settings', 'is_verified']),
        ]);
    }

    public function testEmailConfiguration(Request $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        $validated = $request->validate(['recipient_email' => ['required', 'email']]);

        $config = EmailConfiguration::query()
            ->where('scope', 'organisation')
            ->where('organisation_id', $organisation->id)
            ->firstOrFail();

        $result = $this->emailConfigService->testConfiguration($config, $validated['recipient_email']);
        return response()->json(['data' => $result]);
    }

    public function loginLogs(Request $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');

        $logs = LoginAttempt::query()
            ->where('organisation_id', $organisation->id)
            ->orderByDesc('attempted_at')
            ->paginate(30);

        return response()->json(['data' => $logs]);
    }
}
