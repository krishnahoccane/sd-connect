<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OrganisationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InitialOrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $orgName = env('TWSD_INITIAL_ORG_NAME', 'TWSD Enterprise');
        $orgSlug = env('TWSD_INITIAL_ORG_SLUG', 'twsd');
        $adminEmail = env('TWSD_INITIAL_ORG_ADMIN_EMAIL');

        if (empty($adminEmail)) {
            throw new \RuntimeException('TWSD_INITIAL_ORG_ADMIN_EMAIL must be configured in environment before seeding initial organisation.');
        }

        $password = env('TWSD_INITIAL_ORG_ADMIN_PASSWORD');
        $generated = false;
        if (empty($password)) {
            $password = Str::password(24, true, true, true, false);
            $generated = true;
        }

        DB::transaction(function () use ($orgName, $orgSlug, $adminEmail, $password, $generated): void {
            $organisation = Organisation::query()->firstOrCreate(
                ['slug' => $orgSlug],
                [
                    'name' => $orgName,
                    'status' => 'active',
                    'jit_provisioning_mode' => 'invitation_only',
                    'mfa_required' => false,
                    'session_lifetime_minutes' => 480,
                ]
            );

            OrganisationAuthMethod::query()->firstOrCreate(
                ['organisation_id' => $organisation->id, 'provider_code' => 'email_password'],
                ['is_enabled' => true, 'require_as_mfa_second_stage' => false, 'priority' => 1]
            );

            $orgAdminRole = Role::query()->firstOrCreate(
                ['scope' => 'organisation', 'organisation_id' => $organisation->id, 'slug' => 'org_admin'],
                ['name' => 'Organisation Admin', 'description' => 'Full tenant administration', 'is_system' => true]
            );

            $orgAdminRole->permissions()->sync(
                Permission::query()->where('scope', 'organisation')->pluck('id')
            );

            $adminUser = User::query()->firstOrCreate(
                ['primary_email' => strtolower(trim($adminEmail))],
                [
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'status' => 'active',
                    'is_super_admin' => false,
                ]
            );

            $adminUser->profile()->firstOrCreate(
                ['user_id' => $adminUser->id],
                ['display_name' => "{$orgName} Administrator"]
            );

            OrganisationUser::query()->firstOrCreate(
                ['organisation_id' => $organisation->id, 'user_id' => $adminUser->id],
                ['membership_status' => 'active', 'joined_at' => now()]
            );

            DB::table('user_roles')->updateOrInsert([
                'user_id' => $adminUser->id,
                'role_id' => $orgAdminRole->id,
                'organisation_id' => $organisation->id,
                'application_id' => null,
            ], [
                'uuid' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($generated && $this->command) {
                $this->command->warn("Generated one-time bootstrap Org Admin password for {$adminEmail}: {$password}");
            }
        });
    }
}
