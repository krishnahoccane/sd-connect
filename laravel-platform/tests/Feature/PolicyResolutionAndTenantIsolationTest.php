<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAuthMethod;
use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\User;
use App\Modules\Authentication\Services\AuthenticationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyResolutionAndTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_disablement_cannot_be_overridden_by_organisation_or_application(): void
    {
        AuthProvider::create([
            'code' => 'whatsapp_otp',
            'name' => 'WhatsApp OTP',
            'category' => 'otp',
            'is_globally_enabled' => false,
            'requires_external_health' => false,
            'health_status' => 'healthy',
        ]);

        $org = Organisation::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $app = Application::create([
            'organisation_id' => $org->id,
            'name' => 'Portal A',
            'slug' => 'portal-a',
            'app_url' => 'https://portal-a.example.com',
            'allowed_scopes' => ['openid', 'profile'],
            'allowed_grant_types' => ['authorization_code'],
        ]);

        OrganisationAuthMethod::create([
            'organisation_id' => $org->id,
            'provider_code' => 'whatsapp_otp',
            'is_enabled' => true,
        ]);

        ApplicationAuthMethod::create([
            'organisation_id' => $org->id,
            'application_id' => $app->id,
            'provider_code' => 'whatsapp_otp',
            'is_enabled' => true,
        ]);

        $result = app(AuthenticationPolicyService::class)->resolveEffectiveMethods($org, $app);

        $this->assertEmpty($result['methods']);
        $this->assertSame('disabled_globally', $result['rejected']['whatsapp_otp']);
    }

    public function test_cross_tenant_application_resolution_throws_exception(): void
    {
        $orgA = Organisation::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $orgB = Organisation::create(['name' => 'Org B', 'slug' => 'org-b', 'status' => 'active']);
        $appB = Application::create([
            'organisation_id' => $orgB->id,
            'name' => 'App B',
            'slug' => 'app-b',
            'app_url' => 'https://app-b.example.com',
            'allowed_scopes' => ['openid'],
            'allowed_grant_types' => ['authorization_code'],
        ]);

        $this->expectException(\RuntimeException::class);
        app(AuthenticationPolicyService::class)->resolveEffectiveMethods($orgA, $appB);
    }

    public function test_suspending_central_user_blocks_all_organisation_memberships(): void
    {
        $org = Organisation::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $user = User::create(['primary_email' => 'alice@twsd.me', 'status' => 'active']);
        $user->memberships()->create(['organisation_id' => $org->id, 'membership_status' => 'active']);

        $this->assertTrue($user->hasActiveMembershipIn($org->id));

        $user->update(['status' => 'suspended']);
        $this->assertFalse($user->fresh()->hasActiveMembershipIn($org->id));
    }
}
