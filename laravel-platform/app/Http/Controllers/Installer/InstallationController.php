<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Installation\Services\InstallationCheckService;
use App\Modules\Installation\Services\InstallationExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class InstallationController extends Controller
{
    public function __construct(
        private readonly InstallationCheckService $checkService,
        private readonly InstallationExecutionService $executionService
    ) {
    }

    public function index(): View
    {
        $checks = $this->checkService->inspect();
        $configuredHost = (string) config('database.connections.mysql.host', 'localhost');
        if ($configuredHost === '' || $configuredHost === 'mysql') {
            $configuredHost = 'localhost';
        }

        return view('installer.wizard', [
            'checks' => $checks,
            'defaults' => [
                'app_url' => url('/'),
                'db_host' => $configuredHost,
                'db_port' => config('database.connections.mysql.port', 3306),
                'db_socket' => config('database.connections.mysql.unix_socket', ''),
                'db_database' => config('database.connections.mysql.database', 'twsd_connect'),
                'db_username' => config('database.connections.mysql.username', 'twsd_user'),
                'redis_host' => env('REDIS_HOST', '127.0.0.1'),
                'redis_port' => env('REDIS_PORT', 6379),
            ],
        ]);
    }

    public function checkRequirements(): JsonResponse
    {
        return response()->json(['data' => $this->checkService->inspect()]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_socket' => ['nullable', 'string', 'max:255'],
            'db_database' => ['required', 'string', 'max:128'],
            'db_username' => ['required', 'string', 'max:128'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->executionService->testDatabaseConnection(
            host: $validated['db_host'],
            port: (int) $validated['db_port'],
            database: $validated['db_database'],
            username: $validated['db_username'],
            password: (string) ($validated['db_password'] ?? ''),
            socket: $validated['db_socket'] ?? null
        );

        return response()->json(['data' => $result], $result['connected'] ? 200 : 422);
    }

    public function configureAndMigrate(Request $request): JsonResponse
    {
        $checks = $this->checkService->inspect();
        if (!$checks['ready']) {
            return response()->json([
                'error' => 'requirements_not_met',
                'message' => 'Resolve all failing PHP, extension, and directory permission checks before migrating.',
            ], 422);
        }

        $validated = $request->validate([
            'app_url' => ['required', 'url', 'max:255'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_socket' => ['nullable', 'string', 'max:255'],
            'db_database' => ['required', 'string', 'max:128'],
            'db_username' => ['required', 'string', 'max:128'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'redis_host' => ['nullable', 'string', 'max:255'],
            'redis_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'redis_password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->executionService->configureDatabaseAndMigrate($validated);
            return response()->json(['data' => $result]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'migration_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function createSuperAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'organisation_name' => ['nullable', 'string', 'max:160'],
            'organisation_slug' => ['nullable', 'string', 'alpha_dash', 'max:100'],
        ]);

        try {
            $created = $this->executionService->createSuperAdminAndFinalize(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
                orgName: $validated['organisation_name'] ?? null,
                orgSlug: $validated['organisation_slug'] ?? null,
                request: $request
            );

            $user = User::query()->where('uuid', $created['super_admin_uuid'])->first();
            if ($user && $request->hasSession()) {
                Auth::guard('web')->login($user);
                $request->session()->regenerate();
            }

            return response()->json([
                'data' => $created,
                'message' => 'TWSD Connect installation completed and installer locked.',
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'super_admin_creation_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
