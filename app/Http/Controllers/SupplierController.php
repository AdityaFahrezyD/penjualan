<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Services\SupplierService;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierService $supplierService
    ) {}

    public function index()
    {
        return response()->json([
            'message' => 'Data supplier berhasil diambil',
            'data' => $this->supplierService->getAll(),
        ]);
    }

    public function show(string $supplier_id)
    {
        return response()->json([
            'message' => 'Data supplier berhasil diambil',
            'data' => $this->supplierService->getById($supplier_id),
        ]);
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->supplierService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Supplier berhasil ditambahkan',
            'data' => $supplier,
        ], 201);
    }

    public function update(
        UpdateSupplierRequest $request,
        string $supplier_id
    ) {
        $supplier = $this->supplierService->update(
            $supplier_id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Supplier berhasil diubah',
            'data' => $supplier,
        ]);
    }

    public function destroy(string $supplier_id)
    {
        $this->supplierService->delete($supplier_id);

        return response()->json([
            'message' => 'Supplier berhasil dihapus',
        ]);
    }
}