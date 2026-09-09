<?php

namespace App\Http\Middleware;

use App\Services\MasterDataCache;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheMasterData
{
    public function __construct(private MasterDataCache $cache) {}

    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $ttl = (int) config('api.response_cache.ttl_seconds');

        if (! $request->isMethod('GET') || ! config('api.response_cache.enabled') || $ttl <= 0) {
            return $next($request);
        }

        // Capture the version before reading the database. A concurrent writer can
        // then invalidate it without this response overwriting the new generation.
        $key = $this->cache->key($resource, $request);
        $payload = Cache::get($key);

        if (is_string($payload)) {
            return JsonResponse::fromJsonString($payload);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() === 200) {
            Cache::put($key, $response->getContent(), $ttl);
        }

        return $response;
    }
}
