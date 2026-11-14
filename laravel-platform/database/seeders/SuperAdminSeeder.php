<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('TWSD_SUPER_ADMIN_EMAIL');
        if (empty($email)) {
            throw new \RuntimeException('TWSD_SUPER_ADMIN_EMAIL must be configured in environment before seeding Super Admin.');
        }

        $password = env('TWSD_SUPER_ADMIN_PASSWORD');
        $generated = false;
        if (empty($password)) {
            $password = Str::password(24, true, true, true, false);
            $generated = true;
        }

        DB::transaction(function () use ($email, $password, $generated): void {
            $user = User::query()->firstOrCreate(
                ['primary_email' => strtolower(trim($email))],
                [
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'status' => 'active',
                    'is_super_admin' => true,
                    'mfa_enabled' => true,
                ]
            );

            $user->profile()->firstOrCreate(
                ['user_id' => $user->id],
                ['display_name' => env('TWSD_SUPER_ADMIN_NAME', 'Platform Super Admin')]
            );

            $superRole = Role::query()->where('scope', 'platform')->where('slug', 'super_admin')->firstOrFail();

            DB::table('user_roles')->updateOrInsert([
                'user_id' => $user->id,
                'role_id' => $superRole->id,
                'organisation_id' => null,
                'application_id' => null,
            ], [
                'uuid' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($generated && $this->command) {
                $this->command->warn("Generated one-time bootstrap Super Admin password for {$email}: {$password}");
                $this->command->warn('Store this securely in a password manager and rotate immediately on first login.');
            }
        });
    }
}
