<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestSupplier\StoreRequestSupplierRequest;
use App\Http\Requests\RequestSupplier\UpdateRequestSupplierStatusRequest;
use App\Services\RequestSupplierService;
use Illuminate\Http\Request;

class RequestSupplierController extends Controller
{
    public function __construct(
        protected RequestSupplierService $requestSupplierService
    ) {}

    /**
     * Menampilkan seluruh Request Supplier
     * berdasarkan Purchase Request.
     */
    public function index(string $purchase_request_id)
    {
        return response()->json([
            'message' => 'Data Request Supplier berhasil diambil.',
            'data' => $this->requestSupplierService->getByPurchaseRequest(
                $purchase_request_id
            ),
        ]);
    }

    /**
     * Admin membuat Request Supplier
     * untuk beberapa supplier sekaligus.
     */
    public function store(
        StoreRequestSupplierRequest $request,
        string $purchase_request_id
    ) {
        return response()->json([
            'message' => 'Request Supplier berhasil dibuat dan dikirim.',
            'data' => $this->requestSupplierService->createMultiple(
                $purchase_request_id,
                $request->validated()
            ),
        ], 201);
    }

    /**
     * Supplier memberikan respons terhadap
     * Request Supplier miliknya sendiri.
     */
    public function respond(
        UpdateRequestSupplierStatusRequest $request,
        string $request_supplier_id
    ) {
        $supplierId = $request->user()
            ->userSupplier
            ->supplier_id;

        return response()->json([
            'message' => 'Respons Request Supplier berhasil disimpan.',
            'data' => $this->requestSupplierService->respond(
                $request_supplier_id,
                $supplierId,
                $request->validated()
            ),
        ]);
    }
}