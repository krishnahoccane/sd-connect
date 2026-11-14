<?php

namespace App\Modules\Providers\Email\Services;

use App\Contracts\AuthenticationProviderInterface;
use App\Models\Application;
use App\Models\LoginAttempt;
use App\Models\Organisation;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class EmailPasswordProvider implements AuthenticationProviderInterface
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function providerCode(): string
    {
        return 'email_password';
    }

    public function supportsOrganisationCredentials(): bool
    {
        return false;
    }

    public function isConfigurationHealthy(?Organisation $organisation = null): bool
    {
        return true;
    }

    public function authenticate(array $payload, Organisation $organisation, Application $application): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $request = $payload['request'] ?? null;

        if ($email === '' || $password === '') {
            return $this->fail('Email and password are required.');
        }

        $user = User::query()->where('primary_email', $email)->first();

        if (!$user || empty($user->password)) {
            $this->logAttempt($organisation, $application, null, $email, 'failed', 'invalid_credentials', $request);
            return $this->fail('Invalid credentials.');
        }

        if ($user->status !== 'active') {
            $this->logAttempt($organisation, $application, $user, $email, 'blocked', 'central_account_suspended', $request);
            return $this->fail('Central user account is suspended.');
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $this->logAttempt($organisation, $application, $user, $email, 'blocked', 'account_locked', $request);
            return $this->fail('Account is temporarily locked due to repeated failed attempts.');
        }

        if (!Hash::check($password, $user->password)) {
            $maxAttempts = (int) config('twsd_connect.password.max_login_attempts', 5);
            $lockoutMinutes = (int) config('twsd_connect.password.lockout_minutes', 15);
            $failedCount = $user->failed_login_count + 1;

            $updates = ['failed_login_count' => $failedCount];
            if ($failedCount >= $maxAttempts) {
                $updates['locked_until'] = now()->addMinutes($lockoutMinutes);
                $this->auditService->recordSecurityEvent('auth.account_locked', 'high', $organisation->id, $user->id, ['attempts' => $failedCount], $request instanceof Request ? $request : null);
            }
            $user->update($updates);

            $this->logAttempt($organisation, $application, $user, $email, 'failed', 'invalid_password', $request);
            return $this->fail('Invalid credentials.');
        }

        if (!$user->hasActiveMembershipIn($organisation->id)) {
            $this->logAttempt($organisation, $application, $user, $email, 'blocked', 'membership_inactive', $request);
            return $this->fail('User does not have an active membership in this organisation.');
        }

        $user->update([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request instanceof Request ? $request->ip() : null,
        ]);

        $this->logAttempt($organisation, $application, $user, $email, 'success', null, $request);

        return [
            'authenticated' => true,
            'provider_subject' => $user->uuid,
            'email' => $user->primary_email,
            'phone' => $user->primary_phone,
            'claims' => ['user_id' => $user->id, 'amr' => ['pwd']],
            'next_stage_required' => null,
            'error' => null,
        ];
    }

    public function assertPasswordMeetsPolicy(string $password, Organisation $organisation): void
    {
        $orgPolicy = $organisation->password_policy ?? [];
        $global = config('twsd_connect.password');
        $minLength = max((int) ($orgPolicy['min_length'] ?? 12), (int) ($global['min_length'] ?? 12));

        if (mb_strlen($password) < $minLength) {
            throw new InvalidArgumentException("Password must be at least {$minLength} characters.");
        }
        if (($global['require_uppercase'] ?? true) && !preg_match('/[A-Z]/', $password)) {
            throw new InvalidArgumentException('Password must include at least one uppercase letter.');
        }
        if (($global['require_lowercase'] ?? true) && !preg_match('/[a-z]/', $password)) {
            throw new InvalidArgumentException('Password must include at least one lowercase letter.');
        }
        if (($global['require_number'] ?? true) && !preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('Password must include at least one number.');
        }
        if (($global['require_symbol'] ?? true) && !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new InvalidArgumentException('Password must include at least one symbol.');
        }
    }

    private function logAttempt(
        Organisation $organisation,
        Application $application,
        ?User $user,
        string $email,
        string $outcome,
        ?string $reason,
        mixed $request
    ): void {
        LoginAttempt::create([
            'organisation_id' => $organisation->id,
            'application_id' => $application->id,
            'user_id' => $user?->id,
            'identifier_masked' => substr($email, 0, 2) . '***@' . (explode('@', $email)[1] ?? ''),
            'provider_code' => 'email_password',
            'outcome' => $outcome,
            'failure_reason' => $reason,
            'ip_address' => $request instanceof Request ? $request->ip() : '0.0.0.0',
            'user_agent' => $request instanceof Request ? mb_substr((string) $request->userAgent(), 0, 512) : null,
            'attempted_at' => now(),
        ]);
    }

    private function fail(string $message): array
    {
        return [
            'authenticated' => false,
            'provider_subject' => null,
            'email' => null,
            'phone' => null,
            'claims' => [],
            'next_stage_required' => null,
            'error' => $message,
        ];
    }
}
