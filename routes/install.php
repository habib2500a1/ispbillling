<?php

use App\Http\Controllers\Install\InstallWizardController;
use App\Http\Middleware\RedirectIfAppInstalled;
use Illuminate\Support\Facades\Route;

Route::middleware([RedirectIfAppInstalled::class])
    ->prefix('install')
    ->name('install.')
    ->group(function (): void {
        Route::get('/', [InstallWizardController::class, 'welcome'])->name('welcome');
        Route::get('/permissions', [InstallWizardController::class, 'permissions'])->name('permissions');
        Route::post('/permissions', [InstallWizardController::class, 'fixPermissions'])->name('permissions.fix');
        Route::get('/database', [InstallWizardController::class, 'database'])->name('database');
        Route::post('/database', [InstallWizardController::class, 'storeDatabase'])->name('database.store');
        Route::get('/admin', [InstallWizardController::class, 'admin'])->name('admin');
        Route::post('/admin', [InstallWizardController::class, 'storeAdmin'])->name('admin.store');
        Route::get('/complete', [InstallWizardController::class, 'complete'])->name('complete');
    });
