<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\RequestSupplierController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierQuotationController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);

        Route::apiResource('suppliers', SupplierController::class);

        Route::apiResource('units', UnitController::class);

        Route::apiResource('items', ItemController::class);

    });
    Route::middleware('role:admin,akuntan')->group(function () {

        Route::get(
            'purchase-requests',
            [PurchaseRequestController::class, 'index']
        );

        Route::get(
            'purchase-requests/{purchase_request_id}',
            [PurchaseRequestController::class, 'show']
        );

        Route::post(
            'purchase-requests',
            [PurchaseRequestController::class, 'store']
        );

        Route::post(
            'purchase-requests/{purchase_request_id}/details',
            [PurchaseRequestController::class, 'addDetail']
        );

        Route::patch(
            'purchase-requests/{purchase_request_id}/details/{detail_purchase_request_id}',
            [PurchaseRequestController::class, 'updateDetail']
        );

        Route::delete(
            'purchase-requests/{purchase_request_id}/details/{detail_purchase_request_id}',
            [PurchaseRequestController::class, 'deleteDetail']
        );

        Route::get(
            'purchase-requests/{purchase_request_id}/request-suppliers',
            [RequestSupplierController::class, 'index']
        );

        Route::post(
            'purchase-requests/{purchase_request_id}/request-suppliers',
            [RequestSupplierController::class, 'store']
        );

        Route::get(
            'purchase-orders',
            [PurchaseOrderController::class, 'index']
        );

        Route::get(
            'purchase-orders/{purchase_order_id}',
            [PurchaseOrderController::class, 'show']
        );

        Route::post(
            'supplier-quotations/{supplier_quotation_id}/purchase-order',
            [PurchaseOrderController::class, 'store']
        );

        Route::patch(
            'purchase-orders/{purchase_order_id}',
            [PurchaseOrderController::class, 'update']
        );

        Route::patch(
            'purchase-orders/{purchase_order_id}/status',
            [PurchaseOrderController::class, 'updateStatus']
        );

        Route::get(
            'purchase-orders/{purchase_order_id}/payments',
            [PaymentController::class, 'index']
        );

        Route::post(
            'purchase-orders/{purchase_order_id}/payments',
            [PaymentController::class, 'store']
        );

        Route::get(
            'payments/{payment_id}',
            [PaymentController::class, 'show']
        );

        Route::patch(
            'payments/{payment_id}',
            [PaymentController::class, 'update']
        );

        Route::patch(
            'payments/{payment_id}/submit',
            [PaymentController::class, 'submit']
        );

        Route::patch(
            'payments/{payment_id}/confirm',
            [PaymentController::class, 'confirm']
        );

        Route::patch(
            'payments/{payment_id}/reject',
            [PaymentController::class, 'reject']
        );
    });

    Route::middleware('role:supplier')->group(function () {
        Route::patch(
            'request-suppliers/{request_supplier_id}/respond',
            [RequestSupplierController::class, 'respond']
        );

        Route::get(
            'supplier-quotations',
            [SupplierQuotationController::class, 'index']
        );

        Route::get(
            'supplier-quotations/request-suppliers/{request_supplier_id}',
            [SupplierQuotationController::class, 'show']
        );

        Route::post(
            'supplier-quotations/request-suppliers/{request_supplier_id}',
            [SupplierQuotationController::class, 'store']
        );

        Route::patch(
            'supplier-quotations/{supplier_quotation_id}',
            [SupplierQuotationController::class, 'updateHeader']
        );

        Route::patch(
            'supplier-quotations/{supplier_quotation_id}/details/{detail_supplier_quotation_id}',
            [SupplierQuotationController::class, 'updateDetail']
        );
    });
});


