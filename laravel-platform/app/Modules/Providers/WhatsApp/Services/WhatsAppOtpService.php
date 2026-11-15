<?php

namespace App\Modules\Providers\WhatsApp\Services;

use App\Models\Organisation;
use App\Models\ProviderConfiguration;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class WhatsAppOtpService
{
    public function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Phone number must be in international format without + (8 to 15 digits, e.g. 919876543210).');
        }

        return $digits;
    }

    public function hasCredentials(?Organisation $organisation = null): bool
    {
        try {
            $this->resolveCredentials($organisation);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @return array{endpoint: string, appkey: string, authkey: string}
     */
    public function resolveCredentials(?Organisation $organisation = null): array
    {
        $endpoint = (string) config('twsd_connect.providers.whatsapp.endpoint', 'https://api.whatsapp.webglit.com/api/create-message');

        if ($organisation) {
            $orgConfig = ProviderConfiguration::query()
                ->where('scope', 'organisation')
                ->where('organisation_id', $organisation->id)
                ->where('provider_code', 'whatsapp_otp')
                ->where('is_active', true)
                ->first();

            if ($orgConfig && !$orgConfig->use_platform_default) {
                $secrets = $orgConfig->encrypted_credentials ?? [];
                $appkey = trim((string) ($secrets['appkey'] ?? ''));
                $authkey = trim((string) ($secrets['authkey'] ?? ''));
                if ($appkey !== '' && $authkey !== '') {
                    return [
                        'endpoint' => $endpoint,
                        'appkey' => $appkey,
                        'authkey' => $authkey,
                    ];
                }
            }
        }

        $globalConfig = ProviderConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->where('provider_code', 'whatsapp_otp')
            ->where('is_active', true)
            ->first();

        $secrets = $globalConfig?->encrypted_credentials ?? [];
        $appkey = trim((string) ($secrets['appkey'] ?? config('twsd_connect.providers.whatsapp.appkey', '')));
        $authkey = trim((string) ($secrets['authkey'] ?? config('twsd_connect.providers.whatsapp.authkey', '')));

        if ($appkey === '' || $authkey === '') {
            throw new RuntimeException('WhatsApp OTP appkey and authkey are not configured yet.');
        }

        return [
            'endpoint' => $endpoint,
            'appkey' => $appkey,
            'authkey' => $authkey,
        ];
    }

    /**
     * Dispatches a WhatsApp message via Webglit multipart form-data API.
     *
     * @return array{to_masked: string, http_status: int, sanitized_response: array<string, mixed>}
     */
    public function sendMessage(string $phoneNumber, string $message, ?Organisation $organisation = null): array
    {
        $creds = $this->resolveCredentials($organisation);
        $toDigits = $this->normalizePhoneDigits($phoneNumber);
        $timeout = (int) config('twsd_connect.providers.whatsapp.timeout_seconds', 10);

        $response = Http::asMultipart()
            ->timeout($timeout)
            ->post($creds['endpoint'], [
                'appkey' => $creds['appkey'],
                'authkey' => $creds['authkey'],
                'to' => $toDigits,
                'message' => $message,
            ]);

        $bodyJson = $response->json();
        $isLogicalError = is_array($bodyJson) && (
            ($bodyJson['status'] ?? null) === false
            || ($bodyJson['success'] ?? null) === false
            || !empty($bodyJson['error'])
        );

        if (!$response->successful() || $isLogicalError) {
            $detail = is_array($bodyJson)
                ? (string) ($bodyJson['message'] ?? $bodyJson['error'] ?? $response->body())
                : $response->body();

            throw new RuntimeException('WhatsApp API error: ' . mb_substr(trim($detail) ?: ('HTTP ' . $response->status()), 0, 300));
        }

        $masked = substr($toDigits, 0, 2) . str_repeat('*', max(2, strlen($toDigits) - 4)) . substr($toDigits, -2);

        return [
            'to_masked' => $masked,
            'http_status' => $response->status(),
            'sanitized_response' => is_array($bodyJson)
                ? collect($bodyJson)->only(['message_status', 'status', 'data', 'message'])->all()
                : ['raw_status' => $response->status()],
        ];
    }
}
