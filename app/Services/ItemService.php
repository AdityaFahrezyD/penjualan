<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function getAll()
    {
        return Item::with('itemUnit')
            ->orderBy('item_name')
            ->get();
    }

    public function getById(string $item_id): Item
    {
        return Item::with('itemUnit')
            ->findOrFail($item_id);
    }

    public function create(array $data): Item
    {
        return Item::create([
            'item_name' => $data['item_name'],
            'stock' => $data['stock'],
            'unit_id' => $data['unit_id'],
        ])->load('itemUnit');
    }

    public function update(string $item_id, array $data): Item
    {
        return DB::transaction(function () use ($item_id, $data) {
            $item = Item::lockForUpdate()->findOrFail($item_id);
            if (isset($data['unit_id']) && $data['unit_id'] !== $item->unit_id
                && ($item->stock != 0 || $item->itemDetailPurchaseRequest()->exists() || $item->itemDetailPurchaseOrder()->exists())) {
                throw ValidationException::withMessages(['unit_id' => ['Satuan dasar tidak dapat diubah setelah memiliki stok atau transaksi.']]);
            }

            $item->update([
                'item_name' => $data['item_name']
                    ?? $item->item_name,

                'unit_id' => $data['unit_id']
                    ?? $item->unit_id,
            ]);

            return $item->fresh()->load('itemUnit');
        });
    }

    public function delete(string $item_id): void
    {
        $item = Item::findOrFail($item_id);

        try {
            $item->delete();
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'item' => [
                    'Item tidak dapat dihapus karena masih digunakan dalam dokumen procurement.',
                ],
            ]);
        }
    }
}
