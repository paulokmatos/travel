<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TravelOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware('auth:api')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });
    });

    Route::middleware('auth:api')->group(function (): void {
        Route::patch('travel-orders/{travel_order}/status', [TravelOrderController::class, 'updateStatus'])
            ->name('travel-orders.update-status');

        Route::apiResource('travel-orders', TravelOrderController::class)
            ->parameters(['travel-orders' => 'travel_order'])
            ->only(['index', 'store', 'show', 'update']);
    });
});
