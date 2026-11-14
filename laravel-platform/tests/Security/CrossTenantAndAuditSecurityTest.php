<?php

namespace Tests\Security;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Modules\Applications\Services\ApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CrossTenantAndAuditSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_user_is_blocked_from_protected_routes(): void
    {
        $user = User::create(['primary_email' => 'suspended@twsd.me', 'status' => 'suspended']);
        Passport::actingAs($user, ['openid', 'email']);

        $response = $this->getJson('/oauth/userinfo');
        $response->assertStatus(403)->assertJsonFragment(['error' => 'account_suspended']);
    }

    public function test_cross_tenant_application_access_is_rejected_and_audit_logs_are_recorded(): void
    {
        AuthProvider::query()->firstOrCreate(
            ['code' => 'email_password'],
            ['name' => 'Email & Password', 'category' => 'password', 'is_globally_enabled' => true, 'health_status' => 'healthy']
        );

        $orgA = Organisation::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $orgB = Organisation::create(['name' => 'Org B', 'slug' => 'org-b', 'status' => 'active']);
        OrganisationAuthMethod::create(['organisation_id' => $orgA->id, 'provider_code' => 'email_password', 'is_enabled' => true]);

        $actor = User::create(['primary_email' => 'admin@orga.com', 'status' => 'active']);
        OrganisationUser::create(['organisation_id' => $orgA->id, 'user_id' => $actor->id, 'membership_status' => 'active']);

        $created = app(ApplicationService::class)->createApplication($orgA, [
            'name' => 'Org A App',
            'app_url' => 'https://orga.example.com',
            'application_type' => 'web_confidential',
            'redirect_uris' => ['https://orga.example.com/callback'],
        ], $actor);

        // Audit log generated for application creation and client creation
        $this->assertTrue(
            AuditLog::query()->where('organisation_id', $orgA->id)->where('action', 'application.created')->exists()
        );
        $this->assertTrue(
            AuditLog::query()->where('organisation_id', $orgA->id)->where('action', 'oauth.client_created')->exists()
        );

        // Org B attempting to modify Org A's application must fail
        $this->expectException(InvalidArgumentException::class);
        app(ApplicationService::class)->updateApplication($orgB, $created['application'], ['name' => 'Hijacked']);
    }
}
