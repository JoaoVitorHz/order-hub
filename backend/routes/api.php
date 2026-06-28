<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/metrics', [OrderController::class, 'metrics']);
    Route::get('/{id}', [OrderController::class, 'show'])->where('id', '[0-9]+');
    Route::post('/{id}/status', [OrderController::class, 'updateStatus'])->where('id', '[0-9]+');
});

Route::get('/affiliates/{id}/summary', [AffiliateController::class, 'summary'])->where('id', '[0-9]+');
