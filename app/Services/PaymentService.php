<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function getByPurchaseOrder(string $purchase_order_id)
    {
        return Payment::with(['paymentUser', 'paymentUserConfirm'])
            ->where('purchase_order_id', $purchase_order_id)->latest()->get();
    }

    public function getWithSummary(string $purchase_order_id): array
    {
        return DB::transaction(function () use ($purchase_order_id) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchase_order_id);
            return [
                'data' => $this->getByPurchaseOrder($purchase_order_id),
                'meta' => ['payment_summary' => $this->summary($po)],
            ];
        });
    }

    public function getById(string $payment_id): Payment
    {
        return Payment::with(['paymentPurchaseOrder', 'paymentUser', 'paymentUserConfirm'])->findOrFail($payment_id);
    }

    // Integer minor units avoid floating-point comparisons for DECIMAL(15,2).
    private function cents(mixed $amount): int
    {
        $text = (string) $amount;
        if (! preg_match('/^-?\d{1,13}(?:\.\d{1,2})?$/D', $text)) {
            throw ValidationException::withMessages(['amount' => ['Nominal harus berupa angka dengan maksimal dua angka desimal.']]);
        }
        $negative = str_starts_with($text, '-');
        $parts = explode('.', ltrim($text, '-'));
        $value = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        return $negative ? -$value : $value;
    }

    private function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    private function confirmedCents(PurchaseOrder $po): int
    {
        // A locking read also sees the latest committed values under MySQL REPEATABLE READ.
        return Payment::where('purchase_order_id', $po->getKey())->where('status', 'confirmed')
            ->lockForUpdate()->get(['amount'])->sum(fn ($payment) => $this->cents($payment->amount));
    }

    private function summary(PurchaseOrder $po): array
    {
        $total = $this->cents($po->total);
        $confirmed = $this->confirmedCents($po);
        return [
            'total_amount' => $this->decimal($total),
            'confirmed_amount' => $this->decimal($confirmed),
            'remaining_amount' => $this->decimal($total - $confirmed),
        ];
    }

    private function validateAmount(PurchaseOrder $po, mixed $amount): string
    {
        $value = $this->cents($amount);
        if ($value <= 0) {
            throw ValidationException::withMessages(['amount' => ['Nominal pembayaran harus lebih dari 0.']]);
        }
        if ($value > $this->cents($po->total) - $this->confirmedCents($po)) {
            throw ValidationException::withMessages(['amount' => ['Jumlah pembayaran melebihi sisa tagihan Purchase Order.']]);
        }
        return $this->decimal($value);
    }

    // Always lock PO before payment, including reject/delete, to serialize competing actions.
    private function withLockedPayment(string $id, callable $action): mixed
    {
        $poId = Payment::findOrFail($id)->purchase_order_id;
        return DB::transaction(function () use ($id, $poId, $action) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);
            $payment = Payment::where('purchase_order_id', $poId)->lockForUpdate()->findOrFail($id);
            return $action($payment, $po);
        });
    }

    private function requireStatus(Payment $payment, array $statuses, string $message): void
    {
        if (! in_array($payment->status, $statuses, true)) {
            throw ValidationException::withMessages(['payment' => [$message]]);
        }
    }

    public function create(string $purchase_order_id, string $user_id, array $data): Payment
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($purchase_order_id, $user_id, $data) {
                    $po = PurchaseOrder::lockForUpdate()->findOrFail($purchase_order_id);
                    if (in_array($po->status, ['cancelled', 'failed'], true)) {
                        throw ValidationException::withMessages(['purchase_order' => ['Payment tidak dapat dibuat untuk Purchase Order ini.']]);
                    }
                    $amount = $this->validateAmount($po, $data['amount']);
                    return Payment::create([
                        'payment_number' => $this->generatePaymentNumber(),
                        'purchase_order_id' => $purchase_order_id,
                        'created_by' => $user_id,
                        'amount' => $amount,
                        'payment_method' => $data['payment_method'],
                        'payment_date' => $data['payment_date'] ?? null,
                        'status' => 'draft',
                        'notes' => $data['notes'] ?? null,
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                $message = $exception->getPrevious()?->getMessage() ?? '';
                if ($attempt === 2 || (! str_contains($message, 'payments_payment_number_unique')
                    && ! str_contains($message, 'payments.payment_number'))) {
                    throw $exception;
                }
            }
        }
    }

    protected function generatePaymentNumber(): string
    {
        return 'PAY-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    public function update(string $payment_id, array $data): Payment
    {
        return $this->withLockedPayment($payment_id, function ($payment, $po) use ($data) {
            $this->requireStatus($payment, ['draft'], 'Payment hanya dapat diperbarui ketika berstatus draft.');
            if (array_key_exists('amount', $data)) {
                $data['amount'] = $this->validateAmount($po, $data['amount']);
            }
            $payment->update($data);
            return $payment->fresh();
        });
    }

    public function submit(string $payment_id): Payment
    {
        return $this->withLockedPayment($payment_id, function ($payment, $po) {
            $this->requireStatus($payment, ['draft'], 'Hanya Payment draft yang dapat dikirim.');
            $this->validateAmount($po, $payment->amount);
            $payment->update(['status' => 'waiting_confirmation']);
            return $payment->fresh();
        });
    }

    public function confirm(string $payment_id, string $confirmed_by): Payment
    {
        return $this->withLockedPayment($payment_id, function ($payment, $po) use ($confirmed_by) {
            $this->requireStatus($payment, ['waiting_confirmation'], 'Payment ini tidak dapat dikonfirmasi.');
            $this->validateAmount($po, $payment->amount);
            $payment->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmed_by' => $confirmed_by]);
            $confirmed = $this->confirmedCents($po);
            $po->update(['payment_status' => $confirmed >= $this->cents($po->total) ? 'paid' : 'partially_paid']);
            return $payment->fresh();
        });
    }

    public function reject(string $payment_id): Payment
    {
        return $this->withLockedPayment($payment_id, function ($payment) {
            $this->requireStatus($payment, ['waiting_confirmation'], 'Payment ini tidak dapat ditolak.');
            $payment->update(['status' => 'rejected']);
            return $payment->fresh();
        });
    }

    public function delete(string $payment_id): void
    {
        $this->withLockedPayment($payment_id, function ($payment) {
            $this->requireStatus($payment, ['draft', 'waiting_confirmation', 'rejected'], 'Pembayaran terkonfirmasi tidak dapat dihapus.');
            $payment->delete();
        });
    }
}