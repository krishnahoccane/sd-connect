<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Models\OAuthClientRegistry;
use App\Models\OAuthRedirectUri;
use App\Models\SsoSession;
use App\Models\UserSession;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OIDC\Services\OidcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OidcController extends Controller
{
    public function __construct(
        private readonly OidcService $oidcService,
        private readonly AuditService $auditService
    ) {
    }

    public function discovery(): JsonResponse
    {
        return response()
            ->json($this->oidcService->discoveryDocument())
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function jwks(): JsonResponse
    {
        return response()
            ->json($this->oidcService->jwks())
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function userinfo(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->token();

        $claims = ['sub' => $user->uuid];

        if ($token && $token->can('profile') && $user->profile) {
            $claims['name'] = $user->profile->display_name;
            $claims['given_name'] = $user->profile->given_name;
            $claims['family_name'] = $user->profile->family_name;
        }

        if ($token && $token->can('email')) {
            $claims['email'] = $user->primary_email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        if ($token && $token->can('phone') && $user->primary_phone) {
            $claims['phone_number'] = $user->primary_phone;
            $claims['phone_number_verified'] = $user->phone_verified_at !== null;
        }

        return response()->json($claims);
    }

    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        $sessionToken = $request->cookie('twsd_sso_session');
        if (is_string($sessionToken) && $sessionToken !== '') {
            $ssoSession = SsoSession::query()
                ->where('session_token_hash', hash('sha256', $sessionToken))
                ->whereNull('revoked_at')
                ->first();

            if ($ssoSession) {
                $ssoSession->update(['revoked_at' => now()]);
                UserSession::query()
                    ->where('sso_session_id', $ssoSession->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                $this->auditService->record(
                    action: 'oidc.rp_initiated_logout',
                    actor: $ssoSession->user,
                    targetType: 'sso_session',
                    targetUuid: $ssoSession->uuid,
                    request: $request
                );
            }
        }

        $postLogoutUri = $request->query('post_logout_redirect_uri');
        if (is_string($postLogoutUri) && $postLogoutUri !== '') {
            $query = OAuthRedirectUri::query()
                ->where('uri_type', 'post_logout')
                ->where('uri', $postLogoutUri)
                ->where('is_active', true);

            $clientId = $request->query('client_id');
            if (is_string($clientId) && $clientId !== '') {
                $registry = OAuthClientRegistry::query()
                    ->where('passport_client_id', $clientId)
                    ->where('is_active', true)
                    ->first();

                if (!$registry) {
                    return response()->json(['error' => 'invalid_client'], 400);
                }

                $query->where('application_id', $registry->application_id);
            }

            if (!$query->exists()) {
                return response()->json(['error' => 'invalid_post_logout_redirect_uri'], 400);
            }

            $state = $request->query('state');
            $target = $state ? "{$postLogoutUri}?state=" . urlencode((string) $state) : $postLogoutUri;

            return redirect()->away($target)->withoutCookie('twsd_sso_session');
        }

        return response()->json(['logged_out' => true])->withoutCookie('twsd_sso_session');
    }
}
