<?php

namespace App\Modules\Installation\Services;

use InvalidArgumentException;
use RuntimeException;

class EnvFileWriter
{
    /**
     * Safely updates or inserts key/value pairs in .env without permitting newline/key injection.
     *
     * @param array<string, string|int|bool|null> $values
     */
    public function updateValues(string $envPath, array $values): void
    {
        $directory = dirname($envPath);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Target environment directory is not writable.');
        }

        if (!file_exists($envPath)) {
            $examplePath = $directory . DIRECTORY_SEPARATOR . '.env.example';
            if (file_exists($examplePath)) {
                copy($examplePath, $envPath);
            } else {
                file_put_contents($envPath, '', LOCK_EX);
            }
        }

        $content = (string) file_get_contents($envPath);

        foreach ($values as $key => $rawValue) {
            if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
                throw new InvalidArgumentException("Invalid environment key [{$key}].");
            }

            $stringValue = $rawValue === null ? '' : (is_bool($rawValue) ? ($rawValue ? 'true' : 'false') : (string) $rawValue);

            if (str_contains($stringValue, "\n") || str_contains($stringValue, "\r") || str_contains($stringValue, "\0")) {
                throw new InvalidArgumentException("Environment value for [{$key}] contains prohibited control characters.");
            }

            $formatted = $this->formatEnvValue($stringValue);
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, "{$key}={$formatted}", $content, 1);
            } else {
                $content = rtrim($content, "\r\n") . PHP_EOL . "{$key}={$formatted}" . PHP_EOL;
            }
        }

        if (file_put_contents($envPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('Failed to persist .env configuration.');
        }
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_.\-:\/+=]+$/', $value)) {
            return $value;
        }

        $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
        return "\"{$escaped}\"";
    }
}
