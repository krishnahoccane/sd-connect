<?php

namespace App\Modules\Audit\Services;

use App\Models\AuditLog;
use App\Models\ConfigurationHistory;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'client_secret',
        'secret',
        'otp',
        'code',
        'encrypted_credentials',
        'encrypted_transport_secrets',
        'encrypted_private_key_pem',
        'smtp_password',
        'authkey',
        'appkey',
        'access_token',
        'refresh_token',
        'id_token',
    ];

    public function record(
        string $action,
        ?User $actor = null,
        ?int $organisationId = null,
        ?string $targetType = null,
        ?string $targetUuid = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?Request $request = null,
        ?string $actorRole = null
    ): AuditLog {
        return AuditLog::create([
            'organisation_id' => $organisationId,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actorRole ?? ($actor?->is_super_admin ? 'super_admin' : 'user'),
            'action' => $action,
            'target_type' => $targetType,
            'target_uuid' => $targetUuid,
            'before_state' => $beforeState ? $this->sanitize($beforeState) : null,
            'after_state' => $afterState ? $this->sanitize($afterState) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 512) : null,
            'created_at' => now(),
        ]);
    }

    public function recordSecurityEvent(
        string $eventType,
        string $severity = 'info',
        ?int $organisationId = null,
        ?int $userId = null,
        array $context = [],
        ?Request $request = null
    ): SecurityEvent {
        return SecurityEvent::create([
            'organisation_id' => $organisationId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'severity' => $severity,
            'ip_address' => $request?->ip(),
            'context' => $this->sanitize($context),
            'occurred_at' => now(),
        ]);
    }

    public function recordConfigChange(
        string $scope,
        string $configKey,
        ?array $previousValue,
        ?array $newValue,
        ?int $changedByUserId = null,
        ?int $organisationId = null,
        ?int $applicationId = null
    ): ConfigurationHistory {
        return ConfigurationHistory::create([
            'scope' => $scope,
            'organisation_id' => $organisationId,
            'application_id' => $applicationId,
            'config_key' => $configKey,
            'previous_value' => $previousValue ? $this->sanitize($previousValue) : null,
            'new_value' => $newValue ? $this->sanitize($newValue) : null,
            'changed_by_user_id' => $changedByUserId,
            'created_at' => now(),
        ]);
    }

    public function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $clean[$key] = '[REDACTED]';
                continue;
            }
            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }
        return $clean;
    }
}
