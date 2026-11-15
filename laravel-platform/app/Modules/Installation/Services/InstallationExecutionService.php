<?php

namespace App\Modules\Installation\Services;

use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Models\OrganisationUser;
use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\OIDC\Services\OidcService;
use App\Modules\Providers\Email\Services\EmailPasswordProvider;
use Database\Seeders\AuthProviderSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class InstallationExecutionService
{
    public const LOCK_RELATIVE_PATH = 'app/twsd_installed.lock';

    public function __construct(
        private readonly EnvFileWriter $envWriter,
        private readonly OidcService $oidcService,
        private readonly EmailPasswordProvider $passwordProvider,
        private readonly AuditService $auditService
    ) {
    }

    public function isInstalled(): bool
    {
        if (file_exists(storage_path(self::LOCK_RELATIVE_PATH))) {
            return true;
        }

        if (env('TWSD_INSTALLER_ENABLED') === false || env('TWSD_INSTALLER_ENABLED') === 'false') {
            return true;
        }

        try {
            if (!empty(config('app.key')) && Schema::hasTable('users')) {
                return User::query()->where('is_super_admin', true)->exists();
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Tests an isolated PDO connection to MySQL 8 without persisting credentials yet.
     *
     * @return array{connected: bool, server_version: string|null, error: string|null}
     */
    public function testDatabaseConnection(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password
    ): array {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

            return [
                'connected' => true,
                'server_version' => $version,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'server_version' => null,
                'error' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Persists verified DB & App configuration into .env, runs migrations, generates Passport & OIDC keys, and seeds base RBAC/Providers.
     *
     * @param array<string, mixed> $dbConfig
     */
    public function configureDatabaseAndMigrate(array $dbConfig): array
    {
        $test = $this->testDatabaseConnection(
            host: (string) $dbConfig['db_host'],
            port: (int) $dbConfig['db_port'],
            database: (string) $dbConfig['db_database'],
            username: (string) $dbConfig['db_username'],
            password: (string) ($dbConfig['db_password'] ?? '')
        );

        if (!$test['connected']) {
            throw new RuntimeException($test['error'] ?? 'Unable to connect to MySQL database.');
        }

        $appKey = config('app.key');
        if (empty($appKey)) {
            $appKey = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));
        }

        $this->envWriter->updateValues(base_path('.env'), [
            'APP_URL' => (string) ($dbConfig['app_url'] ?? 'https://connect.twsd.me'),
            'APP_KEY' => $appKey,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $dbConfig['db_host'],
            'DB_PORT' => (int) $dbConfig['db_port'],
            'DB_DATABASE' => (string) $dbConfig['db_database'],
            'DB_USERNAME' => (string) $dbConfig['db_username'],
            'DB_PASSWORD' => (string) ($dbConfig['db_password'] ?? ''),
            'REDIS_HOST' => (string) ($dbConfig['redis_host'] ?? '127.0.0.1'),
            'REDIS_PORT' => (int) ($dbConfig['redis_port'] ?? 6379),
            'REDIS_PASSWORD' => (string) ($dbConfig['redis_password'] ?? ''),
        ]);

        // Refresh runtime configuration & database connection
        Config::set('app.key', $appKey);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.host', $dbConfig['db_host']);
        Config::set('database.connections.mysql.port', (int) $dbConfig['db_port']);
        Config::set('database.connections.mysql.database', $dbConfig['db_database']);
        Config::set('database.connections.mysql.username', $dbConfig['db_username']);
        Config::set('database.connections.mysql.password', $dbConfig['db_password'] ?? '');
        DB::purge('mysql');
        DB::reconnect('mysql');

        Artisan::call('migrate', ['--force' => true]);

        if (!file_exists(storage_path('oauth-private.key')) || !file_exists(storage_path('oauth-public.key'))) {
            Artisan::call('passport:keys', ['--force' => true]);
        }

        if (!\App\Models\SigningKeyMetadata::query()->where('status', 'active')->exists()) {
            $this->oidcService->generateAndActivateSigningKey();
        }

        Artisan::call('db:seed', ['--class' => AuthProviderSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        return [
            'migrated' => true,
            'mysql_version' => $test['server_version'],
        ];
    }

    /**
     * Creates the first Super Admin account, optional initial Organisation, and locks the installer.
     *
     * @return array{super_admin_uuid: string, super_admin_email: string, organisation_uuid: string|null}
     */
    public function createSuperAdminAndFinalize(
        string $name,
        string $email,
        string $password,
        ?string $orgName = null,
        ?string $orgSlug = null,
        ?Request $request = null
    ): array {
        if (!Schema::hasTable('users') || !Schema::hasTable('roles')) {
            throw new RuntimeException('Database migrations have not been executed yet.');
        }

        if (User::query()->where('is_super_admin', true)->exists()) {
            throw new RuntimeException('A Super Admin account already exists. Installation is locked.');
        }

        $normalizedEmail = strtolower(trim($email));
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid Super Admin email address is required.');
        }

        $tempOrg = new Organisation(['password_policy' => ['min_length' => 12]]);
        $this->passwordProvider->assertPasswordMeetsPolicy($password, $tempOrg);

        $result = DB::transaction(function () use ($name, $normalizedEmail, $password, $orgName, $orgSlug, $request): array {
            $user = User::create([
                'primary_email' => $normalizedEmail,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'status' => 'active',
                'is_super_admin' => true,
                'mfa_enabled' => true,
            ]);

            $user->profile()->create([
                'display_name' => trim($name),
            ]);

            $superRole = Role::query()
                ->where('scope', 'platform')
                ->where('slug', 'super_admin')
                ->firstOrFail();

            DB::table('user_roles')->insert([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'role_id' => $superRole->id,
                'organisation_id' => null,
                'application_id' => null,
                'assigned_by_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $organisation = null;
            if (!empty($orgName)) {
                $slug = Str::slug($orgSlug ?: $orgName);
                $organisation = Organisation::create([
                    'name' => trim($orgName),
                    'slug' => $slug,
                    'status' => 'active',
                    'jit_provisioning_mode' => 'invitation_only',
                    'mfa_required' => false,
                    'session_lifetime_minutes' => 480,
                ]);

                OrganisationAuthMethod::create([
                    'organisation_id' => $organisation->id,
                    'provider_code' => 'email_password',
                    'is_enabled' => true,
                    'priority' => 1,
                ]);

                OrganisationUser::create([
                    'organisation_id' => $organisation->id,
                    'user_id' => $user->id,
                    'membership_status' => 'active',
                    'joined_at' => now(),
                ]);
            }

            $this->auditService->record(
                action: 'platform.installation_completed',
                actor: $user,
                organisationId: $organisation?->id,
                targetType: 'user',
                targetUuid: $user->uuid,
                afterState: ['super_admin_email' => $normalizedEmail, 'organisation_slug' => $organisation?->slug],
                request: $request,
                actorRole: 'super_admin'
            );

            return [
                'super_admin_uuid' => $user->uuid,
                'super_admin_email' => $user->primary_email,
                'organisation_uuid' => $organisation?->uuid,
            ];
        });

        $lockPayload = json_encode([
            'installed_at' => now()->toIso8601String(),
            'super_admin_uuid' => $result['super_admin_uuid'],
            'organisation_uuid' => $result['organisation_uuid'],
        ], JSON_PRETTY_PRINT);

        file_put_contents(storage_path(self::LOCK_RELATIVE_PATH), (string) $lockPayload, LOCK_EX);

        if (file_exists(base_path('.env'))) {
            $this->envWriter->updateValues(base_path('.env'), [
                'TWSD_INSTALLER_ENABLED' => false,
            ]);
        }

        return $result;
    }
}
