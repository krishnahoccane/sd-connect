<?php

namespace App\Modules\OTP\Services;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\OtpDeliveryLog;
use App\Models\OtpTransaction;
use App\Modules\Providers\Email\Services\EmailConfigurationService;
use Throwable;

class OtpDeliveryService
{
    public function __construct(private readonly EmailConfigurationService $emailConfigService)
    {
    }

    public function deliverEmailOtp(
        OtpTransaction $transaction,
        Organisation $organisation,
        Application $application,
        string $recipientEmail,
        string $plainOtp,
        int $expiryMinutes
    ): OtpDeliveryLog {
        try {
            $senderConfig = $this->emailConfigService->resolveEffectiveSender($organisation);

            $replacements = [
                '{{otp}}' => $plainOtp,
                '{{application_name}}' => $application->name,
                '{{organisation_name}}' => $organisation->name,
                '{{expiry_minutes}}' => (string) $expiryMinutes,
            ];

            $subject = strtr(
                $senderConfig->otp_subject_template ?: '{{otp}} is your sign-in code for {{application_name}}',
                $replacements
            );
            $body = strtr(
                $senderConfig->otp_body_template ?: 'Your verification code for {{application_name}} is {{otp}}. Valid for {{expiry_minutes}} minutes.',
                $replacements
            );

            $this->emailConfigService->dispatchRawEmail($senderConfig, $recipientEmail, $subject, $body);

            return OtpDeliveryLog::create([
                'otp_transaction_id' => $transaction->id,
                'organisation_id' => $organisation->id,
                'provider_code' => $senderConfig->driver,
                'delivery_status' => 'sent',
                'sanitized_response' => [
                    'sender_email' => $senderConfig->sender_email,
                    'driver' => $senderConfig->driver,
                ],
            ]);
        } catch (Throwable $e) {
            return OtpDeliveryLog::create([
                'otp_transaction_id' => $transaction->id,
                'organisation_id' => $organisation->id,
                'provider_code' => 'email_otp',
                'delivery_status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }
}
