<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CacheMasterData;
use App\Models\DetailPurchaseOrder;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RequestSupplier;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\Unit;
use App\Models\User;
use App\Services\MasterDataCache;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasterDataCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Unit $unit;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise real serialization and expiration in isolated SQLite :memory:.
        config(['cache.default' => 'database']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->unit = Unit::create(['unit_name' => 'Piece', 'unit_code' => 'PCS']);
        $this->item = Item::create(['item_name' => 'Barang A', 'stock' => 10, 'unit_id' => $this->unit->getKey()]);
        $this->actingAs($this->admin);
    }

    private function masterQueries(): array
    {
        return array_values(array_filter(DB::getQueryLog(), fn (array $query) => preg_match('/\bfrom\s+["`]?\b(items|units)\b/i', $query['query']) === 1));
    }

    public function test_lists_and_details_reuse_json_without_reading_master_tables(): void
    {
        foreach (['/api/items', '/api/items/'.$this->item->getKey(), '/api/units', '/api/units/'.$this->unit->getKey()] as $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $first = $this->getJson($url)->assertOk();
            $this->assertNotEmpty($this->masterQueries());
            DB::flushQueryLog();
            $cached = $this->getJson($url)->assertOk();
            $this->assertSame($first->getContent(), $cached->getContent());
            $this->assertSame([], $this->masterQueries());
            DB::disableQueryLog();
        }

        $this->assertGreaterThan(0, DB::table('cache')->count());
    }

    public function test_expiration_and_disable_switch_read_fresh_data(): void
    {
        $url = '/api/items/'.$this->item->getKey();
        $this->getJson($url)->assertJsonPath('data.stock', 10);
        // Deliberately bypass observers to verify TTL independently of invalidation.
        Item::whereKey($this->item->getKey())->update(['stock' => 11]);
        $this->getJson($url)->assertJsonPath('data.stock', 10);
        $this->travel(61)->seconds();
        $this->getJson($url)->assertJsonPath('data.stock', 11);
        Item::whereKey($this->item->getKey())->update(['stock' => 12]);
        config(['api.response_cache.enabled' => false]);
        $this->getJson($url)->assertJsonPath('data.stock', 12);

        config(['api.response_cache.enabled' => true, 'api.response_cache.ttl_seconds' => 2]);
        app(MasterDataCache::class)->invalidate('items');
        $this->getJson($url)->assertJsonPath('data.stock', 12);
        Item::whereKey($this->item->getKey())->update(['stock' => 13]);
        $this->travel(3)->seconds();
        $this->getJson($url)->assertJsonPath('data.stock', 13);
    }

    public function test_item_mutations_invalidate_lists_and_details(): void
    {
        $this->getJson('/api/items')->assertJsonCount(1, 'data');
        $id = $this->postJson('/api/items', ['item_name' => 'Barang B', 'stock' => 0, 'unit_id' => $this->unit->getKey()])
            ->assertCreated()->json('data.item_id');
        $this->getJson('/api/items')->assertJsonCount(2, 'data');
        $this->getJson('/api/items/'.$id)->assertJsonPath('data.item_name', 'Barang B');
        $this->patchJson('/api/items/'.$id, ['item_name' => 'Barang C'])->assertOk();
        $this->getJson('/api/items/'.$id)->assertJsonPath('data.item_name', 'Barang C');
        $this->getJson('/api/items')->assertJsonPath('data.1.item_name', 'Barang C');
        $this->deleteJson('/api/items/'.$id)->assertOk();
        $this->getJson('/api/items')->assertJsonCount(1, 'data');
        $this->getJson('/api/items/'.$id)->assertNotFound();
    }

    public function test_unit_mutations_also_refresh_item_relationships(): void
    {
        $this->getJson('/api/units')->assertJsonCount(1, 'data');
        $this->getJson('/api/units/'.$this->unit->getKey())->assertOk();
        $this->getJson('/api/items')->assertOk();
        $this->getJson('/api/items/'.$this->item->getKey())->assertOk();
        $this->patchJson('/api/units/'.$this->unit->getKey(), ['unit_name' => 'Pieces'])->assertOk();
        $this->getJson('/api/units')->assertJsonPath('data.0.unit_name', 'Pieces');
        $this->getJson('/api/units/'.$this->unit->getKey())->assertJsonPath('data.unit_name', 'Pieces');
        $this->getJson('/api/items')->assertJsonPath('data.0.item_unit.unit_name', 'Pieces');
        $this->getJson('/api/items/'.$this->item->getKey())->assertJsonPath('data.item_unit.unit_name', 'Pieces');

        $id = $this->postJson('/api/units', ['unit_name' => 'Box', 'unit_code' => 'BOX'])->assertCreated()->json('data.unit_id');
        $this->getJson('/api/units')->assertJsonCount(2, 'data');
        $this->getJson('/api/units/'.$id)->assertOk();
        $this->deleteJson('/api/units/'.$id)->assertOk();
        $this->getJson('/api/units')->assertJsonCount(1, 'data');
        $this->getJson('/api/units/'.$id)->assertNotFound();
    }

    public function test_warm_cache_still_requires_authentication_and_role(): void
    {
        $this->getJson('/api/items')->assertOk();
        $this->getJson('/api/units')->assertOk();
        $this->getJson('/api/units/'.$this->unit->getKey())->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'supplier']));
        $this->getJson('/api/items')->assertForbidden();
        $this->getJson('/api/units/'.$this->unit->getKey())->assertForbidden();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/units')->assertOk();
        $this->assertSame([], $this->masterQueries());
        DB::disableQueryLog();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/units')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/items')->assertUnauthorized();
    }

    public function test_errors_are_not_cached(): void
    {
        $url = '/api/items/'.Str::uuid();
        for ($i = 0; $i < 2; $i++) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson($url)->assertNotFound();
            $this->assertNotEmpty($this->masterQueries());
            DB::disableQueryLog();
        }
    }

    public function test_non_get_requests_and_server_errors_are_not_cached(): void
    {
        $middleware = app(CacheMasterData::class);
        $calls = 0;
        $next = function () use (&$calls) {
            return response()->json(['attempt' => ++$calls]);
        };
        $post = Request::create('/api/items', 'POST');
        $middleware->handle($post, $next, 'items');
        $middleware->handle($post, $next, 'items');
        $this->assertSame(2, $calls);

        $request = Request::create('/api/items');
        $middleware->handle($request, fn () => response()->json(['message' => 'Failure'], 500), 'items');
        $this->getJson('/api/items')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_purchase_orders_remain_uncached(): void
    {
        $po = $this->shippingOrder();
        $this->getJson('/api/purchase-orders/'.$po->getKey())->assertJsonPath('data.notes', null);
        $po->update(['notes' => 'Updated']);
        $this->getJson('/api/purchase-orders/'.$po->getKey())->assertJsonPath('data.notes', 'Updated');
    }

    public function test_query_keys_ignore_map_order_but_preserve_values_and_list_order(): void
    {
        $this->getJson('/api/items?filter[name]=A&sort=name')->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/items?sort=name&filter[name]=A')->assertOk();
        $this->assertSame([], $this->masterQueries());
        $this->getJson('/api/items?sort=name&filter[name]=B')->assertOk();
        $this->assertNotEmpty($this->masterQueries());
        DB::disableQueryLog();

        $cache = app(MasterDataCache::class);
        $this->assertNotSame(
            $cache->key('items', Request::create('/api/items?sort[]=name&sort[]=stock')),
            $cache->key('items', Request::create('/api/items?sort[]=stock&sort[]=name')),
        );
    }

    public function test_observers_wait_for_commit_and_discard_rollback_callbacks(): void
    {
        // A nontransactional cache proves rollback correctness comes from callbacks,
        // rather than SQLite also rolling back the cache table writes.
        config(['cache.default' => 'array']);
        $cache = app(MasterDataCache::class);
        $request = Request::create('/api/items');
        $before = $cache->key('items', $request);
        DB::beginTransaction();
        $this->unit->update(['unit_name' => 'Pending']);
        $this->item->update(['item_name' => 'Pending']);
        $this->assertSame($before, $cache->key('items', $request));
        DB::rollBack();
        $this->assertSame($before, $cache->key('items', $request));
        $this->assertSame('Barang A', $this->item->fresh()->item_name);

        DB::beginTransaction();
        $this->item->fresh()->update(['item_name' => 'Committed']);
        $this->assertSame($before, $cache->key('items', $request));
        DB::commit();
        $this->assertNotSame($before, $cache->key('items', $request));
    }

    public function test_in_flight_read_cannot_repopulate_active_generation_after_write(): void
    {
        $request = Request::create('/api/items/'.$this->item->getKey());
        app(CacheMasterData::class)->handle($request, function () {
            $oldPayload = ['message' => 'Data barang berhasil diambil', 'data' => $this->item->load('itemUnit')->toArray()];
            $this->item->update(['item_name' => 'Changed during read']);

            return response()->json($oldPayload);
        }, 'items');

        $this->getJson($request->getRequestUri())->assertJsonPath('data.item_name', 'Changed during read');
    }

    public function test_expired_version_never_revives_an_old_payload(): void
    {
        $url = '/api/items/'.$this->item->getKey();
        $this->getJson($url)->assertJsonPath('data.stock', 10);
        Item::whereKey($this->item->getKey())->update(['stock' => 20]);
        Cache::forget('api:master-data:items:version');
        $this->getJson($url)->assertJsonPath('data.stock', 20);
    }

    public function test_delivered_order_invalidates_stock_only_after_commit(): void
    {
        $po = $this->shippingOrder();
        $url = '/api/items/'.$this->item->getKey();
        $this->getJson('/api/items')->assertJsonPath('data.0.stock', 10);
        $this->getJson($url)->assertJsonPath('data.stock', 10);

        $this->patchJson('/api/purchase-orders/'.$po->getKey().'/status', ['status' => 'delivered'])->assertOk();
        $this->getJson($url)->assertJsonPath('data.stock', 15);
        $this->getJson('/api/items')->assertJsonPath('data.0.stock', 15);
        $this->patchJson('/api/purchase-orders/'.$po->getKey().'/status', ['status' => 'delivered'])->assertUnprocessable();
        $this->getJson($url)->assertJsonPath('data.stock', 15);
    }

    public function test_rolled_back_delivery_does_not_invalidate_or_change_stock(): void
    {
        config(['cache.default' => 'array']);
        $po = $this->shippingOrder();
        $request = Request::create('/api/items/'.$this->item->getKey());
        $this->getJson($request->getRequestUri())->assertJsonPath('data.stock', 10);
        $cache = app(MasterDataCache::class);
        $before = $cache->key('items', $request);

        DB::beginTransaction();
        app(PurchaseOrderService::class)->updateStatus($po->getKey(), 'delivered', 'admin');
        $this->assertSame($before, $cache->key('items', $request));
        DB::rollBack();
        $this->assertSame($before, $cache->key('items', $request));
        $this->assertSame(10, $this->item->fresh()->stock);
        $this->assertSame('shipping', $po->fresh()->status);
        $this->getJson($request->getRequestUri())->assertJsonPath('data.stock', 10);
    }

    private function shippingOrder(): PurchaseOrder
    {
        $supplierUser = User::factory()->create(['role' => 'supplier']);
        $supplier = Supplier::create(['user_id' => $supplierUser->getKey(), 'supplier_name' => 'Supplier',
            'phone' => '0812345678', 'address' => 'Jakarta']);
        $pr = PurchaseRequest::create(['request_number' => 'PR-CACHE', 'created_by' => $this->admin->getKey(),
            'request_date' => today(), 'status' => 'po_created']);
        $invitation = RequestSupplier::create(['purchase_request_id' => $pr->getKey(),
            'supplier_id' => $supplier->getKey(), 'status' => 'accepted']);
        $quotation = SupplierQuotation::create(['quotation_number' => 'QR-CACHE',
            'request_supplier_id' => $invitation->getKey(), 'quotation_date' => today(), 'status' => 'po_created']);
        $po = PurchaseOrder::create(['po_number' => 'PO-CACHE', 'purchase_request_id' => $pr->getKey(),
            'supplier_id' => $supplier->getKey(), 'supplier_quotation_id' => $quotation->getKey(),
            'created_by' => $this->admin->getKey(), 'order_date' => today(), 'status' => 'shipping']);
        DetailPurchaseOrder::create(['purchase_order_id' => $po->getKey(), 'item_id' => $this->item->getKey(),
            'unit_id' => $this->unit->getKey(), 'base_unit_id' => $this->unit->getKey(), 'quantity' => 5,
            'conversion_qty' => 1, 'base_quantity' => 5, 'unit_price' => 1000, 'subtotal' => 5000]);

        return $po;
    }
}
