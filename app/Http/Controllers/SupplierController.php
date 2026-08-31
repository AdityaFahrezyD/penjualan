<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Services\SupplierService;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService
    ) {}

    /**
     * Menampilkan seluruh supplier.
     */
    public function index()
    {
        return response()->json([
            'message' => 'Data supplier berhasil diambil.',
            'data' => $this->supplierService->getAll(),
        ]);
    }

    /**
     * Menampilkan detail supplier.
     */
    public function show(string $supplier_id)
    {
        return response()->json([
            'message' => 'Data supplier berhasil diambil.',
            'data' => $this->supplierService->getById($supplier_id),
        ]);
    }

    /**
     * Menambahkan supplier.
     */
    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->supplierService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Supplier berhasil ditambahkan.',
            'data' => $supplier,
        ], 201);
    }

    /**
     * Mengubah supplier.
     */
    public function update(
        UpdateSupplierRequest $request,
        string $supplier_id
    ) {
        $supplier = $this->supplierService->update(
            $supplier_id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Supplier berhasil diperbarui.',
            'data' => $supplier,
        ]);
    }

    /**
     * Menghapus supplier.
     */
    public function destroy(string $supplier_id)
    {
        $this->supplierService->delete($supplier_id);

        return response()->json([
            'message' => 'Supplier berhasil dihapus.',
        ]);
    }
}