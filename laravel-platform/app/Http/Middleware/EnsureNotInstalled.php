<?php

namespace App\Http\Middleware;

use App\Modules\Installation\Services\InstallationExecutionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function __construct(private readonly InstallationExecutionService $installer)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->isInstalled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'already_installed',
                    'message' => 'TWSD Connect is already installed and the installer is permanently locked.',
                ], 403);
            }

            abort(403, 'TWSD Connect is already installed. Delete storage/app/twsd_installed.lock only for controlled disaster recovery.');
        }

        return $next($request);
    }
}
