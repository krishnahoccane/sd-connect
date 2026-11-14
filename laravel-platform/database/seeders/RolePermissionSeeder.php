<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $platformRoles = [
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Full platform governance'],
            ['slug' => 'security_admin', 'name' => 'Security Admin', 'description' => 'Signing keys, security events, policies'],
            ['slug' => 'organisation_manager', 'name' => 'Organisation Manager', 'description' => 'Organisation lifecycle and suspension'],
            ['slug' => 'integration_manager', 'name' => 'Integration Manager', 'description' => 'Global auth providers and OAuth/OIDC settings'],
            ['slug' => 'support_admin', 'name' => 'Support Admin', 'description' => 'Read-only troubleshooting and session inspection'],
            ['slug' => 'auditor', 'name' => 'Auditor', 'description' => 'Immutable audit log and compliance inspection'],
        ];

        foreach ($platformRoles as $role) {
            Role::query()->updateOrCreate(
                ['scope' => 'platform', 'organisation_id' => null, 'slug' => $role['slug']],
                array_merge($role, ['scope' => 'platform', 'organisation_id' => null, 'is_system' => true])
            );
        }

        $permissions = [
            ['scope' => 'platform', 'slug' => 'platform.organisations.manage', 'name' => 'Manage Organisations', 'module' => 'organisations'],
            ['scope' => 'platform', 'slug' => 'platform.providers.manage', 'name' => 'Manage Global Providers', 'module' => 'providers'],
            ['scope' => 'platform', 'slug' => 'platform.oidc.manage', 'name' => 'Manage OIDC & Signing Keys', 'module' => 'oidc'],
            ['scope' => 'platform', 'slug' => 'platform.audit.read', 'name' => 'Read Global Audit Logs', 'module' => 'audit'],
            ['scope' => 'organisation', 'slug' => 'org.applications.manage', 'name' => 'Manage Organisation Applications', 'module' => 'applications'],
            ['scope' => 'organisation', 'slug' => 'org.auth_methods.manage', 'name' => 'Manage Organisation Auth Methods', 'module' => 'authentication'],
            ['scope' => 'organisation', 'slug' => 'org.branding.manage', 'name' => 'Manage Organisation Branding', 'module' => 'branding'],
            ['scope' => 'organisation', 'slug' => 'org.users.manage', 'name' => 'Manage Organisation Users & Invitations', 'module' => 'users'],
            ['scope' => 'organisation', 'slug' => 'org.audit.read', 'name' => 'Read Organisation Audit Logs', 'module' => 'audit'],
        ];

        foreach ($permissions as $perm) {
            Permission::query()->updateOrCreate(['slug' => $perm['slug']], $perm);
        }

        $superAdminRole = Role::query()->where('scope', 'platform')->where('slug', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->permissions()->sync(Permission::query()->pluck('id'));
        }
    }
}
