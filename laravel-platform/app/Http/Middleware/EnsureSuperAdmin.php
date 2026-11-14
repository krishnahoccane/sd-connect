<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next, ?string $permissionSlug = null): Response
    {
        $user = $request->user();

        if (!$user || !$user->isGloballyActive() || !$user->is_super_admin) {
            return response()->json(['error' => 'forbidden_super_admin_required'], 403);
        }

        if ($permissionSlug !== null) {
            $hasPerm = \Illuminate\Support\Facades\DB::table('user_roles')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('user_roles.user_id', $user->id)
                ->whereNull('user_roles.organisation_id')
                ->where('permissions.slug', $permissionSlug)
                ->exists();

            if (!$hasPerm) {
                return response()->json(['error' => 'insufficient_platform_permissions'], 403);
            }
        }

        return $next($request);
    }
}
