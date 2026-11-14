<?php

use App\Models\OAuthTransaction;
use App\Models\OrganisationBranding;
use App\Modules\Authentication\Services\AuthenticationPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/login', function (Request $request, AuthenticationPolicyService $policyService) {
    $txUuid = (string) $request->query('tx', '');
    $tx = OAuthTransaction::query()->with(['application.organisation'])->where('uuid', $txUuid)->firstOrFail();

    $organisation = $tx->organisation;
    $application = $tx->application;
    $brandingRecord = OrganisationBranding::query()->with('publishedVersion')->where('organisation_id', $organisation->id)->first();

    return view('authentication.login', [
        'transactionId' => $tx->uuid,
        'clientId' => $tx->passport_client_id,
        'organisation' => $organisation,
        'application' => $application,
        'branding' => $brandingRecord?->publishedVersion,
        'effectivePolicy' => $policyService->resolveEffectiveMethods($organisation, $application),
    ]);
})->name('auth.login');

Route::get('/forgot-password', fn () => view('authentication.login'))->name('auth.password.forgot');
