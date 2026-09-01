<?php

namespace App\Http\Controllers;

use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Services\ItemService;

class ItemController extends Controller
{
    public function __construct(
        private ItemService $itemService
    ) {}

    public function index()
    {
        return response()->json([
            'message' => 'Data barang berhasil diambil',
            'data' => $this->itemService->getAll(),
        ]);
    }

    public function show(string $item_id)
    {
        return response()->json([
            'message' => 'Data barang berhasil diambil',
            'data' => $this->itemService->getById($item_id),
        ]);
    }

    public function store(StoreItemRequest $request)
    {
        $item = $this->itemService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Barang berhasil ditambahkan',
            'data' => $item,
        ], 201);
    }

    public function update(
        UpdateItemRequest $request,
        string $item_id
    ) {
        $item = $this->itemService->update(
            $item_id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Barang berhasil diubah',
            'data' => $item,
        ]);
    }

    public function destroy(string $item_id)
    {
        $this->itemService->delete($item_id);

        return response()->json([
            'message' => 'Barang berhasil dihapus',
        ]);
    }
}