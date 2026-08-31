<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class UnitService
{
    public function getAll()
    {
        return Unit::query()
            ->orderBy('unit_name')
            ->get();
    }

    public function getById(string $unit_id): Unit
    {
        return Unit::findOrFail($unit_id);
    }

    public function create(array $data): Unit
    {
        return Unit::create([
            'unit_name' => $data['unit_name'],
            'unit_code' => $data['unit_code'],
        ]);
    }

    public function update(
        string $unit_id,
        array $data
    ): Unit {
        $unit = Unit::findOrFail($unit_id);

        $unit->update($data);

        return $unit->fresh();
    }

    public function delete(string $unit_id): void
    {
        $unit = Unit::findOrFail($unit_id);

        try {
            $unit->delete();
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'unit' => [
                    'Unit tidak dapat dihapus karena masih digunakan oleh item.',
                ],
            ]);
        }
    }
}