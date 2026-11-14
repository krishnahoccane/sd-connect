<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAuthMethod;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Modules\Applications\Services\ApplicationService;
use App\Modules\Authentication\Services\AuthenticationPolicyService;
use App\Modules\Providers\Email\Services\EmailConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class EmailSenderIsolationAndAdminPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_sender_isolation_never_uses_another_organisations_sender(): void
    {
        $orgA = Organisation::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $orgB = Organisation::create(['name' => 'Org B', 'slug' => 'org-b', 'status' => 'active']);

        EmailConfiguration::create([
            'scope' => 'organisation',
            'organisation_id' => $orgA->id,
            'driver' => 'org_smtp',
            'sender_name' => 'Org A Auth',
            'sender_email' => 'auth@orga.example.com',
            'is_verified' => true,
        ]);

        // Org B has no email config and no verified platform default; must throw rather than borrow Org A
        $this->expectException(RuntimeException::class);
        app(EmailConfigurationService::class)->resolveEffectiveSender($orgB);
    }

    public function test_global_organisation_and_application_provider_hierarchy(): void
    {
        AuthProvider::create([
            'code' => 'email_otp',
            'name' => 'Email OTP',
            'category' => 'otp',
            'is_globally_enabled' => false,
            'requires_external_health' => false,
            'health_status' => 'healthy',
        ]);

        $org = Organisation::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $app = Application::create([
            'organisation_id' => $org->id,
            'name' => 'Portal',
            'slug' => 'portal',
            'app_url' => 'https://portal.example.com',
            'allowed_scopes' => ['openid'],
            'allowed_grant_types' => ['authorization_code'],
        ]);

        // Application cannot enable globally disabled provider
        try {
            app(ApplicationService::class)->syncAuthMethods($org, $app, ['email_otp']);
            $this->fail('Expected global restriction exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('disabled globally', $e->getMessage());
        }

        // Enable globally, keep disabled at org -> application still blocked
        AuthProvider::query()->where('code', 'email_otp')->update(['is_globally_enabled' => true]);
        try {
            app(ApplicationService::class)->syncAuthMethods($org, $app, ['email_otp']);
            $this->fail('Expected organisation restriction exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('disabled by the organisation', $e->getMessage());
        }

        // Enable at org, keep disabled on app -> policy resolver rejects as disabled_by_application
        OrganisationAuthMethod::create(['organisation_id' => $org->id, 'provider_code' => 'email_otp', 'is_enabled' => true]);
        ApplicationAuthMethod::create(['organisation_id' => $org->id, 'application_id' => $app->id, 'provider_code' => 'email_otp', 'is_enabled' => false]);

        $resolved = app(AuthenticationPolicyService::class)->resolveEffectiveMethods($org, $app);
        $this->assertSame('disabled_by_application', $resolved['rejected']['email_otp']);
    }
}
