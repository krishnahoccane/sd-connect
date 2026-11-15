<?php

namespace App\Modules\OTP\Services;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\OtpAttempt;
use App\Models\OtpTransaction;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OtpVerificationService
{
    public function __construct(
        private readonly OtpGenerator $generator,
        private readonly OtpHasher $hasher,
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Issues a new single-use hashed OTP transaction bound to org, app, user, recipient, and purpose.
     *
     * @return array{transaction: OtpTransaction, plain_otp_for_delivery: string, expiry_seconds: int}
     */
    public function createTransaction(
        Organisation $organisation,
        Application $application,
        string $channel,
        string $recipient,
        string $purpose = 'signin',
        ?User $user = null,
        ?Request $request = null
    ): array {
        $orgPolicy = $organisation->otp_policy ?? [];
        $globalDefaults = config('twsd_connect.otp');

        $digits = (int) ($orgPolicy['digits'] ?? $globalDefaults['digits'] ?? 6);
        $expirySeconds = min(
            (int) ($orgPolicy['expiry_seconds'] ?? $globalDefaults['expiry_seconds'] ?? 300),
            (int) ($globalDefaults['expiry_seconds'] ?? 300)
        );
        $cooldownSeconds = max(
            (int) ($orgPolicy['resend_cooldown_seconds'] ?? $globalDefaults['resend_cooldown_seconds'] ?? 60),
            (int) ($globalDefaults['resend_cooldown_seconds'] ?? 60)
        );
        $maxAttempts = min(
            (int) ($orgPolicy['max_verification_attempts'] ?? $globalDefaults['max_verification_attempts'] ?? 5),
            (int) ($globalDefaults['max_verification_attempts'] ?? 5)
        );
        $maxResends = (int) ($globalDefaults['max_resends_per_window'] ?? 3);
        $windowMinutes = (int) ($globalDefaults['resend_window_minutes'] ?? 15);

        $recipientHash = $this->hasher->hashRecipient($recipient);

        $recentCount = OtpTransaction::query()
            ->where('organisation_id', $organisation->id)
            ->where('channel', $channel)
            ->where('recipient_hash', $recipientHash)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($recentCount > $maxResends) {
            $this->auditService->recordSecurityEvent('otp.resend_rate_limited', 'warning', $organisation->id, $user?->id, ['channel' => $channel], $request);
            throw new InvalidArgumentException('Too many OTP requests. Please wait before requesting another code.');
        }

        $latest = OtpTransaction::query()
            ->where('organisation_id', $organisation->id)
            ->where('application_id', $application->id)
            ->where('channel', $channel)
            ->where('recipient_hash', $recipientHash)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($latest && $latest->last_sent_at && $latest->last_sent_at->addSeconds($cooldownSeconds)->isFuture()) {
            throw new InvalidArgumentException('OTP resend cooldown active. Please wait before requesting a new code.');
        }

        return DB::transaction(function () use (
            $organisation,
            $application,
            $user,
            $channel,
            $recipient,
            $recipientHash,
            $purpose,
            $digits,
            $expirySeconds,
            $maxAttempts,
            $request
        ): array {
            OtpTransaction::query()
                ->where('organisation_id', $organisation->id)
                ->where('application_id', $application->id)
                ->where('channel', $channel)
                ->where('recipient_hash', $recipientHash)
                ->where('purpose', $purpose)
                ->where('status', 'pending')
                ->update(['status' => 'revoked']);

            $uuid = (string) Str::uuid();
            $plainOtp = $this->generator->generateNumeric($digits);

            $tx = OtpTransaction::create([
                'uuid' => $uuid,
                'organisation_id' => $organisation->id,
                'application_id' => $application->id,
                'user_id' => $user?->id,
                'channel' => $channel,
                'recipient_hash' => $recipientHash,
                'recipient_masked' => $channel === 'email_otp'
                    ? $this->hasher->maskEmail($recipient)
                    : $this->hasher->maskPhone($recipient),
                'otp_hash' => $this->hasher->hash($plainOtp, $uuid),
                'purpose' => $purpose,
                'attempt_count' => 0,
                'max_attempts' => $maxAttempts,
                'resend_count' => 0,
                'status' => 'pending',
                'expires_at' => now()->addSeconds($expirySeconds),
                'last_sent_at' => now(),
                'ip_address' => $request?->ip(),
            ]);

            return [
                'transaction' => $tx,
                'plain_otp_for_delivery' => $plainOtp,
                'expiry_seconds' => $expirySeconds,
            ];
        });
    }

    /**
     * Atomically verifies an OTP with row locking to block concurrent replay & brute-force attacks.
     */
    public function verify(
        string $transactionUuid,
        string $submittedOtp,
        Organisation $organisation,
        Application $application,
        string $recipient,
        string $purpose = 'signin',
        ?Request $request = null
    ): OtpTransaction {
        return DB::transaction(function () use ($transactionUuid, $submittedOtp, $organisation, $application, $recipient, $purpose, $request): OtpTransaction {
            $tx = OtpTransaction::query()
                ->where('uuid', $transactionUuid)
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                throw new InvalidArgumentException('Invalid OTP transaction.');
            }

            // Strict context binding check
            if (
                $tx->organisation_id !== $organisation->id ||
                $tx->application_id !== $application->id ||
                $tx->purpose !== $purpose ||
                !hash_equals($tx->recipient_hash, $this->hasher->hashRecipient($recipient))
            ) {
                $this->auditService->recordSecurityEvent('otp.context_mismatch', 'high', $organisation->id, $tx->user_id, ['tx' => $tx->uuid], $request);
                throw new InvalidArgumentException('OTP transaction context mismatch.');
            }

            if ($tx->status === 'verified') {
                $this->auditService->recordSecurityEvent('otp.replay_attempt', 'critical', $organisation->id, $tx->user_id, ['tx' => $tx->uuid], $request);
                throw new InvalidArgumentException('OTP has already been used.');
            }

            if ($tx->status !== 'pending') {
                throw new InvalidArgumentException('OTP transaction is no longer active.');
            }

            if ($tx->expires_at->isPast()) {
                $tx->update(['status' => 'expired']);
                throw new InvalidArgumentException('OTP has expired.');
            }

            if ($tx->attempt_count >= $tx->max_attempts) {
                $tx->update(['status' => 'exhausted']);
                throw new InvalidArgumentException('Maximum OTP verification attempts exceeded.');
            }

            $matched = $this->hasher->verify(trim($submittedOtp), $tx->uuid, $tx->otp_hash);

            OtpAttempt::create([
                'otp_transaction_id' => $tx->id,
                'succeeded' => $matched,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 512) : null,
                'attempted_at' => now(),
            ]);

            if (!$matched) {
                $newCount = $tx->attempt_count + 1;
                $newStatus = $newCount >= $tx->max_attempts ? 'exhausted' : 'pending';
                $tx->update([
                    'attempt_count' => $newCount,
                    'status' => $newStatus,
                ]);

                if ($newStatus === 'exhausted') {
                    $this->auditService->recordSecurityEvent('otp.brute_force_exhausted', 'high', $organisation->id, $tx->user_id, ['tx' => $tx->uuid], $request);
                }

                throw new InvalidArgumentException('Invalid OTP code.');
            }

            $tx->update([
                'attempt_count' => $tx->attempt_count + 1,
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            return $tx;
        });
    }
}
