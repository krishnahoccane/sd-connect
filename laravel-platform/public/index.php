<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Ensure writable Laravel skeleton directories exist on fresh shared-hosting uploads
$requiredDirs = [
    __DIR__ . '/../bootstrap/cache',
    __DIR__ . '/../storage/app',
    __DIR__ . '/../storage/app/public',
    __DIR__ . '/../storage/framework/cache/data',
    __DIR__ . '/../storage/framework/sessions',
    __DIR__ . '/../storage/framework/views',
    __DIR__ . '/../storage/logs',
];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$envPath = __DIR__ . '/../.env';
$envExamplePath = __DIR__ . '/../.env.example';
if (!file_exists($envPath) && file_exists($envExamplePath) && is_writable(__DIR__ . '/..')) {
    @copy($envExamplePath, $envPath);
}

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>TWSD Connect — Composer Dependencies Missing</title>'
        . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#0f172a;padding:2rem;}'
        . '.card{max-width:640px;margin:2rem auto;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:1.75rem;}'
        . 'pre{background:#0f172a;color:#f8fafc;padding:1rem;border-radius:6px;overflow:auto;font-size:.875rem;}</style></head>'
        . '<body><div class="card"><h1 style="margin-top:0;font-size:1.25rem;">TWSD Connect — Composer Install Required</h1>'
        . '<p><code>vendor/autoload.php</code> was not found. On cPanel or your target PHP 8.3+ host, open <strong>Terminal</strong> / SSH in the project root and run:</p>'
        . '<pre>composer install --no-dev --optimize-autoloader</pre>'
        . '<p style="margin-bottom:0;color:#475569;font-size:.9rem;">Once dependencies are installed, reload this page to launch the <strong>/install</strong> setup wizard.</p>'
        . '</div></body></html>';
    exit;
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require $autoloadPath;

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
