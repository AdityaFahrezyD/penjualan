<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Menampilkan semua item.
     */
    public function index()
    {
        $items = Item::orderBy('item_name')->get();

        return response()->json([
            'message' => 'Data barang berhasil diambil',
            'data' => $items,
        ]);
    }

    /**
     * Menampilkan satu item.
     */
    public function show(string $item_id)
    {
        $item = Item::find($item_id);

        if (!$item) {
            return response()->json([
                'message' => 'Barang tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Data barang berhasil diambil',
            'data' => $item,
        ]);
    }

    /**
     * Menambahkan item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:60'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = Item::create([
            'item_name' => $validated['item_name'],
            'stock' => $validated['stock'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Barang berhasil ditambahkan',
            'data' => $item,
        ], 201);
    }

    /**
     * Mengubah item.
     */
    public function update(Request $request, string $item_id)
    {
        $item = Item::find($item_id);

        if (!$item) {
            return response()->json([
                'message' => 'Barang tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:60'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Barang berhasil diubah',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * Menghapus item.
     */
    public function destroy(string $item_id)
    {
        $item = Item::find($item_id);

        if (!$item) {
            return response()->json([
                'message' => 'Barang tidak ditemukan',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'message' => 'Barang berhasil dihapus',
        ]);
    }
}