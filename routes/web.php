<?php

use App\Http\Controllers\Admin\ProfitReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PriceEstimateController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('studio', StudioController::class)->name('studio');

    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('generations.store');

    Route::post('generations/estimate', PriceEstimateController::class)
        ->middleware('throttle:300,1')
        ->name('generations.estimate');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('credits.store');

    Route::get('admin/report', ProfitReportController::class)
        ->middleware('can:view-admin-report')
        ->name('admin.report');
});

require __DIR__.'/settings.php';
