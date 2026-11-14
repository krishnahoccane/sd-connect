<?php

namespace Tests\Security;

use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OrganisationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Applications\Services\ApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TenantIsolationAndAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_tenant_api_and_role_boundary_enforcement(): void
    {
        AuthProvider::query()->firstOrCreate(
            ['code' => 'email_password'],
            ['name' => 'Email & Password', 'category' => 'password', 'is_globally_enabled' => true, 'health_status' => 'healthy']
        );

        $orgA = Organisation::create(['name' => 'Tenant Alpha', 'slug' => 'alpha', 'status' => 'active']);
        $orgB = Organisation::create(['name' => 'Tenant Beta', 'slug' => 'beta', 'status' => 'active']);

        OrganisationAuthMethod::create(['organisation_id' => $orgA->id, 'provider_code' => 'email_password', 'is_enabled' => true]);
        OrganisationAuthMethod::create(['organisation_id' => $orgB->id, 'provider_code' => 'email_password', 'is_enabled' => true]);

        $permApps = Permission::firstOrCreate(['slug' => 'org.applications.manage'], ['scope' => 'organisation', 'name' => 'Manage Apps', 'module' => 'applications']);
        $roleA = Role::create(['scope' => 'organisation', 'organisation_id' => $orgA->id, 'slug' => 'org_admin', 'name' => 'Org A Admin']);
        $roleA->permissions()->sync([$permApps->id]);

        $adminA = User::create(['primary_email' => 'admin@alpha.twsd.me', 'status' => 'active', 'is_super_admin' => false]);
        OrganisationUser::create(['organisation_id' => $orgA->id, 'user_id' => $adminA->id, 'membership_status' => 'active']);
        DB::table('user_roles')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $adminA->id,
            'role_id' => $roleA->id,
            'organisation_id' => $orgA->id,
        ]);

        $appB = app(ApplicationService::class)->createApplication($orgB, [
            'name' => 'Beta Core',
            'app_url' => 'https://beta.twsd.me',
            'application_type' => 'web_confidential',
            'redirect_uris' => ['https://beta.twsd.me/callback'],
        ]);

        Passport::actingAs($adminA, ['openid']);

        // 1. Org A admin cannot access Org B applications list
        $this->getJson("/api/v1/organisation/{$orgB->uuid}/applications")->assertStatus(403);

        // 2. Org A admin cannot tamper with Org A route using Org B application UUID
        $this->getJson("/api/v1/organisation/{$orgA->uuid}/applications/{$appB['application']->uuid}")->assertStatus(403);

        // 3. Org A admin cannot access Super Admin global provider endpoints
        $this->putJson('/api/v1/super-admin/providers/email_password', ['is_globally_enabled' => false])->assertStatus(403);

        // 4. Suspended organisation blocks authorization requests
        $orgB->update(['status' => 'suspended']);
        $this->getJson('/oauth/authorize?' . http_build_query([
            'client_id' => $appB['client_id'],
            'redirect_uri' => 'https://beta.twsd.me/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'code_challenge' => str_repeat('b', 43),
            'code_challenge_method' => 'S256',
        ]))->assertStatus(403);

        // 5. Suspended central user blocked on Org A APIs
        $adminA->update(['status' => 'suspended']);
        $this->getJson("/api/v1/organisation/{$orgA->uuid}/applications")->assertStatus(403);
    }
}
