<?php

namespace App\Http\Controllers;

use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Services\UnitService;

class UnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    public function index()
    {
        return response()->json([
            'message' => 'Data unit berhasil diambil.',
            'data' => $this->unitService->getAll(),
        ]);
    }

    public function show(string $unit_id)
    {
        return response()->json([
            'message' => 'Data unit berhasil diambil.',
            'data' => $this->unitService->getById($unit_id),
        ]);
    }

    public function store(StoreUnitRequest $request)
    {
        return response()->json([
            'message' => 'Unit berhasil ditambahkan.',
            'data' => $this->unitService->create(
                $request->validated()
            ),
        ], 201);
    }

    public function update(
        UpdateUnitRequest $request,
        string $unit_id
    ) {
        return response()->json([
            'message' => 'Unit berhasil diperbarui.',
            'data' => $this->unitService->update(
                $unit_id,
                $request->validated()
            ),
        ]);
    }

    public function destroy(string $unit_id)
    {
        $this->unitService->delete($unit_id);

        return response()->json([
            'message' => 'Unit berhasil dihapus.',
        ]);
    }
}