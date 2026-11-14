<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolveOrganisation;
use App\Models\Application;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\LoginAttempt;
use App\Models\OrganisationAuthMethod;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('super-admin')->middleware(['auth', EnsureActiveUser::class, EnsureSuperAdmin::class])->group(function (): void {
    Route::get('/auth-settings', function () {
        return view('super-admin.auth-settings', [
            'providers' => AuthProvider::query()->orderBy('id')->get(),
            'platformEmail' => EmailConfiguration::query()->where('scope', 'global')->whereNull('organisation_id')->first(),
            'securityEvents' => SecurityEvent::query()->orderByDesc('occurred_at')->limit(20)->get(),
        ]);
    })->name('super-admin.auth-settings');
});

Route::prefix('org/{organisation}')
    ->middleware(['auth', EnsureActiveUser::class, ResolveOrganisation::class])
    ->group(function (): void {
        Route::get('/applications', function (Request $request) {
            $organisation = $request->attributes->get('resolved_organisation');
            return view('organisation-admin.applications', [
                'organisation' => $organisation,
                'applications' => Application::query()->with(['oauthClientRegistry', 'redirectUris'])->where('organisation_id', $organisation->id)->get(),
            ]);
        })->name('org.applications');

        Route::get('/email-settings', function (Request $request) {
            $organisation = $request->attributes->get('resolved_organisation');
            return view('organisation-admin.email-settings', [
                'organisation' => $organisation,
                'providers' => AuthProvider::query()->get(),
                'orgMethods' => OrganisationAuthMethod::query()->where('organisation_id', $organisation->id)->get()->keyBy('provider_code'),
                'emailConfig' => EmailConfiguration::query()->where('scope', 'organisation')->where('organisation_id', $organisation->id)->first(),
                'recentLogs' => LoginAttempt::query()->where('organisation_id', $organisation->id)->orderByDesc('attempted_at')->limit(25)->get(),
            ]);
        })->name('org.email-settings');
    });
