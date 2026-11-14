<?php

namespace Tests\Feature;

use App\Models\AuthProvider;
use App\Models\Organisation;
use App\Models\OrganisationAuthMethod;
use App\Modules\Applications\Services\ApplicationService;
use App\Modules\OAuth\Services\OAuthClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Passport\Client;
use Tests\TestCase;

class ApplicationLifecycleAndOAuthClientTest extends TestCase
{
    use RefreshDatabase;

    private function createOrgWithPasswordProvider(string $slug = 'acme'): Organisation
    {
        AuthProvider::query()->firstOrCreate(
            ['code' => 'email_password'],
            ['name' => 'Email & Password', 'category' => 'password', 'is_globally_enabled' => true, 'health_status' => 'healthy']
        );

        $org = Organisation::create(['name' => strtoupper($slug), 'slug' => $slug, 'status' => 'active']);
        OrganisationAuthMethod::create(['organisation_id' => $org->id, 'provider_code' => 'email_password', 'is_enabled' => true]);

        return $org;
    }

    public function test_application_crud_suspend_activate_archive_and_one_time_secret(): void
    {
        $org = $this->createOrgWithPasswordProvider();
        $service = app(ApplicationService::class);

        $created = $service->createApplication($org, [
            'name' => 'Billing Portal',
            'app_url' => 'https://billing.acme.example.com',
            'application_type' => 'web_confidential',
            'redirect_uris' => ['https://billing.acme.example.com/callback'],
            'auth_methods' => ['email_password'],
        ]);

        $app = $created['application'];
        $this->assertNotNull($created['plain_secret_once']);
        $this->assertSame('acme-billing-portal.connect.twsd.me', $app->twsd_subdomain);
        $this->assertDatabaseHas('oauth_client_registry', ['application_id' => $app->id, 'passport_client_id' => $created['client_id']]);

        // Secret is never stored in plaintext in API model serialization
        $serialized = json_encode($app->toArray());
        $this->assertStringNotContainsString($created['plain_secret_once'], (string) $serialized);

        $updated = $service->updateApplication($org, $app, ['name' => 'Billing Console']);
        $this->assertSame('Billing Console', $updated->name);

        $service->suspendApplication($org, $updated);
        $this->assertSame('suspended', $updated->fresh()->status);

        $service->activateApplication($org, $updated);
        $this->assertSame('active', $updated->fresh()->status);

        $service->archiveApplication($org, $updated);
        $this->assertNotNull($updated->fresh()->archived_at);
        $this->assertTrue((bool) Client::query()->find($created['client_id'])?->revoked);
    }

    public function test_secret_rotation_invalidates_previous_secret_and_returns_new_secret_once(): void
    {
        $org = $this->createOrgWithPasswordProvider();
        $created = app(ApplicationService::class)->createApplication($org, [
            'name' => 'ERP App',
            'app_url' => 'https://erp.example.com',
            'application_type' => 'web_confidential',
            'redirect_uris' => ['https://erp.example.com/oauth/callback'],
        ]);

        $rotated = app(OAuthClientService::class)->rotateSecret($created['application']);
        $this->assertNotSame($created['plain_secret_once'], $rotated['plain_secret_once']);

        $client = Client::query()->findOrFail($created['client_id']);
        $this->assertFalse(password_verify($created['plain_secret_once'], (string) $client->secret));
        $this->assertTrue(password_verify($rotated['plain_secret_once'], (string) $client->secret));
    }

    public function test_wildcard_redirect_uri_is_rejected(): void
    {
        $org = $this->createOrgWithPasswordProvider();

        $this->expectException(InvalidArgumentException::class);
        app(ApplicationService::class)->createApplication($org, [
            'name' => 'Wildcard Test',
            'app_url' => 'https://app.example.com',
            'application_type' => 'spa_public',
            'redirect_uris' => ['https://*.example.com/callback'],
        ]);
    }

    public function test_exact_redirect_uri_matching_blocks_unregistered_subpaths(): void
    {
        $org = $this->createOrgWithPasswordProvider();
        $created = app(ApplicationService::class)->createApplication($org, [
            'name' => 'CRM',
            'app_url' => 'https://crm.example.com',
            'application_type' => 'spa_public',
            'redirect_uris' => ['https://crm.example.com/auth/callback'],
        ]);

        $oauthService = app(OAuthClientService::class);
        $this->assertTrue($oauthService->isExactRedirectUriRegistered($created['application'], 'https://crm.example.com/auth/callback'));
        $this->assertFalse($oauthService->isExactRedirectUriRegistered($created['application'], 'https://crm.example.com/auth/callback?extra=1'));
        $this->assertFalse($oauthService->isExactRedirectUriRegistered($created['application'], 'https://crm.example.com/auth/other'));
    }
}
