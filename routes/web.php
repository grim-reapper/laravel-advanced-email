<?php

use Illuminate\Support\Facades\Route;
use GrimReapper\AdvancedEmail\Http\Controllers\TrackingController;
use GrimReapper\AdvancedEmail\Http\Controllers\EmailReportController;

// Tracking Routes
Route::get('/track/open/{uuid}', [TrackingController::class, 'trackOpen'])
    ->name('opens');

Route::get('/track/click/{uuid}/{link_id}', [TrackingController::class, 'trackLinkClick'])
    ->name('clicks');

// Reporting Routes (Optional - Requires Authentication/Authorization)
Route::middleware(['web', 'auth']) // Example middleware, adjust as needed
    ->prefix('admin/email-reports') // Example prefix
    ->name('advanced-email.report.')
    ->group(function () {
        Route::get('/', [EmailReportController::class, 'index'])->name('index');
        Route::get('/{uuid}', [EmailReportController::class, 'show'])->name('show');
    });