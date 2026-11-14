<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEmailConfigurationRequest;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\LoginAttempt;
use App\Models\OtpDeliveryLog;
use App\Models\SecurityEvent;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Providers\Email\Services\EmailConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
