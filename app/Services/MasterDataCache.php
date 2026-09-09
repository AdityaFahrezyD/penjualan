<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MasterDataCache
{
    public function key(string $resource, Request $request): string
    {
        $versionKey = $this->versionKey($resource);
        $version = Cache::get($versionKey);

        if ($version === null) {
            // A finite TTL lets database/Redis stores initialize atomically via add.
            // Always use a new UUID: an expired version must never revive old data.
            $candidate = (string) Str::uuid();
            Cache::add($versionKey, $candidate, 86400);
            $version = Cache::get($versionKey, $candidate);
        }

        $requestKey = json_encode([
            $request->path(),
            $this->normalizeQuery($request->query()),
        ], JSON_THROW_ON_ERROR);

        return 'api:master-data:'.$resource.':'.$version.':'.hash('sha256', $requestKey);
    }

    public function invalidate(string $resource): void
    {
        Cache::put($this->versionKey($resource), (string) Str::uuid(), 86400);
    }

    private function versionKey(string $resource): string
    {
        return 'api:master-data:'.$resource.':version';
    }

    private function normalizeQuery(array $query): array
    {
        if (! array_is_list($query)) {
            ksort($query);
        }

        foreach ($query as &$value) {
            if (is_array($value)) {
                $value = $this->normalizeQuery($value);
            }
        }

        return $query;
    }
}
