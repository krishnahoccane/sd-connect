<?php

use App\Http\Controllers\Installer\InstallationController;
use App\Http\Middleware\EnsureNotInstalled;
use Illuminate\Support\Facades\Route;

Route::prefix('install')
    ->middleware([EnsureNotInstalled::class])
    ->group(function (): void {
        Route::get('/', [InstallationController::class, 'index'])->name('installer.index');
        Route::get('/requirements', [InstallationController::class, 'checkRequirements'])->name('installer.requirements');
        Route::post('/database/test', [InstallationController::class, 'testDatabase'])->name('installer.database.test');
        Route::post('/database/migrate', [InstallationController::class, 'configureAndMigrate'])->name('installer.database.migrate');
        Route::post('/super-admin', [InstallationController::class, 'createSuperAdmin'])->name('installer.super-admin');
    });
