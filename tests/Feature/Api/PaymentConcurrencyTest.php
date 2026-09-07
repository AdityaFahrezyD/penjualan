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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    private User $admin;
    private PurchaseOrder $po;
    private array $connection;

    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('PAYMENT_TEST_MYSQL_PORT')) {
            $this->markTestSkipped('Set PAYMENT_TEST_MYSQL_PORT and PAYMENT_TEST_MYSQL_DATABASE for an isolated MySQL database.');
        }
        $database = getenv('PAYMENT_TEST_MYSQL_DATABASE');
        if (! $database || ! str_starts_with($database, 'payment_test_')) {
            throw new \RuntimeException('Concurrency tests require a dedicated payment_test_ database.');
        }
        $this->connection = [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => getenv('PAYMENT_TEST_MYSQL_PORT'),
            'database' => $database, 'username' => 'root', 'password' => '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ];
        config(['database.default' => 'payment_concurrency', 'database.connections.payment_concurrency' => $this->connection]);
        DB::purge('payment_concurrency');
        $this->artisan('migrate', ['--database' => 'payment_concurrency', '--force' => true])->assertExitCode(0);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'supplier']);
        $supplier = Supplier::create(['user_id' => $user->id, 'supplier_name' => 'Test', 'phone' => '08123', 'address' => 'Test']);
        $pr = PurchaseRequest::create(['request_number' => 'PR-'.Str::random(12), 'created_by' => $this->admin->id, 'request_date' => today(), 'status' => 'po_created']);
        $invitation = RequestSupplier::create(['purchase_request_id' => $pr->getKey(), 'supplier_id' => $supplier->getKey(), 'status' => 'accepted']);
        $quotation = SupplierQuotation::create(['quotation_number' => 'QR-'.Str::random(12), 'request_supplier_id' => $invitation->getKey(), 'quotation_date' => today(), 'status' => 'po_created']);
        $this->po = PurchaseOrder::create(['po_number' => 'PO-'.Str::random(12), 'purchase_request_id' => $pr->getKey(), 'supplier_id' => $supplier->getKey(), 'supplier_quotation_id' => $quotation->getKey(), 'created_by' => $this->admin->id, 'order_date' => today(), 'total' => '100000.00']);
    }

    private function pending(): Payment
    {
        $service = app(PaymentService::class);
        $payment = $service->create($this->po->getKey(), $this->admin->id, ['amount' => '60000.00', 'payment_method' => 'cash']);
        return $service->submit($payment->getKey());
    }

    private function compete(array $operations): array
    {
        $script = tempnam(sys_get_temp_dir(), 'payment-worker-');
        file_put_contents($script, <<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$args = json_decode(base64_decode($argv[1]), true);
config(['database.default' => 'payment_concurrency', 'database.connections.payment_concurrency' => $args['connection']]);
Illuminate\Support\Facades\DB::purge('payment_concurrency');
echo "ready\n";
flush();
try {
    $service = app(App\Services\PaymentService::class);
    if ($args['operation'] === 'confirm') $service->confirm($args['id'], $args['user']);
    else $service->delete($args['id']);
    echo "ok\n";
} catch (Illuminate\Validation\ValidationException $e) {
    echo "422\n";
} catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    echo "404\n";
}
PHP);
        $workers = [];
        DB::beginTransaction();
        try {
            PurchaseOrder::lockForUpdate()->findOrFail($this->po->getKey());
            foreach ($operations as [$operation, $id]) {
                $args = base64_encode(json_encode(['operation' => $operation, 'id' => $id, 'user' => $this->admin->id, 'connection' => $this->connection]));
                $process = new Process([PHP_BINARY, $script, $args], base_path(), null, null, 30);
                $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 15;
            do {
                $ready = count(array_filter($workers, fn ($worker) => str_contains($worker->getOutput(), 'ready')));
                if ($ready === count($workers)) break;
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(count($workers), $ready, 'Workers must overlap while PO is locked.');
            DB::commit();
            return array_map(function ($worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $lines = explode("\n", trim($worker->getOutput()));
                return end($lines);
            }, $workers);
        } finally {
            if (DB::transactionLevel()) DB::rollBack();
            foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop();
            unlink($script);
        }
    }

    public function test_concurrent_confirmations_cannot_exceed_po_total(): void
    {
        $first = $this->pending();
        $second = $this->pending();
        $results = $this->compete([['confirm', $first->getKey()], ['confirm', $second->getKey()]]);
        sort($results);
        $this->assertSame(['422', 'ok'], $results);
        $summary = app(PaymentService::class)->getWithSummary($this->po->getKey())['meta']['payment_summary'];
        $this->assertSame('60000.00', $summary['confirmed_amount']);
        $this->assertSame('40000.00', $summary['remaining_amount']);
    }

    public function test_confirm_and_delete_cannot_remove_a_confirmed_payment(): void
    {
        $payment = $this->pending();
        $results = $this->compete([['confirm', $payment->getKey()], ['delete', $payment->getKey()]]);
        $current = Payment::find($payment->getKey());
        if ($current) {
            $this->assertSame('confirmed', $current->status);
            $this->assertSame(['ok', '422'], $results);
        } else {
            $this->assertSame(['404', 'ok'], $results);
            $this->assertSame('unpaid', $this->po->fresh()->payment_status);
        }
    }
}