<?php

namespace App\Modules\Providers\WhatsApp\Services;

use App\Contracts\AuthenticationProviderInterface;
use App\Models\Application;
use App\Models\Organisation;
use App\Models\User;
use App\Modules\OTP\Services\OtpDeliveryService;
use App\Modules\OTP\Services\OtpVerificationService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class WhatsAppOtpProvider implements AuthenticationProviderInterface
{
    public function __construct(
        private readonly OtpVerificationService $otpVerificationService,
        private readonly OtpDeliveryService $otpDeliveryService,
        private readonly WhatsAppOtpService $whatsAppOtpService
    ) {
    }

    public function providerCode(): string
    {
        return 'whatsapp_otp';
    }

    public function supportsOrganisationCredentials(): bool
    {
        return true;
    }

    public function isConfigurationHealthy(?Organisation $organisation = null): bool
    {
        return $this->whatsAppOtpService->hasCredentials($organisation);
    }

    public function sendOtp(
        Organisation $organisation,
        Application $application,
        string $phone,
        string $purpose = 'signin',
        ?Request $request = null
    ): array {
        $digits = $this->whatsAppOtpService->normalizePhoneDigits($phone);
        $user = User::query()
            ->whereIn('primary_phone', ["+{$digits}", $digits])
            ->first();

        if (!$user || !$user->hasActiveMembershipIn($organisation->id)) {
            throw new InvalidArgumentException('No active user with this phone number belongs to this organisation.');
        }

        $issued = $this->otpVerificationService->createTransaction(
            organisation: $organisation,
            application: $application,
            channel: 'whatsapp_otp',
            recipient: $digits,
            purpose: $purpose,
            user: $user,
            request: $request
        );

        $delivery = $this->otpDeliveryService->deliverWhatsAppOtp(
            transaction: $issued['transaction'],
            organisation: $organisation,
            application: $application,
            recipientPhone: $digits,
            plainOtp: $issued['plain_otp_for_delivery'],
            expiryMinutes: (int) ceil($issued['expiry_seconds'] / 60)
        );

        if ($delivery->delivery_status === 'failed') {
            throw new InvalidArgumentException($delivery->error_message ?: 'Unable to send WhatsApp OTP.');
        }

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
        $purpose = (string) ($payload['purpose'] ?? 'signin');
        $request = $payload['request'] ?? null;

        try {
            $digits = $this->whatsAppOtpService->normalizePhoneDigits((string) ($payload['phone'] ?? ''));

            $tx = $this->otpVerificationService->verify(
                transactionUuid: $txUuid,
                submittedOtp: $otp,
                organisation: $organisation,
                application: $application,
                recipient: $digits,
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

            if ($user->phone_verified_at === null) {
                $user->update(['phone_verified_at' => now()]);
            }

            return [
                'authenticated' => true,
                'provider_subject' => $user->uuid,
                'email' => $user->primary_email,
                'phone' => $user->primary_phone,
                'claims' => ['user_id' => $user->id, 'amr' => ['otp', 'whatsapp']],
                'next_stage_required' => null,
                'error' => null,
            ];
        } catch (Throwable $e) {
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
