<?php

namespace App\Modules\OTP\Services;

class OtpHasher
{
    public function hash(string $plainOtp, string $transactionUuid): string
    {
        $key = (string) config('app.key');
        return hash_hmac('sha256', "{$transactionUuid}|{$plainOtp}", $key);
    }

    public function verify(string $plainOtp, string $transactionUuid, string $storedHash): bool
    {
        $computed = $this->hash($plainOtp, $transactionUuid);
        return hash_equals($storedHash, $computed);
    }

    public function hashRecipient(string $recipient): string
    {
        return hash('sha256', strtolower(trim($recipient)));
    }

    public function maskEmail(string $email): string
    {
        $parts = explode('@', trim($email));
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible . str_repeat('*', max(2, mb_strlen($local) - 2)) . '@' . $parts[1];
    }
}
