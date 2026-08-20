<?php

namespace App\Services;

use App\Models\Supplier;

class SupplierService
{
    public function getAll()
    {
        return Supplier::orderBy('supplier_name')->get();
    }

    public function getById(string $supplier_id): Supplier
    {
        $supplier = Supplier::find($supplier_id);

        if (!$supplier) {
            abort(404, 'Supplier tidak ditemukan');
        }

        return $supplier;
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function update(
        string $supplier_id,
        array $data
    ): Supplier {
        $supplier = $this->getById($supplier_id);

        $supplier->update($data);

        return $supplier->fresh();
    }

    public function delete(string $supplier_id): void
    {
        $supplier = $this->getById($supplier_id);

        $supplier->delete();
    }
}