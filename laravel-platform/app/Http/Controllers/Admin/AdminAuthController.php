<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\Organisation;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OTP\Services\OtpGenerator;
use App\Modules\OTP\Services\OtpHasher;
use App\Modules\Providers\Email\Services\EmailConfigurationService;
use App\Modules\Providers\WhatsApp\Services\WhatsAppOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AdminAuthController extends Controller
{
    private const LIVE_PROVIDER_CODES = ['email_password', 'email_otp', 'whatsapp_otp'];

    public function __construct(
        private readonly AuditService $auditService,
        private readonly EmailConfigurationService $emailConfigService,
        private readonly WhatsAppOtpService $whatsAppOtpService,
        private readonly OtpGenerator $otpGenerator,
        private readonly OtpHasher $otpHasher
    ) {
    }

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if ($user) {
                return redirect($this->resolvePostLoginRedirect($user));
            }

            return redirect('/');
        }

        $enabledLiveProviders = AuthProvider::query()
            ->where('is_globally_enabled', true)
            ->whereIn('code', self::LIVE_PROVIDER_CODES)
            ->orderBy('id')
            ->get();

        $enabledCodes = $enabledLiveProviders->pluck('code')->all();
        $emailPasswordEnabled = in_array('email_password', $enabledCodes, true);
        $emailOtpEnabled = in_array('email_otp', $enabledCodes, true);
        $whatsappOtpEnabled = in_array('whatsapp_otp', $enabledCodes, true);

        $platformEmail = EmailConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->first();
        $emailOtpSenderReady = (bool) ($platformEmail && $platformEmail->is_verified);
        $whatsappOtpReady = $this->whatsAppOtpService->hasCredentials();

        $anyOtpReady = ($emailOtpEnabled && $emailOtpSenderReady) || ($whatsappOtpEnabled && $whatsappOtpReady);
        $emergencyFallback = !$emailPasswordEnabled && !$anyOtpReady;

        return view('admin.login', [
            'enabledLiveProviders' => $enabledLiveProviders,
            'emailPasswordEnabled' => $emailPasswordEnabled,
            'emailOtpEnabled' => $emailOtpEnabled,
            'emailOtpSenderReady' => $emailOtpSenderReady,
            'whatsappOtpEnabled' => $whatsappOtpEnabled,
            'whatsappOtpReady' => $whatsappOtpReady,
            'emergencyFallback' => $emergencyFallback,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $passwordGlobalEnabled = AuthProvider::query()
            ->where('code', 'email_password')
            ->where('is_globally_enabled', true)
            ->exists();

        $emailOtpReady = AuthProvider::query()
            ->where('code', 'email_otp')
            ->where('is_globally_enabled', true)
            ->exists()
            && EmailConfiguration::query()
                ->where('scope', 'global')
                ->whereNull('organisation_id')
                ->where('is_verified', true)
                ->exists();

        if (!$passwordGlobalEnabled && $emailOtpReady) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Password sign-in is disabled globally. Sign in using Email One-Time Password.']);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $user = User::query()->where('primary_email', $email)->first();

        if (!$user || empty($user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        if (!$user->isGloballyActive()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account is suspended or locked.']);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            $maxAttempts = (int) config('twsd_connect.password.max_login_attempts', 5);
            $lockoutMinutes = (int) config('twsd_connect.password.lockout_minutes', 15);
            $failedCount = $user->failed_login_count + 1;
            $updates = ['failed_login_count' => $failedCount];
            if ($failedCount >= $maxAttempts) {
                $updates['locked_until'] = now()->addMinutes($lockoutMinutes);
            }
            $user->update($updates);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $this->completeAdminSessionLogin($user, $request, 'email_password');

        return redirect()->intended($this->resolvePostLoginRedirect($user));
    }

    public function sendEmailOtp(Request $request): JsonResponse
    {
        $otpGloballyEnabled = AuthProvider::query()
            ->where('code', 'email_otp')
            ->where('is_globally_enabled', true)
            ->exists();

        if (!$otpGloballyEnabled) {
            return response()->json([
                'error' => 'provider_disabled',
                'message' => 'Email One-Time Password is disabled globally by Super Admin.',
            ], 403);
        }

        $platformEmail = EmailConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->first();

        if (!$platformEmail || !$platformEmail->is_verified) {
            return response()->json([
                'error' => 'email_sender_unverified',
                'message' => 'Platform Default Email Sender must be saved and verified before sending Email OTP codes.',
            ], 422);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->where('primary_email', $email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'account_not_found',
                'message' => 'No active admin account found for that email address.',
            ], 422);
        }

        if (!$user->isGloballyActive()) {
            return response()->json([
                'error' => 'account_inactive',
                'message' => 'This account is suspended or locked.',
            ], 422);
        }

        $hasConsoleAccess = $user->is_super_admin
            || $user->memberships()->where('membership_status', 'active')->exists();

        if (!$hasConsoleAccess) {
            return response()->json([
                'error' => 'console_access_denied',
                'message' => 'This account does not have active admin console access.',
            ], 403);
        }

        $recipientHash = $this->otpHasher->hashRecipient($email);
        $cooldownKey = "admin_otp_cooldown:{$recipientHash}";
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'error' => 'otp_cooldown',
                'message' => 'Please wait 60 seconds before requesting another verification code.',
            ], 429);
        }

        $digits = (int) config('twsd_connect.otp.digits', 6);
        $expirySeconds = (int) config('twsd_connect.otp.expiry_seconds', 300);
        $cooldownSeconds = (int) config('twsd_connect.otp.resend_cooldown_seconds', 60);
        $maxAttempts = (int) config('twsd_connect.otp.max_verification_attempts', 5);

        $challengeUuid = (string) Str::uuid();
        $plainOtp = $this->otpGenerator->generateNumeric($digits);
        $expiryMinutes = max(1, (int) ceil($expirySeconds / 60));

        $replacements = [
            '{{otp}}' => $plainOtp,
            '{{application_name}}' => 'TWSD Connect Admin Console',
            '{{organisation_name}}' => 'TWSD Connect Platform',
            '{{expiry_minutes}}' => (string) $expiryMinutes,
        ];

        $subject = strtr(
            $platformEmail->otp_subject_template ?: '{{otp}} is your sign-in code for {{application_name}}',
            $replacements
        );
        $body = strtr(
            $platformEmail->otp_body_template ?: 'Your one-time verification code for {{application_name}} is {{otp}}. It expires in {{expiry_minutes}} minutes.',
            $replacements
        );

        try {
            $this->emailConfigService->dispatchRawEmail($platformEmail, $user->primary_email, $subject, $body);
        } catch (Throwable $e) {
            Log::warning('Admin login OTP delivery failed', [
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'otp_delivery_failed',
                'message' => 'Email delivery failed: ' . trim($e->getMessage()),
            ], 422);
        }

        Cache::put("admin_otp_challenge:{$challengeUuid}", [
            'user_id' => $user->id,
            'email' => $email,
            'otp_hash' => $this->otpHasher->hash($plainOtp, $challengeUuid),
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'session_id' => $request->session()->getId(),
            'expires_at' => now()->addSeconds($expirySeconds)->timestamp,
        ], now()->addSeconds($expirySeconds));

        Cache::put($cooldownKey, true, now()->addSeconds($cooldownSeconds));

        $this->auditService->record(
            action: 'admin.otp_sent',
            actor: $user,
            targetType: 'user',
            targetUuid: $user->uuid,
            afterState: ['channel' => 'email_otp'],
            request: $request,
            actorRole: $user->is_super_admin ? 'super_admin' : 'user'
        );

        return response()->json([
            'data' => [
                'challenge_uuid' => $challengeUuid,
                'recipient_masked' => $this->otpHasher->maskEmail($email),
                'expires_in_seconds' => $expirySeconds,
            ],
        ]);
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $otpGloballyEnabled = AuthProvider::query()
            ->where('code', 'email_otp')
            ->where('is_globally_enabled', true)
            ->exists();

        if (!$otpGloballyEnabled) {
            return response()->json([
                'error' => 'provider_disabled',
                'message' => 'Email One-Time Password is disabled globally.',
            ], 403);
        }

        $validated = $request->validate([
            'challenge_uuid' => ['required', 'uuid'],
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string', 'min:4', 'max:8'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $challengeUuid = $validated['challenge_uuid'];
        $email = strtolower(trim($validated['email']));
        $cacheKey = "admin_otp_challenge:{$challengeUuid}";
        $challenge = Cache::get($cacheKey);

        if (!is_array($challenge)) {
            return response()->json([
                'error' => 'otp_expired',
                'message' => 'OTP challenge has expired or was already used. Request a new code.',
            ], 422);
        }

        if (
            !hash_equals((string) ($challenge['email'] ?? ''), $email) ||
            !hash_equals((string) ($challenge['session_id'] ?? ''), $request->session()->getId())
        ) {
            return response()->json([
                'error' => 'otp_context_mismatch',
                'message' => 'OTP session mismatch. Please request a fresh verification code.',
            ], 422);
        }

        if ((int) ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($cacheKey);
            return response()->json([
                'error' => 'otp_expired',
                'message' => 'OTP code has expired. Please request a new code.',
            ], 422);
        }

        $attempts = (int) ($challenge['attempts'] ?? 0);
        $maxAttempts = (int) ($challenge['max_attempts'] ?? 5);
        if ($attempts >= $maxAttempts) {
            Cache::forget($cacheKey);
            return response()->json([
                'error' => 'otp_exhausted',
                'message' => 'Maximum verification attempts exceeded. Please request a new code.',
            ], 422);
        }

        if (!$this->otpHasher->verify(trim($validated['otp']), $challengeUuid, (string) ($challenge['otp_hash'] ?? ''))) {
            $attempts++;
            if ($attempts >= $maxAttempts) {
                Cache::forget($cacheKey);
                return response()->json([
                    'error' => 'otp_exhausted',
                    'message' => 'Invalid code and maximum attempts exceeded. Please request a new code.',
                ], 422);
            }

            $challenge['attempts'] = $attempts;
            $remainingSeconds = max(1, (int) $challenge['expires_at'] - now()->timestamp);
            Cache::put($cacheKey, $challenge, now()->addSeconds($remainingSeconds));

            return response()->json([
                'error' => 'otp_invalid',
                'message' => 'Invalid verification code.',
            ], 422);
        }

        Cache::forget($cacheKey);

        $user = User::query()->find((int) ($challenge['user_id'] ?? 0));
        if (!$user || !$user->isGloballyActive()) {
            return response()->json([
                'error' => 'account_inactive',
                'message' => 'This account is suspended or locked.',
            ], 403);
        }

        if ($user->email_verified_at === null) {
            $user->update(['email_verified_at' => now()]);
        }

        $this->completeAdminSessionLogin($user, $request, 'email_otp');

        return response()->json([
            'data' => [
                'authenticated' => true,
                'redirect_to' => session()->pull('url.intended', $this->resolvePostLoginRedirect($user)),
            ],
        ]);
    }

    public function sendWhatsAppOtp(Request $request): JsonResponse
    {
        $waEnabled = AuthProvider::query()
            ->where('code', 'whatsapp_otp')
            ->where('is_globally_enabled', true)
            ->exists();

        if (!$waEnabled) {
            return response()->json([
                'error' => 'provider_disabled',
                'message' => 'WhatsApp OTP is disabled globally by Super Admin.',
            ], 403);
        }

        if (!$this->whatsAppOtpService->hasCredentials()) {
            return response()->json([
                'error' => 'whatsapp_unconfigured',
                'message' => 'WhatsApp OTP appkey and authkey must be configured in Super Admin settings first.',
            ], 422);
        }

        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $rawIdentifier = trim($validated['identifier']);
        if (str_contains($rawIdentifier, '@')) {
            $user = User::query()->where('primary_email', strtolower($rawIdentifier))->first();
        } else {
            try {
                $digits = $this->whatsAppOtpService->normalizePhoneDigits($rawIdentifier);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => 'invalid_phone', 'message' => $e->getMessage()], 422);
            }
            $user = User::query()->whereIn('primary_phone', ["+{$digits}", $digits])->first();
        }

        if (!$user) {
            return response()->json([
                'error' => 'account_not_found',
                'message' => 'No active admin account found for that phone number or email.',
            ], 422);
        }

        if (!$user->isGloballyActive()) {
            return response()->json([
                'error' => 'account_inactive',
                'message' => 'This account is suspended or locked.',
            ], 422);
        }

        $hasConsoleAccess = $user->is_super_admin
            || $user->memberships()->where('membership_status', 'active')->exists();

        if (!$hasConsoleAccess) {
            return response()->json([
                'error' => 'console_access_denied',
                'message' => 'This account does not have active admin console access.',
            ], 403);
        }

        if (empty($user->primary_phone)) {
            return response()->json([
                'error' => 'phone_not_set',
                'message' => 'No mobile phone number is saved on your account. Add it under My Profile first.',
            ], 422);
        }

        $phoneDigits = $this->whatsAppOtpService->normalizePhoneDigits((string) $user->primary_phone);
        $cooldownKey = 'admin_wa_otp_cooldown:' . $this->otpHasher->hashRecipient($phoneDigits);
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'error' => 'otp_cooldown',
                'message' => 'Please wait 60 seconds before requesting another WhatsApp code.',
            ], 429);
        }

        $digitsCount = (int) config('twsd_connect.otp.digits', 6);
        $expirySeconds = (int) config('twsd_connect.otp.expiry_seconds', 300);
        $cooldownSeconds = (int) config('twsd_connect.otp.resend_cooldown_seconds', 60);
        $maxAttempts = (int) config('twsd_connect.otp.max_verification_attempts', 5);

        $challengeUuid = (string) Str::uuid();
        $plainOtp = $this->otpGenerator->generateNumeric($digitsCount);
        $expiryMinutes = max(1, (int) ceil($expirySeconds / 60));

        try {
            $message = "{$plainOtp} is your TWSD Connect Admin Console sign-in code. Valid for {$expiryMinutes} minutes.";
            $this->whatsAppOtpService->sendMessage($phoneDigits, $message);
        } catch (Throwable $e) {
            Log::warning('Admin login WhatsApp OTP delivery failed', [
                'user_uuid' => $user->uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'whatsapp_delivery_failed',
                'message' => 'WhatsApp delivery failed: ' . trim($e->getMessage()),
            ], 422);
        }

        Cache::put("admin_wa_otp_challenge:{$challengeUuid}", [
            'user_id' => $user->id,
            'phone_digits' => $phoneDigits,
            'otp_hash' => $this->otpHasher->hash($plainOtp, $challengeUuid),
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'session_id' => $request->session()->getId(),
            'expires_at' => now()->addSeconds($expirySeconds)->timestamp,
        ], now()->addSeconds($expirySeconds));

        Cache::put($cooldownKey, true, now()->addSeconds($cooldownSeconds));

        $this->auditService->record(
            action: 'admin.whatsapp_otp_sent',
            actor: $user,
            targetType: 'user',
            targetUuid: $user->uuid,
            afterState: ['channel' => 'whatsapp_otp'],
            request: $request,
            actorRole: $user->is_super_admin ? 'super_admin' : 'user'
        );

        return response()->json([
            'data' => [
                'challenge_uuid' => $challengeUuid,
                'recipient_masked' => $this->otpHasher->maskPhone($phoneDigits),
                'expires_in_seconds' => $expirySeconds,
            ],
        ]);
    }

    public function verifyWhatsAppOtp(Request $request): JsonResponse
    {
        $waEnabled = AuthProvider::query()
            ->where('code', 'whatsapp_otp')
            ->where('is_globally_enabled', true)
            ->exists();

        if (!$waEnabled) {
            return response()->json([
                'error' => 'provider_disabled',
                'message' => 'WhatsApp OTP is disabled globally.',
            ], 403);
        }

        $validated = $request->validate([
            'challenge_uuid' => ['required', 'uuid'],
            'otp' => ['required', 'string', 'min:4', 'max:8'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $challengeUuid = $validated['challenge_uuid'];
        $cacheKey = "admin_wa_otp_challenge:{$challengeUuid}";
        $challenge = Cache::get($cacheKey);

        if (!is_array($challenge)) {
            return response()->json([
                'error' => 'otp_expired',
                'message' => 'WhatsApp OTP challenge has expired or was already used. Request a new code.',
            ], 422);
        }

        if (!hash_equals((string) ($challenge['session_id'] ?? ''), $request->session()->getId())) {
            return response()->json([
                'error' => 'otp_context_mismatch',
                'message' => 'OTP session mismatch. Please request a fresh verification code.',
            ], 422);
        }

        if ((int) ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($cacheKey);
            return response()->json([
                'error' => 'otp_expired',
                'message' => 'WhatsApp OTP code has expired. Please request a new code.',
            ], 422);
        }

        $attempts = (int) ($challenge['attempts'] ?? 0);
        $maxAttempts = (int) ($challenge['max_attempts'] ?? 5);
        if ($attempts >= $maxAttempts) {
            Cache::forget($cacheKey);
            return response()->json([
                'error' => 'otp_exhausted',
                'message' => 'Maximum verification attempts exceeded. Please request a new code.',
            ], 422);
        }

        if (!$this->otpHasher->verify(trim($validated['otp']), $challengeUuid, (string) ($challenge['otp_hash'] ?? ''))) {
            $attempts++;
            if ($attempts >= $maxAttempts) {
                Cache::forget($cacheKey);
                return response()->json([
                    'error' => 'otp_exhausted',
                    'message' => 'Invalid code and maximum attempts exceeded. Please request a new code.',
                ], 422);
            }

            $challenge['attempts'] = $attempts;
            $remainingSeconds = max(1, (int) $challenge['expires_at'] - now()->timestamp);
            Cache::put($cacheKey, $challenge, now()->addSeconds($remainingSeconds));

            return response()->json([
                'error' => 'otp_invalid',
                'message' => 'Invalid WhatsApp verification code.',
            ], 422);
        }

        Cache::forget($cacheKey);

        $user = User::query()->find((int) ($challenge['user_id'] ?? 0));
        if (!$user || !$user->isGloballyActive()) {
            return response()->json([
                'error' => 'account_inactive',
                'message' => 'This account is suspended or locked.',
            ], 403);
        }

        if ($user->phone_verified_at === null) {
            $user->update(['phone_verified_at' => now()]);
        }

        $this->completeAdminSessionLogin($user, $request, 'whatsapp_otp');

        return response()->json([
            'data' => [
                'authenticated' => true,
                'redirect_to' => session()->pull('url.intended', $this->resolvePostLoginRedirect($user)),
            ],
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function completeAdminSessionLogin(User $user, Request $request, string $method): void
    {
        $user->update([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->auditService->record(
            action: 'admin.login_succeeded',
            actor: $user,
            targetType: 'user',
            targetUuid: $user->uuid,
            afterState: ['method' => $method],
            request: $request,
            actorRole: $user->is_super_admin ? 'super_admin' : 'user'
        );
    }

    private function resolvePostLoginRedirect(User $user): string
    {
        if ($user->is_super_admin) {
            return route('super-admin.auth-settings');
        }

        $firstOrgId = $user->memberships()
            ->where('membership_status', 'active')
            ->value('organisation_id');

        if ($firstOrgId) {
            $org = Organisation::query()->find($firstOrgId);
            if ($org) {
                return route('org.applications', $org->uuid);
            }
        }

        return '/';
    }
}
