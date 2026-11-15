<?php

use App\Http\Controllers\Api\V1\AuthenticationController;
use App\Http\Controllers\Api\V1\OAuthTokenController;
use App\Http\Controllers\Api\V1\Organisation\ApplicationController;
use App\Http\Controllers\Api\V1\Organisation\AuthenticationAdminController;
use App\Http\Controllers\Api\V1\SuperAdmin\GlobalAuthAdminController;
use App\Http\Controllers\Oidc\OidcController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureOrganisationActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RequireOrganisationPermission;
use App\Http\Middleware\ResolveApplication;
use App\Http\Middleware\ResolveOrganisation;
use Illuminate\Support\Facades\Route;

// OIDC Core & Discovery Endpoints
Route::get('/.well-known/openid-configuration', [OidcController::class, 'discovery'])
    ->name('oidc.discovery');

Route::get('/.well-known/jwks.json', [OidcController::class, 'jwks'])
    ->name('oidc.jwks');

Route::prefix('oauth')->group(function (): void {
    Route::get('/authorize', [OAuthTokenController::class, 'authorizeRequest'])
        ->middleware([ResolveApplication::class, 'throttle:60,1'])
        ->name('oauth.authorize');

    Route::post('/token', [OAuthTokenController::class, 'token'])
        ->middleware(['throttle:60,1'])
        ->name('oauth.token');

    Route::get('/userinfo', [OidcController::class, 'userinfo'])
        ->middleware(['auth:api', EnsureActiveUser::class])
        ->name('oidc.userinfo');

    Route::post('/revoke', [OAuthTokenController::class, 'revoke'])
        ->middleware(['throttle:60,1'])
        ->name('oauth.revoke');

    Route::post('/introspect', [OAuthTokenController::class, 'introspect'])
        ->middleware(['throttle:60,1'])
        ->name('oauth.introspect');

    Route::get('/logout', [OidcController::class, 'logout'])
        ->name('oidc.logout');
});

// Versioned REST APIs
Route::prefix('api/v1')->group(function (): void {
    // Public Authentication Endpoints (strictly resolved server-side via client_id)
    Route::prefix('auth')->middleware([ResolveApplication::class, 'throttle:30,1'])->group(function (): void {
        Route::post('/methods', [AuthenticationController::class, 'methods']);
        Route::post('/email/password', [AuthenticationController::class, 'emailPassword']);
        Route::post('/email/otp/send', [AuthenticationController::class, 'sendEmailOtp']);
        Route::post('/email/otp/verify', [AuthenticationController::class, 'verifyEmailOtp']);
        Route::post('/whatsapp/otp/send', [AuthenticationController::class, 'sendWhatsAppOtp']);
        Route::post('/whatsapp/otp/verify', [AuthenticationController::class, 'verifyWhatsAppOtp']);
        Route::post('/password/forgot', [AuthenticationController::class, 'forgotPassword']);
        Route::post('/password/reset', [AuthenticationController::class, 'resetPassword']);
    });

    Route::post('/auth/logout', [AuthenticationController::class, 'logout']);

    // Super Admin Endpoints
    Route::prefix('super-admin')->middleware(['auth:api', EnsureActiveUser::class, EnsureSuperAdmin::class])->group(function (): void {
        Route::get('/dashboard', [GlobalAuthAdminController::class, 'dashboard']);
        Route::put('/providers/{providerCode}', [GlobalAuthAdminController::class, 'toggleGlobalProvider']);
        Route::put('/providers/email/platform-default', [GlobalAuthAdminController::class, 'updatePlatformEmail']);
        Route::post('/providers/email/platform-default/test', [GlobalAuthAdminController::class, 'testPlatformEmail']);
        Route::get('/security/events', [GlobalAuthAdminController::class, 'securityEvents']);
    });

    // Organisation Admin Endpoints (strict tenant isolation via ResolveOrganisation + RequireOrganisationPermission)
    Route::prefix('organisation/{organisation}')
        ->middleware(['auth:api', EnsureActiveUser::class, ResolveOrganisation::class, EnsureOrganisationActive::class])
        ->group(function (): void {
            Route::prefix('applications')->middleware(RequireOrganisationPermission::class . ':org.applications.manage')->group(function (): void {
                Route::get('/', [ApplicationController::class, 'index']);
                Route::post('/', [ApplicationController::class, 'store']);
                Route::get('/{application}', [ApplicationController::class, 'show']);
                Route::put('/{application}', [ApplicationController::class, 'update']);
                Route::post('/{application}/suspend', [ApplicationController::class, 'suspend']);
                Route::post('/{application}/activate', [ApplicationController::class, 'activate']);
                Route::post('/{application}/archive', [ApplicationController::class, 'archive']);
                Route::post('/{application}/rotate-secret', [ApplicationController::class, 'rotateSecret']);
                Route::get('/{application}/audit-logs', [ApplicationController::class, 'auditHistory']);
            });

            Route::prefix('authentication')->middleware(RequireOrganisationPermission::class . ':org.auth_methods.manage')->group(function (): void {
                Route::get('/', [AuthenticationAdminController::class, 'index']);
                Route::put('/methods', [AuthenticationAdminController::class, 'updateMethods']);
                Route::put('/policies', [AuthenticationAdminController::class, 'updatePolicies']);
                Route::put('/email', [AuthenticationAdminController::class, 'updateEmailConfiguration']);
                Route::post('/email/test', [AuthenticationAdminController::class, 'testEmailConfiguration']);
                Route::get('/logs', [AuthenticationAdminController::class, 'loginLogs']);
            });
        });
});
