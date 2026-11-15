<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Installation\Services\EnvFileWriter;
use App\Modules\Installation\Services\InstallationCheckService;
use App\Modules\Installation\Services\InstallationExecutionService;
use Database\Seeders\AuthProviderSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class InstallationWizardTest extends TestCase
{
    use RefreshDatabase;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockPath = storage_path(InstallationExecutionService::LOCK_RELATIVE_PATH);
        if (file_exists($this->lockPath)) {
            @unlink($this->lockPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->lockPath)) {
            @unlink($this->lockPath);
        }
        parent::tearDown();
    }

    public function test_installation_requirements_page_and_json_inspection(): void
    {
        $response = $this->get('/install');
        $response->assertOk();
        $response->assertSee('Server Requirements Detection');
        $response->assertSee('Create Platform Super Admin');

        $json = $this->getJson('/install/requirements');
        $json->assertOk()->assertJsonStructure([
            'data' => [
                'ready',
                'php' => ['current', 'minimum', 'passed'],
                'extensions',
                'permissions',
                'security',
            ],
        ]);

        $inspect = app(InstallationCheckService::class)->inspect();
        $this->assertArrayHasKey('ready', $inspect);
        $this->assertNotEmpty($inspect['extensions']);
    }

    public function test_env_file_writer_rejects_invalid_keys_and_newline_injection(): void
    {
        $writer = new EnvFileWriter();
        $tmpEnv = sys_get_temp_dir() . '/twsd_test_' . uniqid() . '.env';
        file_put_contents($tmpEnv, "APP_NAME=TWSD\nDB_HOST=localhost\n");

        try {
            $writer->updateValues($tmpEnv, [
                'DB_HOST' => 'mysql.internal',
                'DB_PASSWORD' => 'p@ss"word$123',
            ]);

            $content = (string) file_get_contents($tmpEnv);
            $this->assertStringContainsString('DB_HOST=mysql.internal', $content);
            $this->assertStringContainsString('DB_PASSWORD="p@ss\\"word\\$123"', $content);

            $this->expectException(InvalidArgumentException::class);
            $writer->updateValues($tmpEnv, ['DB_HOST' => "mysql\nAPP_DEBUG=true"]);
        } finally {
            @unlink($tmpEnv);
        }
    }

    public function test_env_file_writer_rejects_malformed_key_names(): void
    {
        $writer = new EnvFileWriter();
        $tmpEnv = sys_get_temp_dir() . '/twsd_test_key_' . uniqid() . '.env';
        file_put_contents($tmpEnv, "APP_NAME=TWSD\n");

        try {
            $this->expectException(InvalidArgumentException::class);
            $writer->updateValues($tmpEnv, ['bad-key' => 'value']);
        } finally {
            @unlink($tmpEnv);
        }
    }

    public function test_database_connection_validation_rejects_missing_fields(): void
    {
        $this->postJson('/install/database/test', [
            'db_host' => '',
            'db_port' => 99999,
        ])->assertStatus(422);
    }

    public function test_super_admin_creation_rejects_weak_password_and_locks_installer_on_success(): void
    {
        $this->seed(AuthProviderSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        // Weak password rejected
        $this->postJson('/install/super-admin', [
            'name' => 'Root Admin',
            'email' => 'root@connect.twsd.me',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ])->assertStatus(422);

        // Strong password creates Super Admin + initial org + audit + lockfile
        $res = $this->postJson('/install/super-admin', [
            'name' => 'Root Admin',
            'email' => 'ROOT@connect.twsd.me',
            'password' => 'Twsd@Enterprise#2026!',
            'password_confirmation' => 'Twsd@Enterprise#2026!',
            'organisation_name' => 'TWSD Enterprise',
            'organisation_slug' => 'twsd-enterprise',
        ]);

        $res->assertStatus(201)->assertJsonPath('data.super_admin_email', 'root@connect.twsd.me');

        $user = User::query()->where('primary_email', 'root@connect.twsd.me')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue(Hash::check('Twsd@Enterprise#2026!', $user->password));
        $this->assertDatabaseHas('organisations', ['slug' => 'twsd-enterprise']);
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id]);
        $this->assertTrue(
            AuditLog::query()->where('action', 'platform.installation_completed')->exists()
        );
        $this->assertFileExists($this->lockPath);

        // EnsureNotInstalled blocks all installer routes after completion
        $this->get('/install')->assertStatus(403);
        $this->getJson('/install/requirements')->assertStatus(403);
        $this->postJson('/install/database/test', [
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'twsd_connect',
            'db_username' => 'root',
        ])->assertStatus(403);
        $this->postJson('/install/super-admin', [
            'name' => 'Second Admin',
            'email' => 'second@connect.twsd.me',
            'password' => 'Twsd@Enterprise#2026!',
            'password_confirmation' => 'Twsd@Enterprise#2026!',
        ])->assertStatus(403);
    }
}
