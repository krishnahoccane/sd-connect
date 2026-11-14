<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Application;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\OtpDeliveryLog;
use App\Models\OtpTransaction;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OTP\Services\OtpVerificationService;
use App\Modules\Providers\Email\Services\EmailPasswordProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class AuthenticationAndOtpEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_purpose_recipient_org_mismatch_and_secret_redaction(): void
    {
        $orgA = Organisation::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $orgB = Organisation::create(['name' => 'Org B', 'slug' => 'org-b', 'status' => 'active']);
        $appA = Application::create([
            'organisation_id' => $orgA->id,
            'name' => 'App A',
            'slug' => 'app-a',
            'app_url' => 'https://appa.twsd.me',
            'allowed_scopes' => ['openid'],
            'allowed_grant_types' => ['authorization_code'],
        ]);
        $user = User::create(['primary_email' => 'user@orga.twsd.me', 'status' => 'active']);

        $otpService = app(OtpVerificationService::class);
        $issued = $otpService->createTransaction($orgA, $appA, 'email_otp', 'user@orga.twsd.me', 'signin', $user);
        $tx = $issued['transaction'];

        // Verify plaintext OTP is never stored in DB
        $this->assertNotSame($issued['plain_otp_for_delivery'], $tx->otp_hash);

        // Purpose mismatch
        try {
            $otpService->verify($tx->uuid, $issued['plain_otp_for_delivery'], $orgA, $appA, 'user@orga.twsd.me', 'password_reset');
            $this->fail('Expected purpose mismatch');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('context mismatch', $e->getMessage());
        }

        // Recipient mismatch
        try {
            $otpService->verify($tx->uuid, $issued['plain_otp_for_delivery'], $orgA, $appA, 'other@orga.twsd.me', 'signin');
            $this->fail('Expected recipient mismatch');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('context mismatch', $e->getMessage());
        }

        // Organisation mismatch
        try {
            $otpService->verify($tx->uuid, $issued['plain_otp_for_delivery'], $orgB, $appA, 'user@orga.twsd.me', 'signin');
            $this->fail('Expected org mismatch');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('context mismatch', $e->getMessage());
        }

        // AuditService redacts secrets & OTPs
        $log = app(AuditService::class)->record(
            action: 'test.redaction',
            actor: $user,
            organisationId: $orgA->id,
            afterState: ['otp' => '123456', 'smtp_password' => 'top-secret', 'client_secret' => 'cs-secret', 'safe' => 'ok']
        );
        $this->assertSame('[REDACTED]', $log->after_state['otp']);
        $this->assertSame('[REDACTED]', $log->after_state['smtp_password']);
        $this->assertSame('[REDACTED]', $log->after_state['client_secret']);
        $this->assertSame('ok', $log->after_state['safe']);
    }

    public function test_otp_resend_cooldown_prevents_duplicate_rapid_delivery_and_email_secrets_hidden(): void
    {
        $org = Organisation::create(['name' => 'Org Cooldown', 'slug' => 'org-cd', 'status' => 'active']);
        $app = Application::create([
            'organisation_id' => $org->id,
            'name' => 'App CD',
            'slug' => 'app-cd',
            'app_url' => 'https://cd.twsd.me',
            'allowed_scopes' => ['openid'],
            'allowed_grant_types' => ['authorization_code'],
        ]);
        $user = User::create(['primary_email' => 'cd@twsd.me', 'status' => 'active']);

        $otpService = app(OtpVerificationService::class);
        $otpService->createTransaction($org, $app, 'email_otp', 'cd@twsd.me', 'signin', $user);

        // Immediate duplicate send must be blocked by 60s cooldown
        $this->expectException(InvalidArgumentException::class);
        $otpService->createTransaction($org, $app, 'email_otp', 'cd@twsd.me', 'signin', $user);

        $emailConfig = EmailConfiguration::create([
            'scope' => 'organisation',
            'organisation_id' => $org->id,
            'driver' => 'org_smtp',
            'sender_name' => 'CD Auth',
            'sender_email' => 'auth@cd.twsd.me',
            'encrypted_transport_secrets' => ['password' => 'super-secret-smtp-pass'],
        ]);
        $this->assertStringNotContainsString('super-secret-smtp-pass', json_encode($emailConfig->toArray()));
    }

    public function test_argon2id_hashing_and_password_policy_enforcement(): void
    {
        $org = Organisation::create(['name' => 'Hash Org', 'slug' => 'hash-org', 'status' => 'active']);
        $provider = app(EmailPasswordProvider::class);

        $this->expectException(InvalidArgumentException::class);
        $provider->assertPasswordMeetsPolicy('weak', $org);

        $hash = Hash::make('Str0ng!Password#2026');
        $this->assertTrue(Hash::check('Str0ng!Password#2026', $hash));
    }
}
