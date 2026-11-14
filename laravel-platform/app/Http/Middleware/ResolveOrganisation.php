<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganisation
{
    public function handle(Request $request, Closure $next): Response
    {
        $orgParam = $request->route('organisation');
        $organisation = $orgParam instanceof Organisation
            ? $orgParam
            : Organisation::query()->where('uuid', (string) $orgParam)->first();

        if (!$organisation) {
            return response()->json(['error' => 'organisation_not_found'], 404);
        }

        $user = $request->user();
        if ($user && !$user->is_super_admin) {
            if (!$user->hasActiveMembershipIn($organisation->id)) {
                return response()->json(['error' => 'forbidden_tenant_access'], 403);
            }
        }

        $request->attributes->set('resolved_organisation', $organisation);

        return $next($request);
    }
}
