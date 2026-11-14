<?php

namespace App\Console\Commands;

use App\Modules\OIDC\Services\OidcService;
use Illuminate\Console\Command;

class RotateOidcSigningKeyCommand extends Command
{
    protected $signature = 'twsd:oidc:rotate-key {--initial : Generate the initial active signing key only if none exists}';
    protected $description = 'Generate and activate a new RS256 OIDC signing key, marking previous keys as retiring.';

    public function handle(OidcService $oidcService): int
    {
        if ($this->option('initial') && \App\Models\SigningKeyMetadata::query()->where('status', 'active')->exists()) {
            $this->info('An active OIDC signing key already exists.');
            return self::SUCCESS;
        }

        $key = $oidcService->generateAndActivateSigningKey();
        $this->info("Activated new OIDC RS256 signing key [{$key->kid}].");
        return self::SUCCESS;
    }
}
