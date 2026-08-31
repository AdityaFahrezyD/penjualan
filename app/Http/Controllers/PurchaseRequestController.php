<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest\StoreDetailPurchaseRequest;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequest;
use App\Http\Requests\PurchaseRequest\UpdateDetailPurchaseRequest;
use App\Services\PurchaseRequestService;

class PurchaseRequestController extends Controller
{
    public function __construct(
        protected PurchaseRequestService $purchaseRequestService
    ) {}

    /**
     * Menampilkan seluruh Purchase Request.
     */
    public function index()
    {
        return response()->json([
            'message' => 'Data Purchase Request berhasil diambil.',
            'data' => $this->purchaseRequestService->getAll(),
        ]);
    }

    /**
     * Menampilkan detail Purchase Request.
     */
    public function show(string $purchase_request_id)
    {
        return response()->json([
            'message' => 'Data Purchase Request berhasil diambil.',
            'data' => $this->purchaseRequestService->getById(
                $purchase_request_id
            ),
        ]);
    }

    /**
     * Membuat Purchase Request beserta seluruh detail.
     */
    public function store(
        StorePurchaseRequest $request
    ) {
        return response()->json([
            'message' => 'Purchase Request berhasil dibuat.',
            'data' => $this->purchaseRequestService->create(
                $request->validated(),
                $request->user()->id
            ),
        ], 201);
    }

    /**
     * Menambahkan detail baru ke Purchase Request.
     */
    public function addDetail(
        StoreDetailPurchaseRequest $request,
        string $purchase_request_id
    ) {
        return response()->json([
            'message' => 'Detail Purchase Request berhasil ditambahkan.',
            'data' => $this->purchaseRequestService->addDetail(
                $purchase_request_id,
                $request->validated()
            ),
        ], 201);
    }

    /**
     * Mengubah detail Purchase Request.
     */
    public function updateDetail(
        UpdateDetailPurchaseRequest $request,
        string $purchase_request_id,
        string $detail_purchase_request_id
    ) {
        return response()->json([
            'message' => 'Detail Purchase Request berhasil diperbarui.',
            'data' => $this->purchaseRequestService->updateDetail(
                $purchase_request_id,
                $detail_purchase_request_id,
                $request->validated()
            ),
        ]);
    }

    /**
     * Menghapus detail Purchase Request.
     *
     * Header Purchase Request tetap ada.
     */
    public function deleteDetail(
        string $purchase_request_id,
        string $detail_purchase_request_id
    ) {
        $this->purchaseRequestService->deleteDetail(
            $purchase_request_id,
            $detail_purchase_request_id
        );

        return response()->json([
            'message' => 'Detail Purchase Request berhasil dihapus.',
        ]);
    }
}