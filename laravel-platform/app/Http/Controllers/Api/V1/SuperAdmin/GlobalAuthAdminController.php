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
use App\Modules\Providers\WhatsApp\Services\WhatsAppOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GlobalAuthAdminController extends Controller
{
    public function __construct(
        private readonly EmailConfigurationService $emailConfigService,
        private readonly WhatsAppOtpService $whatsAppOtpService,
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
            'public_settings' => ['nullable', 'array'],
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

        $publicSettings = $validated['public_settings'] ?? [];
        if ($providerCode === 'whatsapp_otp') {
            if (empty($mergedSecrets['appkey']) || empty($mergedSecrets['authkey'])) {
                return response()->json([
                    'error' => 'missing_whatsapp_credentials',
                    'message' => 'Both appkey and authkey are required for WhatsApp OTP.',
                ], 422);
            }
            $publicSettings = array_merge([
                'endpoint' => config('twsd_connect.providers.whatsapp.endpoint', 'https://api.whatsapp.webglit.com/api/create-message'),
                'phone_format' => 'international_without_plus',
            ], $publicSettings);
        }

        $config = ProviderConfiguration::query()->updateOrCreate(
            ['scope' => 'global', 'organisation_id' => null, 'provider_code' => $providerCode],
            [
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'use_platform_default' => true,
                'public_settings' => $publicSettings,
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
        $config = EmailConfiguration::query()->where('scope', 'global')->whereNull('organisation_id')->first();

        if (!$config) {
            return response()->json([
                'error' => 'email_config_missing',
                'message' => 'Save the Platform Default Email Sender configuration first before sending a test email.',
            ], 422);
        }

        try {
            $result = $this->emailConfigService->testConfiguration($config, $validated['recipient_email']);

            return response()->json(['data' => $result]);
        } catch (Throwable $e) {
            Log::warning('Platform email delivery test failed', [
                'sender_email' => $config->sender_email,
                'driver' => $config->driver,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'email_delivery_failed',
                'message' => 'Email delivery failed: ' . trim($e->getMessage()),
            ], 422);
        }
    }

    public function testFirebaseSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firebase_id_token' => ['required', 'string'],
        ]);

        $config = ProviderConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->where('provider_code', 'firebase_sms')
            ->first();

        $publicSettings = $config?->public_settings ?? [];
        $apiKey = $publicSettings['api_key'] ?? ($publicSettings['firebase_config']['apiKey'] ?? null);

        if (!$config || empty($apiKey)) {
            return response()->json([
                'error' => 'firebase_config_missing',
                'message' => 'Configure and save your Firebase SDK Config first before testing SMS OTP.',
            ], 422);
        }

        try {
            $response = Http::timeout(10)->post(
                'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode((string) $apiKey),
                ['idToken' => $validated['firebase_id_token']]
            );

            if (!$response->successful()) {
                $errorMsg = $response->json('error.message') ?? ('HTTP ' . $response->status());
                $config->update([
                    'health_status' => 'degraded',
                    'last_error_message' => $errorMsg,
                ]);

                return response()->json([
                    'error' => 'firebase_verification_failed',
                    'message' => "Firebase verification rejected: {$errorMsg}",
                ], 422);
            }

            $fbUser = $response->json('users.0') ?? [];
            $phoneNumber = (string) ($fbUser['phoneNumber'] ?? '');

            if ($phoneNumber === '') {
                return response()->json([
                    'error' => 'phone_missing',
                    'message' => 'Firebase ID token does not contain a verified phone number.',
                ], 422);
            }

            $maskedPhone = strlen($phoneNumber) > 6
                ? substr($phoneNumber, 0, 4) . str_repeat('*', strlen($phoneNumber) - 6) . substr($phoneNumber, -2)
                : '***';

            $config->update([
                'health_status' => 'healthy',
                'last_verified_at' => now(),
                'last_error_message' => null,
            ]);

            AuthProvider::query()->where('code', 'firebase_sms')->update([
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
            ]);

            $this->auditService->record(
                action: 'super_admin.firebase_sms_tested',
                actor: $request->user(),
                targetType: 'provider_configuration',
                targetUuid: $config->uuid,
                afterState: ['phone_masked' => $maskedPhone, 'project_id' => $publicSettings['project_id'] ?? null],
                request: $request,
                actorRole: 'super_admin'
            );

            return response()->json([
                'data' => [
                    'tested' => true,
                    'phone_masked' => $maskedPhone,
                    'verified_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Firebase SMS verification test failed', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'firebase_test_failed',
                'message' => 'Firebase SMS verification failed: ' . trim($e->getMessage()),
            ], 422);
        }
    }

    public function testWhatsAppOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $config = ProviderConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->where('provider_code', 'whatsapp_otp')
            ->first();

        try {
            $body = trim((string) ($validated['message'] ?? ''));
            if ($body === '') {
                $body = 'TWSD Connect — Test WhatsApp OTP verification message. Your Webglit API integration is operational.';
            }

            $result = $this->whatsAppOtpService->sendMessage($validated['phone_number'], $body);

            if ($config) {
                $config->update([
                    'health_status' => 'healthy',
                    'last_verified_at' => now(),
                    'last_error_message' => null,
                ]);
            }

            AuthProvider::query()->where('code', 'whatsapp_otp')->update([
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
            ]);

            $this->auditService->record(
                action: 'super_admin.whatsapp_otp_tested',
                actor: $request->user(),
                targetType: 'provider_configuration',
                targetUuid: $config?->uuid,
                afterState: ['to_masked' => $result['to_masked']],
                request: $request,
                actorRole: 'super_admin'
            );

            return response()->json(['data' => ['tested' => true] + $result]);
        } catch (Throwable $e) {
            if ($config) {
                $config->update([
                    'health_status' => 'degraded',
                    'last_error_message' => mb_substr($e->getMessage(), 0, 500),
                ]);
            }

            Log::warning('WhatsApp OTP test delivery failed', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'whatsapp_delivery_failed',
                'message' => 'WhatsApp delivery failed: ' . trim($e->getMessage()),
            ], 422);
        }
    }

    public function securityEvents(): JsonResponse
    {
        return response()->json([
            'data' => SecurityEvent::query()->orderByDesc('occurred_at')->paginate(50),
        ]);
    }
}
