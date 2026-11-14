<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isGloballyActive()) {
            return response()->json([
                'error' => 'account_suspended',
                'message' => 'Central user account is suspended or locked.',
            ], 403);
        }

        $organisation = $request->attributes->get('resolved_organisation');
        if ($organisation && !$user->hasActiveMembershipIn($organisation->id)) {
            return response()->json([
                'error' => 'membership_inactive',
                'message' => 'User does not hold an active membership in this organisation.',
            ], 403);
        }

        return $next($request);
    }
}
