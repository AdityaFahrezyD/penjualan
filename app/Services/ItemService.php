<?php

namespace App\Services;

use App\Models\Item;

class ItemService
{
    public function getAll()
    {
        return Item::orderBy('item_name')->get();
    }

    public function getById(string $item_id): Item
    {
        $item = Item::find($item_id);

        if (!$item) {
            abort(404, 'Barang tidak ditemukan');
        }

        return $item;
    }

    public function create(array $data): Item
    {
        return Item::create([
            'item_name' => $data['item_name'],
            'stock' => $data['stock'] ?? 0,
            'item_price' => $data['item_price'],
        ]);
    }

    public function update(string $item_id, array $data): Item
    {
        $item = $this->getById($item_id);

        $item->update($data);

        return $item->fresh();
    }

    public function delete(string $item_id): void
    {
        $item = $this->getById($item_id);

        $item->delete();
    }
}