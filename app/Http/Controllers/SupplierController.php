<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Menampilkan semua supplier.
     */
    public function index()
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();

        return response()->json([
            'message' => 'Data supplier berhasil diambil',
            'data' => $suppliers,
        ]);
    }

    /**
     * Menampilkan satu supplier.
     */
    public function show(string $supplier_id)
    {
        $supplier = Supplier::find($supplier_id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Supplier tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Data supplier berhasil diambil',
            'data' => $supplier,
        ]);
    }

    /**
     * Menambahkan supplier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:12'],
            'address' => ['required', 'string', 'max:60'],
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'message' => 'Supplier berhasil ditambahkan',
            'data' => $supplier,
        ], 201);
    }

    /**
     * Mengubah supplier.
     */
    public function update(Request $request, string $supplier_id)
    {
        $supplier = Supplier::find($supplier_id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Supplier tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:12'],
            'address' => ['required', 'string', 'max:60'],
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier berhasil diubah',
            'data' => $supplier->fresh(),
        ]);
    }

    /**
     * Menghapus supplier.
     */
    public function destroy(string $supplier_id)
    {
        $supplier = Supplier::find($supplier_id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Supplier tidak ditemukan',
            ], 404);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier berhasil dihapus',
        ]);
    }
}