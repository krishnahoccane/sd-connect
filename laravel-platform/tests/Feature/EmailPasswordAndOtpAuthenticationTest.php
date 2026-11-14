<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Modules\OTP\Services\OtpVerificationService;
use App\Modules\Providers\Email\Services\EmailPasswordProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class EmailPasswordAndOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function seedContext(): array
    {
        AuthProvider::query()->firstOrCreate(
            ['code' => 'email_password'],
            ['name' => 'Email & Password', 'category' => 'password', 'is_globally_enabled' => true, 'health_status' => 'healthy']
        );

        $org = Organisation::create(['name' => 'TWSD', 'slug' => 'twsd', 'status' => 'active']);
        $app = Application::create([
            'organisation_id' => $org->id,
            'name' => 'Portal',
            'slug' => 'portal',
            'app_url' => 'https://portal.twsd.me',
            'allowed_scopes' => ['openid', 'email'],
            'allowed_grant_types' => ['authorization_code'],
        ]);

        $user = User::create([
            'primary_email' => 'engineer@twsd.me',
            'email_verified_at' => now(),
            'password' => Hash::make('Str0ng!Enterprise#2026'),
            'status' => 'active',
        ]);

        OrganisationUser::create(['organisation_id' => $org->id, 'user_id' => $user->id, 'membership_status' => 'active']);

        return [$org, $app, $user];
    }

    public function test_email_password_login_succeeds_and_invalid_password_triggers_lockout(): void
    {
        [$org, $app, $user] = $this->seedContext();
        $provider = app(EmailPasswordProvider::class);

        $ok = $provider->authenticate(['email' => 'engineer@twsd.me', 'password' => 'Str0ng!Enterprise#2026'], $org, $app);
        $this->assertTrue($ok['authenticated']);

        for ($i = 0; $i < 5; $i++) {
            $fail = $provider->authenticate(['email' => 'engineer@twsd.me', 'password' => 'WrongPassword!'], $org, $app);
            $this->assertFalse($fail['authenticated']);
        }

        $this->assertNotNull($user->fresh()->locked_until);

        $blocked = $provider->authenticate(['email' => 'engineer@twsd.me', 'password' => 'Str0ng!Enterprise#2026'], $org, $app);
        $this->assertFalse($blocked['authenticated']);
    }

    public function test_email_otp_expiry_replay_bruteforce_and_context_binding(): void
    {
        [$org, $app, $user] = $this->seedContext();
        $otherApp = Application::create([
            'organisation_id' => $org->id,
            'name' => 'Other App',
            'slug' => 'other-app',
            'app_url' => 'https://other.twsd.me',
            'allowed_scopes' => ['openid'],
            'allowed_grant_types' => ['authorization_code'],
        ]);

        $otpService = app(OtpVerificationService::class);

        // 1. Single-use & replay prevention
        $issued = $otpService->createTransaction($org, $app, 'email_otp', 'engineer@twsd.me', 'signin', $user);
        $tx = $issued['transaction'];
        $verified = $otpService->verify($tx->uuid, $issued['plain_otp_for_delivery'], $org, $app, 'engineer@twsd.me', 'signin');
        $this->assertSame('verified', $verified->status);

        try {
            $otpService->verify($tx->uuid, $issued['plain_otp_for_delivery'], $org, $app, 'engineer@twsd.me', 'signin');
            $this->fail('Expected replay exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already been used', $e->getMessage());
        }

        // 2. Context binding (reject verification under different application)
        $tx->update(['status' => 'pending', 'expires_at' => now()->addMinutes(5)]);
        try {
            $otpService->verify($tx->uuid, $issued['plain_otp_for_delivery'], $org, $otherApp, 'engineer@twsd.me', 'signin');
            $this->fail('Expected context mismatch exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('context mismatch', $e->getMessage());
        }

        // 3. Expiry enforcement
        $tx->update(['expires_at' => now()->subSeconds(5)]);
        try {
            $otpService->verify($tx->uuid, $issued['plain_otp_for_delivery'], $org, $app, 'engineer@twsd.me', 'signin');
            $this->fail('Expected expiry exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('expired', $e->getMessage());
        }

        // 4. Brute-force exhaustion
        $tx->update(['status' => 'pending', 'expires_at' => now()->addMinutes(5), 'attempt_count' => 0, 'max_attempts' => 3]);
        for ($i = 0; $i < 3; $i++) {
            try {
                $otpService->verify($tx->uuid, '000000', $org, $app, 'engineer@twsd.me', 'signin');
            } catch (InvalidArgumentException) {
            }
        }
        $this->assertSame('exhausted', $tx->fresh()->status);
    }
}
