<?php

namespace App\Providers;

use App\Modules\Installation\Services\EnvFileWriter;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (
            !$this->app->runningUnitTests()
            && !file_exists(storage_path('app/twsd_installed.lock'))
            && filter_var(env('TWSD_INSTALLER_ENABLED', true), FILTER_VALIDATE_BOOLEAN)
        ) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
            ]);

            if (empty(config('app.key')) && is_writable(base_path())) {
                try {
                    $bootstrapKey = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));
                    app(EnvFileWriter::class)->updateValues(base_path('.env'), [
                        'APP_KEY' => $bootstrapKey,
                    ]);
                    config(['app.key' => $bootstrapKey]);
                } catch (Throwable) {
                    // Handled gracefully if filesystem is read-only; InstallationCheckService surfaces permissions.
                }
            }
        }

        Passport::tokensExpireIn(
            now()->addMinutes((int) config('twsd_connect.oauth.access_token_ttl_minutes', 15))
        );

        Passport::refreshTokensExpireIn(
            now()->addDays((int) config('twsd_connect.oauth.refresh_token_ttl_days', 30))
        );

        Passport::personalAccessTokensExpireIn(now()->addDays(7));

        Passport::tokensCan([
            'openid' => 'Verify your central TWSD Connect identity',
            'profile' => 'Read your name and profile attributes',
            'email' => 'Read your verified primary email address',
            'phone' => 'Read your verified phone number',
            'offline_access' => 'Maintain access via refresh token rotation',
        ]);

        Passport::setDefaultScope(['openid']);
        Passport::enablePasswordGrant(false);
    }
}
