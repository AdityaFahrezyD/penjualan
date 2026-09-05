<?php

namespace Tests\Feature\Api;

use App\Models\DetailPurchaseOrder;
use App\Models\DetailPurchaseRequest;
use App\Models\DetailSupplierQuotation;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RequestSupplier;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationPackagingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supplierUser;

    private Unit $pcs;

    private Unit $box;

    private Item $item;

    private DetailPurchaseRequest $requestDetail;

    private RequestSupplier $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->supplierUser = User::factory()->create(['role' => 'supplier']);
        $supplier = Supplier::create(['user_id' => $this->supplierUser->id, 'supplier_name' => 'Supplier A',
            'phone' => '08123456789', 'address' => 'Jakarta']);
        $this->pcs = Unit::create(['unit_name' => 'Pieces', 'unit_code' => 'PCS']);
        $this->box = Unit::create(['unit_name' => 'Kardus', 'unit_code' => 'BOX']);
        $this->item = Item::create(['item_name' => 'Sirup', 'stock' => 100, 'unit_id' => $this->pcs->unit_id]);
        $pr = PurchaseRequest::create(['request_number' => 'PR-TEST', 'created_by' => $this->admin->id,
            'request_date' => today(), 'status' => 'waiting_supplier']);
        $this->requestDetail = DetailPurchaseRequest::create(['purchase_request_id' => $pr->purchase_request_id,
            'item_id' => $this->item->item_id, 'quantity' => 101]);
        $this->invitation = RequestSupplier::create(['purchase_request_id' => $pr->purchase_request_id,
            'supplier_id' => $supplier->supplier_id, 'status' => 'accepted', 'sent_at' => now()]);
    }

    private function line(Unit $unit, int $quantity, int $conversion, int $price = 20000): array
    {
        return ['detail_purchase_request_id' => $this->requestDetail->detail_purchase_request_id,
            'unit_id' => $unit->unit_id, 'quantity' => $quantity, 'conversion_qty' => $conversion,
            'unit_price' => $price];
    }

    private function createQuotation(?array $lines = null): string
    {
        return $this->actingAs($this->supplierUser)->postJson(
            '/api/supplier-quotations/request-suppliers/'.$this->invitation->request_supplier_id,
            ['quotation_date' => today()->toDateString(), 'valid_until' => today()->toDateString(),
                'details' => $lines ?? [$this->line($this->box, 10, 10), $this->line($this->pcs, 1, 1, 2500)]])
            ->assertCreated()->json('data.supplier_quotation_id');
    }

    public function test_mixed_packaging_snapshots_prices_and_increases_stock_only_once(): void
    {
        $id = $this->createQuotation();
        $quote = SupplierQuotation::findOrFail($id);
        $this->assertSame('202500.00', $quote->total);
        $this->assertSame(101, $quote->quantity_summary[0]['offered_quantity']);
        $this->assertSame('exact', $quote->quantity_summary[0]['status']);
        $poId = $this->actingAs($this->admin)->postJson('/api/supplier-quotations/'.$id.'/purchase-order',
            ['order_date' => today()->toDateString()])->assertCreated()->json('data.purchase_order_id');
        $lines = DetailPurchaseOrder::where('purchase_order_id', $poId)->get();
        $this->assertCount(2, $lines);
        $this->assertSame(101, (int) $lines->sum('base_quantity'));
        $this->assertSame([1, 10], $lines->pluck('quantity')->map(fn ($q) => (int) $q)->sort()->values()->all());
        $boxLine = DetailSupplierQuotation::where('supplier_quotation_id', $id)->where('unit_id', $this->box->unit_id)->firstOrFail();
        $this->actingAs($this->supplierUser)->patchJson('/api/supplier-quotations/'.$id.'/details/'.$boxLine->getKey(),
            ['conversion_qty' => 20])->assertUnprocessable();
        $this->postJson('/api/supplier-quotations/'.$id.'/details', $this->line($this->pcs, 1, 1))->assertUnprocessable();
        $this->deleteJson('/api/supplier-quotations/'.$id.'/details/'.$boxLine->getKey())->assertUnprocessable();
        $this->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'shipping', 'shipping_date' => today()->toDateString(), 'expected_delivery_date' => today()->addDays(2)->toDateString()])->assertOk();
        $this->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'delivered'])->assertUnprocessable();
        $this->actingAs($this->admin)->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'delivered'])->assertOk();
        $this->assertSame(201, (int) $this->item->fresh()->stock);
        $this->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'delivered'])->assertUnprocessable();
        $this->assertSame(201, (int) $this->item->fresh()->stock);
    }

    public function test_over_and_under_supply_require_explicit_acceptance(): void
    {
        $id = $this->createQuotation([$this->line($this->box, 11, 10)]);
        $this->assertSame(9, SupplierQuotation::findOrFail($id)->quantity_summary[0]['difference']);
        $this->actingAs($this->admin)->postJson('/api/supplier-quotations/'.$id.'/purchase-order',
            ['order_date' => today()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('accept_quantity_difference');
        $this->assertDatabaseCount('purchase_orders', 0);
        $line = DetailSupplierQuotation::where('supplier_quotation_id', $id)->firstOrFail();
        $this->actingAs($this->supplierUser)->patchJson('/api/supplier-quotations/'.$id.'/details/'.$line->getKey(),
            ['quantity' => 10])->assertOk();
        $this->assertSame(-1, SupplierQuotation::findOrFail($id)->quantity_summary[0]['difference']);
        $this->actingAs($this->admin)->postJson('/api/supplier-quotations/'.$id.'/purchase-order',
            ['order_date' => today()->toDateString()])->assertUnprocessable();
        $this->postJson('/api/supplier-quotations/'.$id.'/purchase-order',
            ['order_date' => today()->toDateString(), 'accept_quantity_difference' => true])
            ->assertCreated()->assertJsonPath('data.quantity_difference_accepted', true);
        $this->assertSame(101, (int) $this->requestDetail->fresh()->quantity);
    }

    public function test_add_update_delete_recalculate_quantity_and_discounts(): void
    {
        $id = $this->createQuotation([$this->line($this->box, 10, 10)]);
        $lineId = $this->postJson('/api/supplier-quotations/'.$id.'/details', $this->line($this->pcs, 1, 1, 2500))
            ->assertCreated()->json('data.detail_supplier_quotation_id');
        $this->assertSame(101, SupplierQuotation::findOrFail($id)->quantity_summary[0]['offered_quantity']);
        $this->patchJson('/api/supplier-quotations/'.$id.'/details/'.$lineId,
            ['quantity' => 2, 'discount_percentage' => 10])->assertOk()->assertJsonPath('data.subtotal', '4500.00');
        $this->patchJson('/api/supplier-quotations/'.$id, ['discount_total_percentage' => 10])->assertOk();
        $this->assertSame('184050.00', SupplierQuotation::findOrFail($id)->total);
        $boxLine = DetailSupplierQuotation::where('supplier_quotation_id', $id)->where('unit_id', $this->box->unit_id)->firstOrFail();
        $this->patchJson('/api/supplier-quotations/'.$id.'/details/'.$boxLine->getKey(),
            ['conversion_qty' => 12])->assertOk()->assertJsonPath('data.base_quantity', 120);
        $this->deleteJson('/api/supplier-quotations/'.$id.'/details/'.$lineId)->assertOk();
        $this->assertSame('180000.00', SupplierQuotation::findOrFail($id)->total);
        $this->assertSame(120, SupplierQuotation::findOrFail($id)->quantity_summary[0]['offered_quantity']);
        $this->deleteJson('/api/supplier-quotations/'.$id.'/details/'.$boxLine->getKey())->assertUnprocessable();
    }

    public function test_invalid_packaging_and_client_calculated_values_are_rejected(): void
    {
        $url = '/api/supplier-quotations/request-suppliers/'.$this->invitation->getKey();
        foreach ([['conversion_qty' => 0], ['conversion_qty' => -1], ['conversion_qty' => 1.5],
            ['quantity' => 0], ['quantity' => 1.5], ['conversion_qty' => 2147483647],
            ['base_quantity' => 500], ['base_unit_id' => $this->box->getKey()],
            ['unit_id' => $this->pcs->getKey(), 'conversion_qty' => 10]] as $invalid) {
            $this->actingAs($this->supplierUser)->postJson($url,
                ['quotation_date' => today()->toDateString(), 'details' => [array_merge($this->line($this->box, 10, 10), $invalid)]])
                ->assertUnprocessable();
        }
        $this->assertDatabaseCount('supplier_quotations', 0);
        $this->assertDatabaseCount('detail_supplier_quotations', 0);
    }

    public function test_supplier_can_read_units_but_cannot_edit_master_or_another_quotation(): void
    {
        $id = $this->createQuotation();
        $this->getJson('/api/units')->assertOk();
        $this->postJson('/api/units', ['unit_name' => 'Pack', 'unit_code' => 'PACK'])->assertForbidden();
        $other = User::factory()->create(['role' => 'supplier']);
        Supplier::create(['user_id' => $other->getKey(), 'supplier_name' => 'Other', 'phone' => '0811111111', 'address' => 'Bandung']);
        $line = DetailSupplierQuotation::where('supplier_quotation_id', $id)->firstOrFail();
        $this->actingAs($other)->postJson('/api/supplier-quotations/'.$id.'/details', $this->line($this->pcs, 1, 1))->assertForbidden();
        $this->patchJson('/api/supplier-quotations/'.$id.'/details/'.$line->getKey(), ['quantity' => 2])->assertForbidden();
        $this->deleteJson('/api/supplier-quotations/'.$id.'/details/'.$line->getKey())->assertForbidden();
        $this->getJson('/api/supplier-quotations/request-suppliers/'.$this->invitation->getKey())->assertNotFound();
    }

    public function test_pr_snapshots_base_unit_and_used_item_unit_cannot_change(): void
    {
        $this->assertSame($this->pcs->unit_id, $this->requestDetail->base_unit_id);
        $this->actingAs($this->admin)->patchJson('/api/items/'.$this->item->getKey(), ['unit_id' => $this->box->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson('/api/purchase-requests', ['request_date' => today()->toDateString(),
            'details' => [['item_id' => $this->item->getKey(), 'quantity' => 101]]])->assertCreated();
        $this->assertSame(2, DetailPurchaseRequest::where('base_unit_id', $this->pcs->getKey())->count());
    }

    private function createShippingPo(): string
    {
        $quoteId = $this->createQuotation();

        return $this->actingAs($this->admin)->postJson('/api/supplier-quotations/'.$quoteId.'/purchase-order',
            ['order_date' => today()->toDateString()])->assertCreated()
            ->assertJsonPath('data.shipping_date', null)->assertJsonPath('data.expected_delivery_date', null)
            ->json('data.purchase_order_id');
    }

    public function test_only_the_owner_supplier_can_enter_shipping_dates(): void
    {
        $poId = $this->createShippingPo();
        $url = '/api/purchase-orders/'.$poId.'/status';
        $dates = ['status' => 'shipping', 'shipping_date' => today()->toDateString(),
            'expected_delivery_date' => today()->addDays(2)->toDateString()];
        $other = User::factory()->create(['role' => 'supplier']);
        Supplier::create(['user_id' => $other->getKey(), 'supplier_name' => 'Other', 'phone' => '0811111111', 'address' => 'Bandung']);
        $this->actingAs($other)->patchJson($url, $dates)->assertForbidden();
        $this->patchJson($url, [...$dates, 'supplier_id' => $this->invitation->supplier_id])->assertForbidden();
        $unlinked = User::factory()->create(['role' => 'supplier']);
        $this->actingAs($unlinked)->patchJson($url, $dates)->assertForbidden();
        foreach ([$this->admin, User::factory()->create(['role' => 'akuntan'])] as $user) {
            $this->actingAs($user)->patchJson($url, $dates)->assertUnprocessable();
            $this->patchJson('/api/purchase-orders/'.$poId, ['expected_delivery_date' => today()->toDateString()])
                ->assertUnprocessable();
            $this->patchJson('/api/purchase-orders/'.$poId.'/delivery-estimate', ['expected_delivery_date' => today()->toDateString()])
                ->assertForbidden();
        }
        $this->assertNull(PurchaseOrder::findOrFail($poId)->shipping_date);
        $this->actingAs($this->supplierUser)->patchJson($url, $dates)->assertOk();
        $po = PurchaseOrder::findOrFail($poId);
        $this->assertSame('shipping', $po->status);
        $this->assertSame(today()->toDateString(), $po->shipping_date->toDateString());
        $this->assertSame(today()->addDays(2)->toDateString(), $po->expected_delivery_date->toDateString());
        $this->assertSame(100, (int) $this->item->fresh()->stock);
    }

    public function test_shipping_dates_are_required_validated_and_cannot_modify_other_po_fields(): void
    {
        $poId = $this->createShippingPo();
        $url = '/api/purchase-orders/'.$poId.'/status';
        $valid = ['status' => 'shipping', 'shipping_date' => today()->toDateString(),
            'expected_delivery_date' => today()->addDays(2)->toDateString()];
        $this->actingAs($this->supplierUser)->patchJson($url, ['status' => 'shipping'])->assertUnprocessable();
        foreach ([['shipping_date' => today()->subDay()->toDateString()],
            ['shipping_date' => today()->addDay()->toDateString()],
            ['expected_delivery_date' => today()->subDay()->toDateString()],
            ['shipping_date' => 'invalid'], ['shipping_date' => null],
            ['total' => 1], ['notes' => 'tampered'], ['supplier_id' => $this->invitation->supplier_id],
            ['details' => [['quantity' => 999]]]] as $invalid) {
            $this->patchJson($url, array_merge($valid, $invalid))->assertUnprocessable();
        }
        $this->assertSame('draft', PurchaseOrder::findOrFail($poId)->status);
        $this->assertNull(PurchaseOrder::findOrFail($poId)->shipping_date);
        $this->assertSame(202500, (int) PurchaseOrder::findOrFail($poId)->total);
        $this->patchJson('/api/purchase-orders/'.$poId, ['notes' => 'supplier edit'])->assertForbidden();
        $this->patchJson($url, $valid)->assertOk();
        $this->patchJson($url, $valid)->assertUnprocessable();
    }

    public function test_estimate_is_owner_only_and_locked_after_delivery(): void
    {
        $poId = $this->createShippingPo();
        $url = '/api/purchase-orders/'.$poId.'/delivery-estimate';
        $estimate = ['expected_delivery_date' => today()->addDays(3)->toDateString()];
        $this->actingAs($this->supplierUser)->patchJson($url, $estimate)->assertUnprocessable();
        $this->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'shipping',
            'shipping_date' => today()->toDateString(), 'expected_delivery_date' => today()->addDay()->toDateString()])->assertOk();
        $other = User::factory()->create(['role' => 'supplier']);
        Supplier::create(['user_id' => $other->id, 'supplier_name' => 'Other', 'phone' => '0812345678', 'address' => 'Jakarta']);
        $this->actingAs($other)->patchJson($url, $estimate)->assertForbidden();
        $this->actingAs($this->supplierUser)->patchJson($url, ['expected_delivery_date' => today()->subDay()->toDateString()])->assertUnprocessable();
        $this->patchJson($url, [...$estimate, 'shipping_date' => today()->toDateString()])->assertUnprocessable();
        $this->patchJson($url, [...$estimate, 'total' => 1])->assertUnprocessable();
        $this->patchJson($url, $estimate)->assertOk();
        $this->assertSame(today()->addDays(3)->toDateString(), PurchaseOrder::findOrFail($poId)->expected_delivery_date->toDateString());
        $this->actingAs($this->admin)->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'delivered'])->assertOk();
        $this->actingAs($this->supplierUser)->patchJson($url, ['expected_delivery_date' => today()->addDays(4)->toDateString()])->assertUnprocessable();
        $this->assertSame(today()->addDays(3)->toDateString(), PurchaseOrder::findOrFail($poId)->expected_delivery_date->toDateString());
    }

    public function test_po_creator_cannot_supply_shipping_dates(): void
    {
        $quoteId = $this->createQuotation();
        foreach ([$this->admin, User::factory()->create(['role' => 'akuntan'])] as $user) {
            foreach (['shipping_date', 'expected_delivery_date'] as $field) {
                $this->actingAs($user)->postJson('/api/supplier-quotations/'.$quoteId.'/purchase-order',
                    ['order_date' => today()->toDateString(), $field => today()->toDateString()])
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_stock_overflow_rolls_back_delivery(): void
    {
        $id = $this->createQuotation();
        $poId = $this->actingAs($this->admin)->postJson('/api/supplier-quotations/'.$id.'/purchase-order',
            ['order_date' => today()->toDateString()])->assertCreated()->json('data.purchase_order_id');
        $this->actingAs($this->supplierUser)->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'shipping', 'shipping_date' => today()->toDateString(), 'expected_delivery_date' => today()->addDays(2)->toDateString()])->assertOk();
        $this->item->update(['stock' => 2147483640]);
        $this->actingAs($this->admin)->patchJson('/api/purchase-orders/'.$poId.'/status', ['status' => 'delivered'])->assertUnprocessable();
        $this->assertSame('shipping', PurchaseOrder::findOrFail($poId)->status);
        $this->assertSame(2147483640, (int) $this->item->fresh()->stock);
    }
}
