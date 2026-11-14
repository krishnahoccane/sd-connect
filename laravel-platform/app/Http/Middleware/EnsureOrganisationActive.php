<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $request->attributes->get('resolved_organisation');
        if (!$organisation || !$organisation->isActive()) {
            return response()->json([
                'error' => 'organisation_suspended',
                'message' => 'Organisation is suspended or archived.',
            ], 403);
        }

        return $next($request);
    }
}
