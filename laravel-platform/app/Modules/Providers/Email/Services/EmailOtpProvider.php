<?php

namespace App\Modules\Providers\Email\Services;

use App\Contracts\AuthenticationProviderInterface;
use App\Models\Application;
use App\Models\Organisation;
use App\Models\User;
use App\Modules\OTP\Services\OtpDeliveryService;
use App\Modules\OTP\Services\OtpVerificationService;
use Illuminate\Http\Request;

class EmailOtpProvider implements AuthenticationProviderInterface
{
    public function __construct(
        private readonly OtpVerificationService $otpVerificationService,
        private readonly OtpDeliveryService $otpDeliveryService,
        private readonly EmailConfigurationService $emailConfigService
    ) {
    }

    public function providerCode(): string
    {
        return 'email_otp';
    }

    public function supportsOrganisationCredentials(): bool
    {
        return true;
    }

    public function isConfigurationHealthy(?Organisation $organisation = null): bool
    {
        if (!$organisation) {
            return false;
        }
        try {
            $this->emailConfigService->resolveEffectiveSender($organisation);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function sendOtp(
        Organisation $organisation,
        Application $application,
        string $email,
        string $purpose = 'signin',
        ?Request $request = null
    ): array {
        $normalized = strtolower(trim($email));
        $user = User::query()->where('primary_email', $normalized)->first();

        if (!$user || !$user->hasActiveMembershipIn($organisation->id)) {
            throw new \InvalidArgumentException('User is not active in this organisation.');
        }

        $issued = $this->otpVerificationService->createTransaction(
            organisation: $organisation,
            application: $application,
            channel: 'email_otp',
            recipient: $normalized,
            purpose: $purpose,
            user: $user,
            request: $request
        );

        $delivery = $this->otpDeliveryService->deliverEmailOtp(
            transaction: $issued['transaction'],
            organisation: $organisation,
            application: $application,
            recipientEmail: $normalized,
            plainOtp: $issued['plain_otp_for_delivery'],
            expiryMinutes: (int) ceil($issued['expiry_seconds'] / 60)
        );

        return [
            'transaction_uuid' => $issued['transaction']->uuid,
            'recipient_masked' => $issued['transaction']->recipient_masked,
            'expires_in_seconds' => $issued['expiry_seconds'],
            'delivery_status' => $delivery->delivery_status,
        ];
    }

    public function authenticate(array $payload, Organisation $organisation, Application $application): array
    {
        $txUuid = (string) ($payload['transaction_uuid'] ?? '');
        $otp = (string) ($payload['otp'] ?? '');
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $purpose = (string) ($payload['purpose'] ?? 'signin');
        $request = $payload['request'] ?? null;

        try {
            $tx = $this->otpVerificationService->verify(
                transactionUuid: $txUuid,
                submittedOtp: $otp,
                organisation: $organisation,
                application: $application,
                recipient: $email,
                purpose: $purpose,
                request: $request instanceof Request ? $request : null
            );

            $user = $tx->user()->firstOrFail();
            if (!$user->hasActiveMembershipIn($organisation->id)) {
                return [
                    'authenticated' => false,
                    'provider_subject' => null,
                    'email' => null,
                    'phone' => null,
                    'claims' => [],
                    'next_stage_required' => null,
                    'error' => 'Organisation membership or central account is inactive.',
                ];
            }

            if ($user->email_verified_at === null) {
                $user->update(['email_verified_at' => now()]);
            }

            return [
                'authenticated' => true,
                'provider_subject' => $user->uuid,
                'email' => $user->primary_email,
                'phone' => $user->primary_phone,
                'claims' => ['user_id' => $user->id, 'amr' => ['otp']],
                'next_stage_required' => null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'authenticated' => false,
                'provider_subject' => null,
                'email' => null,
                'phone' => null,
                'claims' => [],
                'next_stage_required' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
