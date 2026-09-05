<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdateDeliveryEstimateRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderStatusRequest;
use App\Services\PurchaseOrderService;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * Menampilkan seluruh Purchase Order.
     */
    public function index()
    {
        return response()->json([
            'message' => 'Data Purchase Order berhasil diambil.',
            'data' => $this->purchaseOrderService->getAll(),
        ]);
    }

    /**
     * Menampilkan detail Purchase Order.
     */
    public function show(string $purchase_order_id)
    {
        return response()->json([
            'message' => 'Detail Purchase Order berhasil diambil.',
            'data' => $this->purchaseOrderService
                ->getById($purchase_order_id),
        ]);
    }

    /**
     * Membuat Purchase Order dari Supplier Quotation.
     */
    public function store(
        StorePurchaseOrderRequest $request,
        string $supplier_quotation_id
    ) {
        return response()->json([
            'message' => 'Purchase Order berhasil dibuat.',
            'data' => $this->purchaseOrderService->create(
                $supplier_quotation_id,
                $request->user()->id,
                $request->validated()
            ),
        ], 201);
    }

    /**
     * Memperbarui header Purchase Order.
     */
    public function update(
        UpdatePurchaseOrderRequest $request,
        string $purchase_order_id
    ) {
        return response()->json([
            'message' => 'Purchase Order berhasil diperbarui.',
            'data' => $this->purchaseOrderService->update(
                $purchase_order_id,
                $request->validated()
            ),
        ]);
    }

    /**
     * Memperbarui status Purchase Order.
     */
    public function updateStatus(
        UpdatePurchaseOrderStatusRequest $request,
        string $purchase_order_id
    ) {
        return response()->json([
            'message' => 'Status Purchase Order berhasil diperbarui.',
            'data' => $this->purchaseOrderService->updateStatus(
                $purchase_order_id,
                $request->validated()['status'],
                $request->user()->role,
                $request->user()->userSupplier?->supplier_id,
                $request->validated()
            ),
        ]);
    }

    public function updateDeliveryEstimate(
        UpdateDeliveryEstimateRequest $request,
        string $purchase_order_id
    ) {
        return response()->json([
            'message' => 'Estimasi tanggal tiba berhasil diperbarui.',
            'data' => $this->purchaseOrderService->updateDeliveryEstimate(
                $purchase_order_id,
                $request->user()->userSupplier->supplier_id,
                $request->validated()
            ),
        ]);
    }
}
