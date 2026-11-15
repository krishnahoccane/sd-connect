<?php

use App\Modules\Installation\Services\InstallationExecutionService;
use Illuminate\Support\Facades\Route;

Route::get('/', function (InstallationExecutionService $installer) {
    if (!$installer->isInstalled()) {
        return redirect()->route('installer.index');
    }

    return view('welcome');
})->name('home');

require __DIR__ . '/authentication.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/installer.php';
