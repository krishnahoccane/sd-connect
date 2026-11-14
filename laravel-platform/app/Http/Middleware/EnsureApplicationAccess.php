<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $request->attributes->get('resolved_organisation');
        $application = $request->attributes->get('resolved_application');
        $user = $request->user();

        if (!$organisation || !$application || $application->organisation_id !== $organisation->id) {
            return response()->json(['error' => 'cross_tenant_application_rejected'], 403);
        }

        if (!$application->isActive()) {
            return response()->json(['error' => 'application_suspended'], 403);
        }

        if ($user && !$user->hasActiveMembershipIn($organisation->id)) {
            return response()->json(['error' => 'application_access_denied'], 403);
        }

        $policy = $application->access_policy ?? [];
        if (($policy['mode'] ?? 'all_org_members') === 'role_restricted' && $user) {
            $allowedRoleSlugs = $policy['allowed_roles'] ?? [];
            $hasRole = \Illuminate\Support\Facades\DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $user->id)
                ->where('user_roles.organisation_id', $organisation->id)
                ->whereIn('roles.slug', $allowedRoleSlugs)
                ->exists();

            if (!$hasRole) {
                return response()->json(['error' => 'application_role_restriction_denied'], 403);
            }
        }

        return $next($request);
    }
}
