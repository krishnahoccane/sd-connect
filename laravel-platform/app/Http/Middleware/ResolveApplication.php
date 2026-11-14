<?php

namespace App\Http\Middleware;

use App\Models\OAuthClientRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApplication
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->input('client_id') ?? $request->route('client_id');

        if (!is_string($clientId) || $clientId === '') {
            return response()->json(['error' => 'invalid_client', 'message' => 'Missing OAuth client_id.'], 400);
        }

        $registry = OAuthClientRegistry::query()
            ->with(['application.organisation'])
            ->where('passport_client_id', $clientId)
            ->where('is_active', true)
            ->first();

        if (!$registry || !$registry->application || !$registry->application->isActive()) {
            return response()->json(['error' => 'unauthorized_client', 'message' => 'Application or tenant is inactive.'], 403);
        }

        $request->attributes->set('oauth_registry', $registry);
        $request->attributes->set('resolved_application', $registry->application);
        $request->attributes->set('resolved_organisation', $registry->application->organisation);

        return $next($request);
    }
}
