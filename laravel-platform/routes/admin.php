<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Api\V1\Organisation\ApplicationController;
use App\Http\Controllers\Api\V1\Organisation\AuthenticationAdminController;
use App\Http\Controllers\Api\V1\SuperAdmin\GlobalAuthAdminController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureOrganisationActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolveOrganisation;
use App\Models\Application;
use App\Models\AuthProvider;
use App\Models\EmailConfiguration;
use App\Models\LoginAttempt;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\ProviderConfiguration;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Admin Console Session Authentication (satisfies Laravel's default route('login') redirect)
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:15,1')->name('admin.login.submit');
Route::post('/admin/login/otp/send', [AdminAuthController::class, 'sendEmailOtp'])->middleware('throttle:10,1')->name('admin.login.otp.send');
Route::post('/admin/login/otp/verify', [AdminAuthController::class, 'verifyEmailOtp'])->middleware('throttle:15,1')->name('admin.login.otp.verify');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth')->name('admin.logout');

Route::prefix('super-admin')->middleware(['auth', EnsureActiveUser::class, EnsureSuperAdmin::class])->group(function (): void {
    Route::get('/auth-settings', function () {
        return view('super-admin.auth-settings', [
            'providers' => AuthProvider::query()->orderBy('id')->get(),
            'globalConfigs' => ProviderConfiguration::query()
                ->where('scope', 'global')
                ->whereNull('organisation_id')
                ->get()
                ->keyBy('provider_code'),
            'platformEmail' => EmailConfiguration::query()->where('scope', 'global')->whereNull('organisation_id')->first(),
            'organisations' => Organisation::query()->orderBy('name')->get(),
            'securityEvents' => SecurityEvent::query()->orderByDesc('occurred_at')->limit(20)->get(),
        ]);
    })->name('super-admin.auth-settings');

    // Web session + CSRF-authenticated actions for the Super Admin Blade UI
    Route::post('/organisations', [GlobalAuthAdminController::class, 'createOrganisation'])
        ->name('super-admin.organisations.store');
    Route::put('/providers/email/platform-default', [GlobalAuthAdminController::class, 'updatePlatformEmail'])
        ->name('super-admin.providers.email.update');
    Route::post('/providers/email/platform-default/test', [GlobalAuthAdminController::class, 'testPlatformEmail'])
        ->name('super-admin.providers.email.test');
    Route::put('/providers/{providerCode}/config', [GlobalAuthAdminController::class, 'upsertProviderConfiguration'])
        ->name('super-admin.providers.config');
    Route::put('/providers/{providerCode}', [GlobalAuthAdminController::class, 'toggleGlobalProvider'])
        ->name('super-admin.providers.toggle');
});

Route::prefix('org/{organisation}')
    ->middleware(['auth', EnsureActiveUser::class, ResolveOrganisation::class, EnsureOrganisationActive::class])
    ->group(function (): void {
        Route::get('/applications', function (Request $request) {
            $organisation = $request->attributes->get('resolved_organisation');
            return view('organisation-admin.applications', [
                'organisation' => $organisation,
                'applications' => Application::query()
                    ->with(['oauthClientRegistry', 'redirectUris', 'authMethods'])
                    ->where('organisation_id', $organisation->id)
                    ->orderByDesc('id')
                    ->get(),
                'providers' => AuthProvider::query()->orderBy('id')->get(),
                'orgMethods' => OrganisationAuthMethod::query()
                    ->where('organisation_id', $organisation->id)
                    ->get()
                    ->keyBy('provider_code'),
            ]);
        })->name('org.applications');

        Route::post('/applications', [ApplicationController::class, 'store'])
            ->name('org.applications.store');
        Route::put('/applications/{application}', [ApplicationController::class, 'update'])
            ->name('org.applications.update');
        Route::post('/applications/{application}/rotate-secret', [ApplicationController::class, 'rotateSecret'])
            ->name('org.applications.rotate-secret');

        Route::get('/email-settings', function (Request $request) {
            $organisation = $request->attributes->get('resolved_organisation');
            return view('organisation-admin.email-settings', [
                'organisation' => $organisation,
                'providers' => AuthProvider::query()->orderBy('id')->get(),
                'orgMethods' => OrganisationAuthMethod::query()->where('organisation_id', $organisation->id)->get()->keyBy('provider_code'),
                'emailConfig' => EmailConfiguration::query()->where('scope', 'organisation')->where('organisation_id', $organisation->id)->first(),
                'recentLogs' => LoginAttempt::query()->where('organisation_id', $organisation->id)->orderByDesc('attempted_at')->limit(25)->get(),
            ]);
        })->name('org.email-settings');

        Route::put('/authentication/methods', [AuthenticationAdminController::class, 'updateMethods'])
            ->name('org.auth.methods');
        Route::put('/authentication/email', [AuthenticationAdminController::class, 'updateEmailConfiguration'])
            ->name('org.auth.email');
        Route::post('/authentication/email/test', [AuthenticationAdminController::class, 'testEmailConfiguration'])
            ->name('org.auth.email.test');
    });
