<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\OIDC\Services\OAuthAuthorizationBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

class OAuthTokenController extends Controller
{
    public function __construct(private readonly OAuthAuthorizationBridgeService $oauthBridge)
    {
    }

    public function authorizeRequest(Request $request): JsonResponse
    {
        $registry = $request->attributes->get('oauth_registry');
        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
            'response_type' => ['required', 'in:code'],
            'scope' => ['required', 'string'],
            'state' => ['nullable', 'string', 'max:512'],
            'nonce' => ['nullable', 'string', 'max:512'],
            'code_challenge' => ['nullable', 'string', 'max:255'],
            'code_challenge_method' => ['nullable', 'in:S256'],
        ]);

        try {
            $scopes = array_values(array_filter(explode(' ', $validated['scope'])));
            $tx = $this->oauthBridge->initiateAuthorization(
                registry: $registry,
                redirectUri: $validated['redirect_uri'],
                requestedScopes: $scopes,
                state: $validated['state'] ?? null,
                nonce: $validated['nonce'] ?? null,
                codeChallenge: $validated['code_challenge'] ?? null,
                codeChallengeMethod: $validated['code_challenge_method'] ?? null
            );

            return response()->json([
                'data' => [
                    'oauth_transaction_id' => $tx->uuid,
                    'login_url' => route('auth.login', ['tx' => $tx->uuid]),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid_request', 'message' => $e->getMessage()], 400);
        }
    }

    public function token(Request $request): JsonResponse
    {
        $grantType = (string) $request->input('grant_type', '');
        if (in_array($grantType, config('twsd_connect.oauth.disabled_grant_types', ['implicit', 'password']), true)) {
            return response()->json(['error' => 'unsupported_grant_type'], 400);
        }

        if ($grantType === 'refresh_token') {
            $validated = $request->validate([
                'client_id' => ['required', 'string'],
                'client_secret' => ['nullable', 'string'],
                'refresh_token' => ['required', 'string'],
            ]);

            try {
                $tokens = $this->oauthBridge->rotateRefreshToken(
                    clientId: $validated['client_id'],
                    clientSecret: $validated['client_secret'] ?? null,
                    refreshToken: $validated['refresh_token'],
                    request: $request
                );

                return response()->json($tokens);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 400);
            }
        }

        if ($grantType !== 'authorization_code') {
            return response()->json(['error' => 'unsupported_grant_type'], 400);
        }

        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'client_secret' => ['nullable', 'string'],
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
            'code_verifier' => ['nullable', 'string'],
        ]);

        try {
            $tokens = $this->oauthBridge->exchangeAuthorizationCode(
                clientId: $validated['client_id'],
                clientSecret: $validated['client_secret'] ?? null,
                code: $validated['code'],
                redirectUri: $validated['redirect_uri'],
                codeVerifier: $validated['code_verifier'] ?? null,
                request: $request
            );

            return response()->json($tokens);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function revoke(Request $request): JsonResponse
    {
        $tokenId = (string) $request->input('token_id', '');
        if ($tokenId !== '') {
            Token::query()->where('id', $tokenId)->update(['revoked' => true]);
        }

        return response()->json(['revoked' => true]);
    }

    public function introspect(Request $request): JsonResponse
    {
        $tokenId = (string) $request->input('token_id', '');
        $token = $tokenId !== '' ? Token::query()->find($tokenId) : null;

        if (!$token || $token->revoked || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'scope' => implode(' ', $token->scopes ?? []),
            'client_id' => $token->client_id,
            'exp' => $token->expires_at?->timestamp,
        ]);
    }
}
