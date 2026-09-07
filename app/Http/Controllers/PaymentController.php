<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Services\PaymentService;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Menampilkan seluruh Payment
     * berdasarkan Purchase Order.
     */
    public function index(Request $request, string $purchase_order_id)
    {
        $this->authorizeRead($request, $purchase_order_id);
        return response()->json([
            'message' => 'Data Payment berhasil diambil.',
            ...$this->paymentService->getWithSummary($purchase_order_id),
        ]);
    }

    private function authorizeRead(Request $request, string $purchaseOrderId): void
    {
        if ($request->user()->role === 'supplier') {
            $owned = PurchaseOrder::whereKey($purchaseOrderId)
                ->whereHas('purchaseOrderSupplier', fn ($query) => $query->where('user_id', $request->user()->id))
                ->exists();
            abort_unless($owned, 403, 'Anda tidak memiliki akses ke pembayaran PO ini.');
        }
    }
    public function destroy(string $payment_id)
    {
        $this->paymentService->delete($payment_id);
        return response()->json(['message' => 'Pembayaran berhasil dihapus.']);
    }
    /**
     * Menampilkan detail Payment.
     */
    public function show(Request $request, string $payment_id)
    {
        $payment = $this->paymentService->getById($payment_id);
        $this->authorizeRead($request, $payment->purchase_order_id);
        return response()->json([
            'message' => 'Detail Payment berhasil diambil.',
            'data' => $payment,
        ]);
    }

    /**
     * Membuat Payment draft.
     */
    public function store(
        StorePaymentRequest $request,
        string $purchase_order_id
    ) {
        return response()->json([
            'message' => 'Payment berhasil dibuat.',
            'data' => $this->paymentService->create(
                $purchase_order_id,
                $request->user()->id,
                $request->validated()
            ),
        ], 201);
    }

    /**
     * Memperbarui Payment draft.
     */
    public function update(
        UpdatePaymentRequest $request,
        string $payment_id
    ) {
        return response()->json([
            'message' => 'Payment berhasil diperbarui.',
            'data' => $this->paymentService->update(
                $payment_id,
                $request->validated()
            ),
        ]);
    }

    /**
     * Mengirim Payment untuk dikonfirmasi.
     */
    public function submit(string $payment_id)
    {
        return response()->json([
            'message' => 'Payment berhasil dikirim untuk konfirmasi.',
            'data' => $this->paymentService->submit(
                $payment_id
            ),
        ]);
    }

    /**
     * Mengonfirmasi Payment.
     */
    public function confirm(
        Request $request,
        string $payment_id
    ) {
        return response()->json([
            'message' => 'Payment berhasil dikonfirmasi.',
            'data' => $this->paymentService->confirm(
                $payment_id,
                $request->user()->id
            ),
        ]);
    }

    /**
     * Menolak Payment.
     */
    public function reject(string $payment_id)
    {
        return response()->json([
            'message' => 'Payment berhasil ditolak.',
            'data' => $this->paymentService->reject(
                $payment_id
            ),
        ]);
    }
}