<?php

namespace App\Modules\OAuth\Validators;

use InvalidArgumentException;

class RedirectUriValidator
{
    public function assertValidExactUri(string $uri): string
    {
        $trimmed = trim($uri);

        if ($trimmed === '' || strlen($trimmed) > 1024) {
            throw new InvalidArgumentException('Redirect URI must be between 1 and 1024 characters.');
        }

        if (str_contains($trimmed, '*')) {
            throw new InvalidArgumentException('Wildcard redirect URIs are strictly prohibited.');
        }

        if (str_contains($trimmed, '#')) {
            throw new InvalidArgumentException('Redirect URIs must not contain URL fragments (#).');
        }

        $parsed = parse_url($trimmed);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new InvalidArgumentException('Redirect URI must be a valid absolute URI.');
        }

        $scheme = strtolower($parsed['scheme']);
        $host = strtolower($parsed['host']);

        $isLoopback = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            throw new InvalidArgumentException('Redirect URIs must use HTTPS (HTTP allowed only for localhost/127.0.0.1).');
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            throw new InvalidArgumentException('Redirect URIs must not contain embedded credentials.');
        }

        return $trimmed;
    }
}
