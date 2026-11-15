<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
