<?php

namespace App\Modules\Providers\Email\Services;

use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\Organisation;
use App\Models\ProviderConfiguration;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

class EmailConfigurationService
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function upsertPlatformDefault(array $data, ?User $actor = null, ?Request $request = null): EmailConfiguration
    {
        return DB::transaction(function () use ($data, $actor, $request): EmailConfiguration {
            $existing = EmailConfiguration::query()
                ->where('scope', 'global')
                ->whereNull('organisation_id')
                ->first();

            $secrets = $data['secrets'] ?? ($existing?->encrypted_transport_secrets);

            $config = EmailConfiguration::query()->updateOrCreate(
                ['scope' => 'global', 'organisation_id' => null],
                [
                    'driver' => $data['driver'] ?? 'platform_google_workspace',
                    'sender_name' => $data['sender_name'],
                    'sender_email' => strtolower(trim($data['sender_email'])),
                    'encrypted_transport_secrets' => $secrets,
                    'smtp_settings' => $data['smtp_settings'] ?? $existing?->smtp_settings,
                    'otp_subject_template' => $data['otp_subject_template'] ?? $existing?->otp_subject_template ?? '{{otp}} is your sign-in code for {{application_name}}',
                    'otp_body_template' => $data['otp_body_template'] ?? $existing?->otp_body_template ?? 'Your one-time verification code for {{application_name}} is {{otp}}. It expires in {{expiry_minutes}} minutes.',
                    'is_verified' => (bool) ($data['is_verified'] ?? false),
                ]
            );

            ProviderConfiguration::query()->updateOrCreate(
                ['scope' => 'global', 'organisation_id' => null, 'provider_code' => 'email_otp'],
                [
                    'is_active' => true,
                    'use_platform_default' => true,
                    'health_status' => $config->is_verified ? 'healthy' : 'unconfigured',
                    'last_verified_at' => $config->is_verified ? now() : null,
                ]
            );

            AuthProvider::query()->where('code', 'email_otp')->update([
                'health_status' => $config->is_verified ? 'healthy' : 'unconfigured',
                'last_health_check_at' => now(),
            ]);

            $this->auditService->record(
                action: 'email_config.platform_updated',
                actor: $actor,
                targetType: 'email_configuration',
                targetUuid: $config->uuid,
                afterState: ['driver' => $config->driver, 'sender_email' => $config->sender_email],
                request: $request
            );

            return $config;
        });
    }

    public function upsertOrganisationConfiguration(
        Organisation $organisation,
        array $data,
        ?User $actor = null,
        ?Request $request = null
    ): EmailConfiguration {
        $driver = $data['driver'] ?? 'org_smtp';
        if (!in_array($driver, ['platform_google_workspace', 'org_smtp', 'org_google_workspace'], true)) {
            throw new InvalidArgumentException("Unsupported email driver [{$driver}].");
        }

        return DB::transaction(function () use ($organisation, $driver, $data, $actor, $request): EmailConfiguration {
            $existing = EmailConfiguration::query()
                ->where('scope', 'organisation')
                ->where('organisation_id', $organisation->id)
                ->first();

            $secrets = $data['secrets'] ?? ($existing?->encrypted_transport_secrets);

            $config = EmailConfiguration::query()->updateOrCreate(
                ['scope' => 'organisation', 'organisation_id' => $organisation->id],
                [
                    'driver' => $driver,
                    'sender_name' => $data['sender_name'],
                    'sender_email' => strtolower(trim($data['sender_email'])),
                    'encrypted_transport_secrets' => $secrets,
                    'smtp_settings' => $data['smtp_settings'] ?? $existing?->smtp_settings,
                    'otp_subject_template' => $data['otp_subject_template'] ?? $existing?->otp_subject_template,
                    'otp_body_template' => $data['otp_body_template'] ?? $existing?->otp_body_template,
                    'is_verified' => false,
                ]
            );

            ProviderConfiguration::query()->updateOrCreate(
                ['scope' => 'organisation', 'organisation_id' => $organisation->id, 'provider_code' => 'email_otp'],
                [
                    'is_active' => true,
                    'use_platform_default' => $driver === 'platform_google_workspace',
                    'health_status' => $driver === 'platform_google_workspace' ? 'healthy' : 'unconfigured',
                ]
            );

            $this->auditService->record(
                action: 'email_config.organisation_updated',
                actor: $actor,
                organisationId: $organisation->id,
                targetType: 'email_configuration',
                targetUuid: $config->uuid,
                afterState: ['driver' => $driver, 'sender_email' => $config->sender_email],
                request: $request
            );

            return $config;
        });
    }

    /**
     * Resolves email configuration for an organisation.
     * NEVER silently sends through another organisation's configuration.
     */
    public function resolveEffectiveSender(Organisation $organisation): EmailConfiguration
    {
        $orgConfig = EmailConfiguration::query()
            ->where('scope', 'organisation')
            ->where('organisation_id', $organisation->id)
            ->first();

        if ($orgConfig) {
            if ($orgConfig->driver === 'platform_google_workspace') {
                return $this->resolvePlatformDefault($orgConfig);
            }

            if (!$orgConfig->is_verified) {
                throw new RuntimeException('Organisation email configuration is not verified.');
            }

            return $orgConfig;
        }

        return $this->resolvePlatformDefault();
    }

    public function testConfiguration(EmailConfiguration $config, string $recipientEmail): array
    {
        $effective = $config->driver === 'platform_google_workspace' && $config->scope === 'organisation'
            ? $this->resolvePlatformDefault($config)
            : $config;

        $this->dispatchRawEmail(
            config: $effective,
            toEmail: $recipientEmail,
            subject: 'TWSD Connect — Email Delivery Verification',
            body: 'This test email confirms your TWSD Connect sender configuration is operational.'
        );

        $config->update([
            'is_verified' => true,
            'last_tested_at' => now(),
        ]);

        if ($config->organisation_id) {
            ProviderConfiguration::query()
                ->where('scope', 'organisation')
                ->where('organisation_id', $config->organisation_id)
                ->where('provider_code', 'email_otp')
                ->update(['health_status' => 'healthy', 'last_verified_at' => now()]);
        } else {
            AuthProvider::query()->where('code', 'email_otp')->update([
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
            ]);
        }

        return ['tested' => true, 'sender_email' => $effective->sender_email, 'tested_at' => now()->toIso8601String()];
    }

    public function dispatchRawEmail(EmailConfiguration $config, string $toEmail, string $subject, string $body): void
    {
        $mailerName = 'twsd_dynamic_' . $config->uuid;
        $secrets = $config->encrypted_transport_secrets ?? [];
        $smtp = $config->smtp_settings ?? [];

        if ($config->driver === 'org_smtp') {
            config([
                "mail.mailers.{$mailerName}" => [
                    'transport' => 'smtp',
                    'host' => $smtp['host'] ?? '',
                    'port' => (int) ($smtp['port'] ?? 587),
                    'encryption' => $smtp['encryption'] ?? 'tls',
                    'username' => $smtp['username'] ?? '',
                    'password' => $secrets['password'] ?? '',
                    'timeout' => 10,
                ],
            ]);
        } else {
            // Google Workspace / Custom Platform SMTP Relay configuration
            config([
                "mail.mailers.{$mailerName}" => [
                    'transport' => 'smtp',
                    'host' => $smtp['host'] ?? 'smtp.gmail.com',
                    'port' => (int) ($smtp['port'] ?? 587),
                    'encryption' => $smtp['encryption'] ?? 'tls',
                    'username' => !empty($smtp['username']) ? $smtp['username'] : $config->sender_email,
                    'password' => $secrets['app_password'] ?? ($secrets['password'] ?? ($secrets['access_token'] ?? '')),
                    'timeout' => 10,
                ],
            ]);
        }

        Mail::mailer($mailerName)->raw($body, function ($message) use ($config, $toEmail, $subject): void {
            $message->from($config->sender_email, $config->sender_name)
                ->to($toEmail)
                ->subject($subject);
        });
    }

    private function resolvePlatformDefault(?EmailConfiguration $orgOverride = null): EmailConfiguration
    {
        $platform = EmailConfiguration::query()
            ->where('scope', 'global')
            ->whereNull('organisation_id')
            ->first();

        if (!$platform || !$platform->is_verified) {
            throw new RuntimeException('Platform default Google Workspace email configuration is missing or unverified.');
        }

        if ($orgOverride) {
            $platform->otp_subject_template = $orgOverride->otp_subject_template ?? $platform->otp_subject_template;
            $platform->otp_body_template = $orgOverride->otp_body_template ?? $platform->otp_body_template;
        }

        return $platform;
    }
}
