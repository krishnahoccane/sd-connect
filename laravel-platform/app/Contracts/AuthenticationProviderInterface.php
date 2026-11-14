<?php

namespace App\Contracts;

use App\Models\Application;
use App\Models\Organisation;

interface AuthenticationProviderInterface
{
    public function providerCode(): string;

    public function supportsOrganisationCredentials(): bool;

    public function isConfigurationHealthy(?Organisation $organisation = null): bool;

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   authenticated: bool,
     *   provider_subject: string|null,
     *   email: string|null,
     *   phone: string|null,
     *   claims: array<string, mixed>,
     *   next_stage_required: string|null,
     *   error: string|null
     * }
     */
    public function authenticate(array $payload, Organisation $organisation, Application $application): array;
}
