<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganisationPermission
{
    public function handle(Request $request, Closure $next, string $permissionSlug): Response
    {
        $user = $request->user();
        $organisation = $request->attributes->get('resolved_organisation');

        if (!$user || !$user->isGloballyActive()) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (!$organisation || !$user->hasActiveMembershipIn($organisation->id)) {
            return response()->json(['error' => 'forbidden_tenant_access'], 403);
        }

        $hasPermission = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.organisation_id', $organisation->id)
            ->where('permissions.slug', $permissionSlug)
            ->exists();

        if (!$hasPermission) {
            return response()->json(['error' => 'insufficient_organisation_permissions'], 403);
        }

        return $next($request);
    }
}
