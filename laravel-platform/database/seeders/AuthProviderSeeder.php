<?php

namespace Database\Seeders;

use App\Models\AuthProvider;
use Illuminate\Database\Seeder;

class AuthProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'code' => 'email_password',
                'name' => 'Email & Password',
                'category' => 'password',
                'is_globally_enabled' => true,
                'supports_org_credentials' => false,
                'requires_external_health' => false,
                'health_status' => 'healthy',
                'default_policy' => ['min_length' => 12, 'hash' => 'argon2id'],
            ],
            [
                'code' => 'email_otp',
                'name' => 'Email One-Time Password',
                'category' => 'otp',
                'is_globally_enabled' => true,
                'supports_org_credentials' => true,
                'requires_external_health' => true,
                'health_status' => 'unconfigured',
                'default_policy' => ['digits' => 6, 'expiry_seconds' => 300, 'cooldown_seconds' => 60],
            ],
            [
                'code' => 'firebase_sms',
                'name' => 'Firebase SMS OTP',
                'category' => 'otp',
                'is_globally_enabled' => false,
                'supports_org_credentials' => true,
                'requires_external_health' => true,
                'health_status' => 'unconfigured',
                'default_policy' => ['verification' => 'firebase_admin_id_token'],
            ],
            [
                'code' => 'whatsapp_otp',
                'name' => 'WhatsApp OTP (Webglit)',
                'category' => 'otp',
                'is_globally_enabled' => true,
                'supports_org_credentials' => true,
                'requires_external_health' => true,
                'health_status' => 'unconfigured',
                'default_policy' => ['digits' => 6, 'expiry_seconds' => 300, 'cooldown_seconds' => 60],
            ],
            [
                'code' => 'google',
                'name' => 'Google Sign-In',
                'category' => 'federated',
                'is_globally_enabled' => false,
                'supports_org_credentials' => true,
                'requires_external_health' => true,
                'health_status' => 'unconfigured',
                'default_policy' => ['use_immutable_sub' => true],
            ],
            [
                'code' => 'microsoft',
                'name' => 'Microsoft Entra ID Sign-In',
                'category' => 'federated',
                'is_globally_enabled' => false,
                'supports_org_credentials' => true,
                'requires_external_health' => true,
                'health_status' => 'unconfigured',
                'default_policy' => ['use_immutable_oid' => true],
            ],
        ];

        foreach ($providers as $provider) {
            AuthProvider::query()->updateOrCreate(
                ['code' => $provider['code']],
                $provider
            );
        }
    }
}
