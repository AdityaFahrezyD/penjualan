<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Menampilkan payment berdasarkan Purchase Order.
     */
    public function getByPurchaseOrder(
        string $purchase_order_id
    )
    {
        return Payment::with([
            'paymentUser',
            'paymentUserConfirm',
        ])
            ->where(
                'purchase_order_id',
                $purchase_order_id
            )
            ->latest()
            ->get();
    }

    /**
     * Menampilkan detail Payment.
     */
    public function getById(string $payment_id): Payment
    {
        return Payment::with([
            'paymentPurchaseOrder',
            'paymentUser',
            'paymentUserConfirm',
        ])->findOrFail($payment_id);
    }

    /**
     * Membuat Payment draft.
     */
    public function create(
        string $purchase_order_id,
        string $user_id,
        array $data
    ): Payment {
        $purchaseOrder = PurchaseOrder::findOrFail(
            $purchase_order_id
        );

        if (
            in_array(
                $purchaseOrder->status,
                ['cancelled', 'failed']
            )
        ) {
            throw ValidationException::withMessages([
                'purchase_order' => [
                    'Payment tidak dapat dibuat untuk Purchase Order ini.',
                ],
            ]);
        }

        return Payment::create([
            'payment_number' =>
                $data['payment_number'],

            'purchase_order_id' =>
                $purchase_order_id,

            'created_by' =>
                $user_id,

            'amount' =>
                $data['amount'],

            'payment_method' =>
                $data['payment_method'],

            'payment_date' =>
                $data['payment_date'] ?? null,

            'status' =>
                'draft',

            'notes' =>
                $data['notes'] ?? null,
        ]);
    }

    /**
     * Update Payment selama masih draft.
     */
    public function update(
        string $payment_id,
        array $data
    ): Payment {
        $payment = Payment::findOrFail($payment_id);

        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages([
                'payment' => [
                    'Payment hanya dapat diperbarui ketika berstatus draft.',
                ],
            ]);
        }

        $payment->update($data);

        return $payment->fresh();
    }

    /**
     * Mengirim payment untuk dikonfirmasi.
     */
    public function submit(
        string $payment_id
    ): Payment {
        $payment = Payment::findOrFail($payment_id);

        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages([
                'payment' => [
                    'Hanya Payment draft yang dapat dikirim.',
                ],
            ]);
        }

        $payment->update([
            'status' => 'waiting_confirmation',
        ]);

        return $payment->fresh();
    }

    /**
     * Konfirmasi Payment.
     */
    public function confirm(
        string $payment_id,
        string $confirmed_by
    ): Payment {
        return DB::transaction(function () use (
            $payment_id,
            $confirmed_by
        ) {
            $payment = Payment::with(
                'paymentPurchaseOrder'
            )->findOrFail($payment_id);

            if (
                $payment->status !== 'waiting_confirmation'
            ) {
                throw ValidationException::withMessages([
                    'payment' => [
                        'Payment ini tidak dapat dikonfirmasi.',
                    ],
                ]);
            }

            $purchaseOrder =
                $payment->paymentPurchaseOrder;

            /*
             * Pastikan total pembayaran tidak
             * melebihi total PO.
             */
            $confirmedAmount = Payment::where(
                'purchase_order_id',
                $purchaseOrder->purchase_order_id
            )
                ->where('status', 'confirmed')
                ->sum('amount');

            $remainingAmount =
                $purchaseOrder->total
                - $confirmedAmount;

            if ($payment->amount > $remainingAmount) {
                throw ValidationException::withMessages([
                    'amount' => [
                        'Jumlah pembayaran melebihi sisa tagihan Purchase Order.',
                    ],
                ]);
            }

            /*
             * Konfirmasi payment.
             */
            $payment->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $confirmed_by,
            ]);

            /*
             * Hitung ulang payment status PO.
             */
            $this->recalculatePaymentStatus(
                $purchaseOrder
            );

            return $payment->fresh();
        });
    }

    /**
     * Reject Payment.
     */
    public function reject(string $payment_id): Payment
    {
        $payment = Payment::findOrFail($payment_id);

        if (
            $payment->status !== 'waiting_confirmation'
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'Payment ini tidak dapat ditolak.',
                ],
            ]);
        }

        $payment->update([
            'status' => 'rejected',
        ]);

        return $payment->fresh();
    }

    /**
     * Menghitung status pembayaran Purchase Order.
     */
    private function recalculatePaymentStatus(
        PurchaseOrder $purchaseOrder
    ): void {
        $confirmedAmount = Payment::where(
            'purchase_order_id',
            $purchaseOrder->purchase_order_id
        )
            ->where('status', 'confirmed')
            ->sum('amount');

        if ($confirmedAmount <= 0) {
            $status = 'unpaid';
        } elseif ($confirmedAmount < $purchaseOrder->total) {
            $status = 'partially_paid';
        } else {
            $status = 'paid';
        }

        $purchaseOrder->update([
            'payment_status' => $status,
        ]);
    }
}