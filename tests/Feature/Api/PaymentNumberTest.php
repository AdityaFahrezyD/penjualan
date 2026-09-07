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
use Tests\TestCase;

class PaymentNumberTest extends TestCase
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

    public function test_api_generates_numbers_and_ignores_legacy_client_input(): void
    {
        $url = '/api/purchase-orders/'.$this->po->getKey().'/payments';
        $payload = ['amount' => 1000, 'payment_method' => 'cash'];
        $first = $this->actingAs($this->admin)->postJson($url, $payload)->assertCreated()->json('data.payment_number');
        $second = $this->postJson($url, [...$payload, 'payment_number' => $first])->assertCreated()->json('data.payment_number');
        $this->assertMatchesRegularExpression('/^PAY-\d{8}-[A-Z0-9]{6}$/', $first);
        $this->assertNotSame($first, $second);
        $this->assertSame($first, Payment::where('payment_number', $first)->firstOrFail()->payment_number);
    }

    public function test_payment_detail_is_available_to_admin_accountant_and_supplier(): void
    {
        $payment = app(PaymentService::class)->create($this->po->getKey(), $this->admin->id, [
            'amount' => 1000, 'payment_method' => 'cash',
        ]);
        foreach (['admin', 'akuntan', 'supplier'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/api/payments/'.$payment->getKey())
                ->assertOk()->assertJsonPath('data.status', 'draft');
        }
    }

    public function test_payment_detail_requires_authentication(): void
    {
        $payment = app(PaymentService::class)->create($this->po->getKey(), $this->admin->id, [
            'amount' => 1000, 'payment_method' => 'cash',
        ]);
        $this->getJson('/api/payments/'.$payment->getKey())->assertUnauthorized();
    }
    private function generator(array $numbers): PaymentService
    {
        return new class($numbers) extends PaymentService
        {
            public int $calls = 0;

            public function __construct(private array $numbers) {}

            protected function generatePaymentNumber(): string
            {
                return $this->numbers[$this->calls++] ?? 'PAY-20260905-REPEAT';
            }
        };
    }

    public function test_collision_retries_without_changing_existing_payment(): void
    {
        $old = app(PaymentService::class)->create($this->po->getKey(), $this->admin->id, ['amount' => 1000, 'payment_method' => 'cash']);
        $old->update(['payment_number' => 'HISTORICAL-001']);
        $service = $this->generator(['HISTORICAL-001', 'PAY-20260905-ABC123']);
        $new = $service->create($this->po->getKey(), $this->admin->id, ['amount' => 2000, 'payment_method' => 'cash']);
        $this->assertSame(2, $service->calls);
        $this->assertSame('PAY-20260905-ABC123', $new->payment_number);
        $this->assertSame('HISTORICAL-001', $old->fresh()->payment_number);
    }

    public function test_collision_stops_after_three_attempts(): void
    {
        $old = app(PaymentService::class)->create($this->po->getKey(), $this->admin->id, ['amount' => 1000, 'payment_method' => 'cash']);
        $service = $this->generator(array_fill(0, 3, $old->payment_number));
        try {
            $service->create($this->po->getKey(), $this->admin->id, ['amount' => 2000, 'payment_method' => 'cash']);
            $this->fail('Expected unique constraint failure.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(3, $service->calls);
            $this->assertDatabaseCount('payments', 1);
        }
    }

    public function test_other_unique_errors_are_not_retried(): void
    {
        DB::statement('CREATE UNIQUE INDEX payments_notes_unique ON payments (notes)');
        $data = ['amount' => 1000, 'payment_method' => 'cash', 'notes' => 'same'];
        app(PaymentService::class)->create($this->po->getKey(), $this->admin->id, $data);
        $service = $this->generator(['PAY-20260905-ABC123']);
        try {
            $service->create($this->po->getKey(), $this->admin->id, $data);
            $this->fail('Expected notes unique constraint failure.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, $service->calls);
        }
    }
}
