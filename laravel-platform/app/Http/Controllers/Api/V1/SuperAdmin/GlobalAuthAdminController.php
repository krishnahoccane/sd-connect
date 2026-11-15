<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailConfigurationRequest;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\LoginAttempt;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OtpDeliveryLog;
use App\Models\ProviderConfiguration;
use App\Models\SecurityEvent;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Providers\Email\Services\EmailConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalAuthAdminController extends Controller
{
    public function __construct(
        private readonly EmailConfigurationService $emailConfigService,
        private readonly AuditService $auditService
    ) {
    }

    public function dashboard(): JsonResponse
    {
        $today = now()->startOfDay();

        return response()->json([
            'data' => [
                'providers' => AuthProvider::query()->orderBy('id')->get(),
                'stats' => [
                    'successful_logins_today' => LoginAttempt::query()->where('attempted_at', '>=', $today)->where('outcome', 'success')->count(),
                    'failed_logins_today' => LoginAttempt::query()->where('attempted_at', '>=', $today)->whereIn('outcome', ['failed', 'blocked'])->count(),
                    'otp_sent_today' => OtpDeliveryLog::query()->where('created_at', '>=', $today)->where('delivery_status', 'sent')->count(),
                    'otp_failed_today' => OtpDeliveryLog::query()->where('created_at', '>=', $today)->where('delivery_status', 'failed')->count(),
                ],
                'recent_security_events' => SecurityEvent::query()->orderByDesc('occurred_at')->limit(25)->get(),
            ],
        ]);
    }

    public function toggleGlobalProvider(Request $request, string $providerCode): JsonResponse
    {
        $validated = $request->validate([
            'is_globally_enabled' => ['required', 'boolean'],
            'default_policy' => ['nullable', 'array'],
        ]);

        $provider = AuthProvider::query()->where('code', $providerCode)->firstOrFail();
        $before = ['is_globally_enabled' => $provider->is_globally_enabled];

        $provider->update([
            'is_globally_enabled' => (bool) $validated['is_globally_enabled'],
            'default_policy' => $validated['default_policy'] ?? $provider->default_policy,
        ]);

        $this->auditService->record(
            action: 'super_admin.provider_toggled',
            actor: $request->user(),
            targetType: 'auth_provider',
            targetUuid: $provider->uuid,
            beforeState: $before,
            afterState: ['is_globally_enabled' => $provider->is_globally_enabled],
            request: $request,
            actorRole: 'super_admin'
        );

        return response()->json(['data' => $provider]);
    }

    public function upsertProviderConfiguration(Request $request, string $providerCode): JsonResponse
    {
        if (!in_array($providerCode, ['firebase_sms', 'whatsapp_otp', 'google', 'microsoft'], true)) {
            return response()->json(['error' => 'unsupported_provider', 'message' => 'Use the email sender form for email_otp configuration.'], 422);
        }

        $provider = AuthProvider::query()->where('code', $providerCode)->firstOrFail();

        $validated = $request->validate([
            'public_settings' => ['required', 'array'],
            'credentials' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $existing = ProviderConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->where('provider_code', $providerCode)
            ->first();

        $mergedSecrets = $existing?->encrypted_credentials ?? [];
        foreach (($validated['credentials'] ?? []) as $k => $v) {
            if ($v !== null && $v !== '') {
                $mergedSecrets[$k] = $v;
            }
        }

        $config = ProviderConfiguration::query()->updateOrCreate(
            ['scope' => 'global', 'organisation_id' => null, 'provider_code' => $providerCode],
            [
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'use_platform_default' => true,
                'public_settings' => $validated['public_settings'],
                'encrypted_credentials' => $mergedSecrets ?: null,
                'health_status' => 'healthy',
                'last_verified_at' => now(),
                'last_error_message' => null,
            ]
        );

        $provider->update([
            'health_status' => 'healthy',
            'last_health_check_at' => now(),
        ]);

        $this->auditService->record(
            action: 'super_admin.provider_configured',
            actor: $request->user(),
            targetType: 'provider_configuration',
            targetUuid: $config->uuid,
            afterState: ['provider_code' => $providerCode, 'public_settings' => $config->public_settings],
            request: $request,
            actorRole: 'super_admin'
        );

        return response()->json([
            'data' => $config->only(['uuid', 'provider_code', 'is_active', 'public_settings', 'health_status']),
            'message' => "{$provider->name} platform configuration saved.",
        ]);
    }

    public function createOrganisation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:100'],
        ]);

        $slug = Str::slug($validated['slug'] ?: $validated['name']);
        if (Organisation::query()->where('slug', $slug)->exists()) {
            return response()->json(['message' => "Organisation slug [{$slug}] is already in use."], 422);
        }

        $org = Organisation::create([
            'name' => trim($validated['name']),
            'slug' => $slug,
            'status' => 'active',
            'jit_provisioning_mode' => 'invitation_only',
            'mfa_required' => false,
            'session_lifetime_minutes' => 480,
        ]);

        OrganisationAuthMethod::create([
            'organisation_id' => $org->id,
            'provider_code' => 'email_password',
            'is_enabled' => true,
            'priority' => 1,
        ]);

        $this->auditService->record(
            action: 'super_admin.organisation_created',
            actor: $request->user(),
            organisationId: $org->id,
            targetType: 'organisation',
            targetUuid: $org->uuid,
            afterState: ['name' => $org->name, 'slug' => $org->slug],
            request: $request,
            actorRole: 'super_admin'
        );

        return response()->json(['data' => $org], 201);
    }

    public function updatePlatformEmail(UpdateEmailConfigurationRequest $request): JsonResponse
    {
        $config = $this->emailConfigService->upsertPlatformDefault(
            data: $request->validated(),
            actor: $request->user(),
            request: $request
        );

        return response()->json([
            'data' => $config->only(['uuid', 'driver', 'sender_name', 'sender_email', 'is_verified']),
        ]);
    }

    public function testPlatformEmail(Request $request): JsonResponse
    {
        $validated = $request->validate(['recipient_email' => ['required', 'email']]);
        $config = EmailConfiguration::query()->where('scope', 'global')->whereNull('organisation_id')->firstOrFail();
        $result = $this->emailConfigService->testConfiguration($config, $validated['recipient_email']);

        return response()->json(['data' => $result]);
    }

    public function securityEvents(): JsonResponse
    {
        return response()->json([
            'data' => SecurityEvent::query()->orderByDesc('occurred_at')->paginate(50),
        ]);
    }
}
