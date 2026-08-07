<?php

use App\Domain\Catalog\Http\Controllers\ProductController;
use App\Domain\Identity\Http\Controllers\AuthController;
use App\Domain\Stores\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/stores', [StoreController::class, 'index']);
        Route::post('/stores', [StoreController::class, 'store']);
        Route::get('/stores/{store}', [StoreController::class, 'show']);
        Route::post('/stores/{store}/activate', [StoreController::class, 'activate']);

        Route::middleware('tenant')->group(function () {
            Route::apiResource('products', ProductController::class)->except(['create', 'edit']);
        });
    });
});
