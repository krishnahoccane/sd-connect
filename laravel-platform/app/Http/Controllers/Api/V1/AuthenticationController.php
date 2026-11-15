<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OAuthTransaction;
use App\Models\SsoSession;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Authentication\Services\AuthenticationPolicyService;
use App\Modules\OIDC\Services\OAuthAuthorizationBridgeService;
use App\Modules\Providers\Email\Services\EmailOtpProvider;
use App\Modules\Providers\Email\Services\EmailPasswordProvider;
use App\Modules\Providers\WhatsApp\Services\WhatsAppOtpProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthenticationController extends Controller
{
    public function __construct(
        private readonly AuthenticationPolicyService $policyService,
        private readonly EmailPasswordProvider $emailPasswordProvider,
        private readonly EmailOtpProvider $emailOtpProvider,
        private readonly WhatsAppOtpProvider $whatsAppOtpProvider,
        private readonly OAuthAuthorizationBridgeService $oauthBridge,
        private readonly AuditService $auditService
    ) {
    }

    public function methods(Request $request): JsonResponse
    {
        $organisation = $request->attributes->get('resolved_organisation');
        $application = $request->attributes->get('resolved_application');

        return response()->json([
            'data' => [
                'organisation' => ['uuid' => $organisation->uuid, 'name' => $organisation->name, 'slug' => $organisation->slug],
                'application' => ['uuid' => $application->uuid, 'name' => $application->name, 'pkce_required' => $application->require_pkce],
                'effective_policy' => $this->policyService->resolveEffectiveMethods($organisation, $application),
            ],
        ]);
    }

    public function emailPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'oauth_transaction_id' => ['nullable', 'uuid'],
        ]);

        $organisation = $request->attributes->get('resolved_organisation');
        $application = $request->attributes->get('resolved_application');

        $policy = $this->policyService->resolveEffectiveMethods($organisation, $application);
        $allowed = collect($policy['methods'])->firstWhere('provider_code', 'email_password');
        if (!$allowed) {
            return response()->json(['error' => 'auth_method_not_permitted'], 403);
        }

        $result = $this->emailPasswordProvider->authenticate([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'request' => $request,
        ], $organisation, $application);

        if (!$result['authenticated']) {
            return response()->json(['error' => 'authentication_failed', 'message' => $result['error']], 401);
        }

        $user = User::query()->findOrFail($result['claims']['user_id']);
        $stages = $allowed['mandatory_stages'] ?? ['email_password'];

        if (count($stages) > 1 && $stages[0] === 'email_password') {
            return response()->json([
                'data' => [
                    'status' => 'mfa_required',
                    'next_stage' => $stages[1],
                    'user_uuid' => $user->uuid,
                ],
            ]);
        }

        return $this->completeAuthentication($user, $request, ['pwd'], $validated['oauth_transaction_id'] ?? null);
    }

    public function sendEmailOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'purpose' => ['nullable', 'in:signin,identity_link,password_reset'],
        ]);

        $organisation = $request->attributes->get('resolved_organisation');
        $application = $request->attributes->get('resolved_application');

        $policy = $this->policyService->resolveEffectiveMethods($organisation, $application);
        $allowed = collect($policy['methods'])->firstWhere('provider_code', 'email_otp');
        if (!$allowed) {
            return response()->json(['error' => 'auth_method_not_permitted'], 403);
        }

        try {
            $issued = $this->emailOtpProvider->sendOtp(
                organisation: $organisation,
                application: $application,
                email: $validated['email'],
                purpose: $validated['purpose'] ?? 'signin',
                request: $request
            );

            return response()->json(['data' => $issued]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'otp_request_rejected', 'message' => $e->getMessage()], 422);
        }
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_uuid' => ['required', 'uuid'],
            'otp' => ['required', 'string', 'min:4', 'max:8'],
            'email' => ['required', 'email'],
            'purpose' => ['nullable', 'in:signin,identity_link,password_reset'],
            'oauth_transaction_id' => ['nullable', 'uuid'],
        ]);

        $organisation = $request->attributes->get('resolved_organisation');
        $application = $request->attributes->get('resolved_application');

        $result = $this->emailOtpProvider->authenticate([
            'transaction_uuid' => $validated['transaction_uuid'],
            'otp' => $validated['otp'],
            'email' => $validated['email'],
            'purpose' => $validated['purpose'] ?? 'signin',
            'request' => $request,
        ], $organisation, $application);

        if (!$result['authenticated']) {
            return response()->json(['error' => 'otp_verification_failed', 'message' => $result['error']], 422);
        }

        $user = User::query()->findOrFail($result['claims']['user_id']);
        return $this->completeAuthentication($user, $request, ['otp'], $validated['oauth_transaction_id'] ?? null, 'email_otp');
    }

    public function sendWhatsAppOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'purpose' => ['nullable', 'in:signin,identity_link,password_reset'],
        ]);

        $organisation = $request->attributes->get('resolved_organisation');
        $application = $request->attributes->get('resolved_application');

        $policy = $this->policyService->resolveEffectiveMethods($organisation, $application);
        $allowed = collect($policy['methods'])->firstWhere('provider_code', 'whatsapp_otp');
        if (!$allowed) {
            return response()->json(['error' => 'auth_method_not_permitted'], 403);
        }

        try {
            $issued = $this->whatsAppOtpProvider->sendOtp(
                organisation: $organisation,
                application: $application,
                phone: $validated['phone'],
                purpose: $validated['purpose'] ?? 'signin',
                request: $request
            );

            return response()->json(['data' => $issued]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'otp_request_rejected', 'message' => $e->getMessage()], 422);
        }
    }

    public function verifyWhatsAppOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_uuid' => ['required', 'uuid'],
            'otp' => ['required', 'string', 'min:4', 'max:8'],
            'phone' => ['required', 'string', 'max:32'],
            'purpose' => ['nullable', 'in:signin,identity_link,password_reset'],
            'oauth_transaction_id' => ['nullable', 'uuid'],
        ]);

        $organisation = $request->attributes->get('resolved_organisation');
        $application = $request->attributes->get('resolved_application');

        $result = $this->whatsAppOtpProvider->authenticate([
            'transaction_uuid' => $validated['transaction_uuid'],
            'otp' => $validated['otp'],
            'phone' => $validated['phone'],
            'purpose' => $validated['purpose'] ?? 'signin',
            'request' => $request,
        ], $organisation, $application);

        if (!$result['authenticated']) {
            return response()->json(['error' => 'otp_verification_failed', 'message' => $result['error']], 422);
        }

        $user = User::query()->findOrFail($result['claims']['user_id']);
        return $this->completeAuthentication($user, $request, ['otp', 'whatsapp'], $validated['oauth_transaction_id'] ?? null, 'whatsapp_otp');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['primary_email' => strtolower(trim($validated['email']))]);

        return response()->json([
            'message' => 'If an active account exists for that email address, a password reset link has been queued.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $organisation = $request->attributes->get('resolved_organisation');

        try {
            $this->emailPasswordProvider->assertPasswordMeetsPolicy($validated['password'], $organisation);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'password_policy_violation', 'message' => $e->getMessage()], 422);
        }

        $status = Password::reset(
            [
                'primary_email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'password_confirmation' => $validated['password'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'failed_login_count' => 0,
                    'locked_until' => null,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['error' => 'password_reset_failed', 'message' => __($status)], 422);
        }

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->cookie('twsd_sso_session');
        if (is_string($token) && $token !== '') {
            $sso = SsoSession::query()->where('session_token_hash', hash('sha256', $token))->first();
            if ($sso) {
                $sso->update(['revoked_at' => now()]);
                \App\Models\UserSession::query()
                    ->where('sso_session_id', $sso->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }
        }

        return response()->json(['logged_out' => true])->withoutCookie('twsd_sso_session');
    }

    private function completeAuthentication(User $user, Request $request, array $amr, ?string $oauthTxUuid, string $providerCode = 'email_password'): JsonResponse
    {
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $rawSessionToken = bin2hex(random_bytes(32));
        $ssoSession = SsoSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $rawSessionToken),
            'amr' => $amr,
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(480),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
        ]);

        $redirectTo = null;
        if ($oauthTxUuid) {
            $tx = OAuthTransaction::query()->where('uuid', $oauthTxUuid)->first();
            if ($tx) {
                \App\Models\UserSession::create([
                    'sso_session_id' => $ssoSession->id,
                    'user_id' => $user->id,
                    'organisation_id' => $tx->organisation_id,
                    'application_id' => $tx->application_id,
                    'auth_provider' => $providerCode,
                    'started_at' => now(),
                ]);

                $code = $this->oauthBridge->issueAuthorizationCode($tx, $user, $amr);
                $separator = str_contains($tx->redirect_uri, '?') ? '&' : '?';
                $redirectTo = "{$tx->redirect_uri}{$separator}code=" . urlencode($code);
                if ($tx->state) {
                    $redirectTo .= '&state=' . urlencode($tx->state);
                }
            }
        }

        $this->auditService->record('auth.login_succeeded', $user, null, 'user', $user->uuid, null, ['amr' => $amr], $request);

        return response()->json([
            'data' => [
                'authenticated' => true,
                'user_uuid' => $user->uuid,
                'redirect_uri' => $redirectTo,
            ],
        ])->cookie('twsd_sso_session', $rawSessionToken, 480, '/', null, true, true, false, 'lax');
    }
}
