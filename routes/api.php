<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\MsTransactionController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Route::apiResource('items', ItemController::class);
// Route::apiResource('suppliers', SupplierController::class)

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    // Admin only
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('items', ItemController::class);
        Route::apiResource('suppliers', SupplierController::class);
    });

    // Admin & Akuntan
    Route::middleware('role:admin,akuntan')->group(function () {
        Route::apiResource('transactions', MsTransactionController::class)
            ->only([
                'index',
                'store',
                'show',
                'destroy',
            ]);
    });

});