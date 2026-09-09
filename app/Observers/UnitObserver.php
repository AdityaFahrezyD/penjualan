<?php

namespace App\Observers;

use App\Models\Unit;
use App\Services\MasterDataCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UnitObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private MasterDataCache $cache) {}

    public function saved(Unit $unit): void
    {
        $this->invalidate();
    }

    public function deleted(Unit $unit): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        $this->cache->invalidate('units');
        $this->cache->invalidate('items');
    }
}
