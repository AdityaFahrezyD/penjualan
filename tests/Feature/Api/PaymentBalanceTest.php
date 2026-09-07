<?php

namespace Tests\Feature\Api;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RequestSupplier;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $supplierUser = User::factory()->create(['role' => 'supplier']);
        $supplier = Supplier::create(['user_id' => $supplierUser->id, 'supplier_name' => 'Supplier', 'phone' => '0812345678', 'address' => 'Jakarta']);
        $pr = PurchaseRequest::create(['request_number' => 'PR-PAYMENT', 'created_by' => $this->admin->id, 'request_date' => today(), 'status' => 'po_created']);
        $invitation = RequestSupplier::create(['purchase_request_id' => $pr->getKey(), 'supplier_id' => $supplier->getKey(), 'status' => 'accepted']);
        $quotation = SupplierQuotation::create(['quotation_number' => 'QR-PAYMENT', 'request_supplier_id' => $invitation->getKey(), 'quotation_date' => today(), 'status' => 'po_created']);
        $this->po = PurchaseOrder::create(['po_number' => 'PO-PAYMENT', 'purchase_request_id' => $pr->getKey(), 'supplier_id' => $supplier->getKey(),
            'supplier_quotation_id' => $quotation->getKey(), 'created_by' => $this->admin->id, 'order_date' => today(), 'total' => 100000]);
    }

    private function payment(string $amount = '1000.00', string $status = 'draft'): Payment
    {
        $payment = app(PaymentService::class)->create($this->po->getKey(), $this->admin->id, ['amount' => $amount, 'payment_method' => 'cash']);
        $payment->update(['status' => $status]);
        return $payment;
    }

    public function test_summary_counts_only_confirmed_and_preserves_list_contract(): void
    {
        $this->payment('40000.00', 'confirmed');
        foreach (['draft', 'waiting_confirmation', 'rejected'] as $status) {
            $this->payment('10000.00', $status);
        }
        $this->actingAs($this->admin)->getJson('/api/purchase-orders/'.$this->po->getKey().'/payments')
            ->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('meta.payment_summary', [
                'total_amount' => '100000.00', 'confirmed_amount' => '40000.00', 'remaining_amount' => '60000.00',
            ]);
    }

    public function test_invalid_create_and_update_amounts_are_rejected(): void
    {
        $draft = $this->payment();
        $this->payment('40000.00', 'confirmed');
        foreach (['-1', '0', '1.001', '100000.01', '60000.01'] as $amount) {
            $this->actingAs($this->admin)->postJson('/api/purchase-orders/'.$this->po->getKey().'/payments', [
                'amount' => $amount, 'payment_method' => 'cash',
            ])->assertUnprocessable()->assertJsonValidationErrors('amount');
            $this->patchJson('/api/payments/'.$draft->getKey(), ['amount' => $amount])
                ->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->assertSame('1000.00', number_format($draft->fresh()->amount, 2, '.', ''));
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_exact_remaining_amount_is_accepted_and_paid_is_recalculated(): void
    {
        $this->payment('40000.00', 'confirmed');
        $this->actingAs($this->admin);
        $response = $this->postJson('/api/purchase-orders/'.$this->po->getKey().'/payments', [
            'amount' => '60000.00', 'payment_method' => 'cash',
        ])->assertCreated();
        $id = $response->json('data.payment_id');
        $this->patchJson('/api/payments/'.$id.'/submit')->assertOk();
        $this->patchJson('/api/payments/'.$id.'/confirm')->assertOk();
        $this->assertSame('paid', $this->po->fresh()->payment_status);
        $this->postJson('/api/purchase-orders/'.$this->po->getKey().'/payments', [
            'amount' => '0.01', 'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_stale_drafts_and_pending_payments_are_revalidated(): void
    {
        $draft = $this->payment('70000.00');
        $pending = $this->payment('70000.00', 'waiting_confirmation');
        $this->payment('40000.00', 'confirmed');
        $this->actingAs($this->admin)->patchJson('/api/payments/'.$draft->getKey().'/submit')
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->patchJson('/api/payments/'.$pending->getKey().'/confirm')
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('waiting_confirmation', $pending->fresh()->status);
    }

    public function test_fractional_amounts_are_exact(): void
    {
        $this->po->update(['total' => '0.30']);
        $this->payment('0.10', 'confirmed');
        $payment = $this->payment('0.20', 'waiting_confirmation');
        app(PaymentService::class)->confirm($payment->getKey(), $this->admin->id);
        $this->assertSame('0.00', app(PaymentService::class)->getWithSummary($this->po->getKey())['meta']['payment_summary']['remaining_amount']);
    }

    public function test_unconfirmed_payments_can_be_deleted_by_admin_and_accountant(): void
    {
        foreach (['admin', 'akuntan'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['draft', 'waiting_confirmation', 'rejected'] as $status) {
                $payment = $this->payment('1000.00', $status);
                $this->deleteJson('/api/payments/'.$payment->getKey())->assertOk();
                $this->assertDatabaseMissing('payments', ['payment_id' => $payment->getKey()]);
            }
        }
        $this->assertSame('100000.00', app(PaymentService::class)->getWithSummary($this->po->getKey())['meta']['payment_summary']['remaining_amount']);
    }

    public function test_confirmed_payment_cannot_be_deleted(): void
    {
        $payment = $this->payment('1000.00', 'confirmed');
        $this->actingAs($this->admin)->deleteJson('/api/payments/'.$payment->getKey())
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertDatabaseHas('payments', ['payment_id' => $payment->getKey(), 'status' => 'confirmed']);
    }

    public function test_delete_requires_authentication_and_disallows_supplier(): void
    {
        $payment = $this->payment();
        $this->deleteJson('/api/payments/'.$payment->getKey())->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'supplier']))->deleteJson('/api/payments/'.$payment->getKey())->assertForbidden();
        $this->assertDatabaseHas('payments', ['payment_id' => $payment->getKey()]);
    }

    public function test_missing_payment_returns_not_found(): void
    {
        $this->actingAs($this->admin)->deleteJson('/api/payments/'.Str::uuid())->assertNotFound();
    }
    public function test_supplier_reads_only_own_payment_list_and_detail(): void
    {
        $payment = $this->payment('40000.00', 'confirmed');
        $owner = $this->po->purchaseOrderSupplier->supplierUser;
        $this->actingAs($owner)->getJson('/api/purchase-orders/'.$this->po->getKey().'/payments')
            ->assertOk()->assertJsonPath('meta.payment_summary.remaining_amount', '60000.00');
        $this->getJson('/api/payments/'.$payment->getKey())->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'supplier']));
        $this->getJson('/api/purchase-orders/'.$this->po->getKey().'/payments')->assertForbidden();
        $this->getJson('/api/payments/'.$payment->getKey())->assertForbidden();
    }

    public function test_supplier_cannot_mutate_even_own_payments(): void
    {
        $payment = $this->payment();
        $owner = $this->po->purchaseOrderSupplier->supplierUser;
        $this->actingAs($owner)->postJson('/api/purchase-orders/'.$this->po->getKey().'/payments', [
            'amount' => '1000.00', 'payment_method' => 'cash',
        ])->assertForbidden();
        $this->patchJson('/api/payments/'.$payment->getKey(), ['amount' => '2000.00'])->assertForbidden();
        foreach (['submit', 'confirm', 'reject'] as $action) {
            $this->patchJson('/api/payments/'.$payment->getKey().'/'.$action)->assertForbidden();
        }
        $this->deleteJson('/api/payments/'.$payment->getKey())->assertForbidden();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('draft', $payment->fresh()->status);
    }
}