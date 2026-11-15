<?php

namespace App\Modules\Installation\Services;

class InstallationCheckService
{
    private const REQUIRED_EXTENSIONS = [
        'openssl',
        'pdo_mysql',
        'mbstring',
        'xml',
        'bcmath',
        'intl',
        'gmp',
        'sodium',
        'ctype',
        'fileinfo',
        'json',
        'zip',
    ];

    /**
     * Inspects PHP runtime, required extensions, writable directories, and crypto capabilities.
     *
     * @return array{
     *   ready: bool,
     *   php: array<string, mixed>,
     *   extensions: array<int, array{name: string, loaded: bool, required: bool}>,
     *   permissions: array<int, array{path: string, writable: bool}>,
     *   security: array<int, array{check: string, passed: bool, detail: string}>
     * }
     */
    public function inspect(): array
    {
        $phpOk = PHP_VERSION_ID >= 80300;

        $extensions = [];
        $allRequiredExtLoaded = true;
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            if (!$loaded) {
                $allRequiredExtLoaded = false;
            }
            $extensions[] = [
                'name' => $ext,
                'loaded' => $loaded,
                'required' => true,
            ];
        }

        // Redis extension is recommended; predis PHP client is bundled in composer.json
        $extensions[] = [
            'name' => 'redis',
            'loaded' => extension_loaded('redis') || class_exists(\Predis\Client::class),
            'required' => false,
        ];

        $pathsToCheck = [
            'storage' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'base_env_target' => file_exists(base_path('.env')) ? base_path('.env') : base_path(),
        ];

        $permissions = [];
        $allWritable = true;
        foreach ($pathsToCheck as $label => $path) {
            $writable = file_exists($path) && is_writable($path);
            if (!$writable) {
                $allWritable = false;
            }
            $permissions[] = [
                'path' => $label,
                'writable' => $writable,
            ];
        }

        $argonSupported = defined('PASSWORD_ARGON2ID');
        $openSslKeyGen = false;
        if (function_exists('openssl_pkey_new')) {
            $res = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $openSslKeyGen = $res !== false;
        }

        $security = [
            [
                'check' => 'Argon2id Password Hashing',
                'passed' => $argonSupported,
                'detail' => $argonSupported ? 'PASSWORD_ARGON2ID available' : 'PHP compiled without Argon2id support',
            ],
            [
                'check' => 'OpenSSL RSA-2048 Key Generation',
                'passed' => $openSslKeyGen,
                'detail' => $openSslKeyGen ? 'RSA key generation operational' : 'OpenSSL key generation failed (check openssl.cnf)',
            ],
            [
                'check' => 'CSPRNG random_bytes()',
                'passed' => strlen(random_bytes(16)) === 16,
                'detail' => 'System CSPRNG operational',
            ],
        ];

        $allSecurityOk = collect($security)->every(fn (array $item) => $item['passed']);

        return [
            'ready' => $phpOk && $allRequiredExtLoaded && $allWritable && $allSecurityOk,
            'php' => [
                'current' => PHP_VERSION,
                'minimum' => '8.3.0',
                'passed' => $phpOk,
            ],
            'extensions' => $extensions,
            'permissions' => $permissions,
            'security' => $security,
        ];
    }
}
