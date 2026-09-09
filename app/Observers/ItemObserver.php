<?php

namespace App\Observers;

use App\Models\Item;
use App\Services\MasterDataCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ItemObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private MasterDataCache $cache) {}

    public function saved(Item $item): void
    {
        $this->cache->invalidate('items');
    }

    public function deleted(Item $item): void
    {
        $this->cache->invalidate('items');
    }
}
