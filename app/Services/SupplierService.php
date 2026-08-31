<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function getAll()
    {
        return Supplier::with('supplierUser')
            ->orderBy('supplier_name')
            ->get();
    }

    public function getById(string $supplier_id): Supplier
    {
        return Supplier::with('supplierUser')
            ->findOrFail($supplier_id);
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data)
            ->load('supplierUser');
    }

    public function update(
        string $supplier_id,
        array $data
    ): Supplier {
        $supplier = Supplier::findOrFail($supplier_id);

        $supplier->update($data);

        return $supplier->fresh()
            ->load('supplierUser');
    }

    public function delete(string $supplier_id): void
    {
        $supplier = Supplier::findOrFail($supplier_id);

        try {
            $supplier->delete();
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'supplier' => [
                    'Supplier tidak dapat dihapus karena masih digunakan.',
                ],
            ]);
        }
    }
}