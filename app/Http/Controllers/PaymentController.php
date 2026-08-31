<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Services\PaymentService;
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
    public function index(string $purchase_order_id)
    {
        return response()->json([
            'message' => 'Data Payment berhasil diambil.',
            'data' => $this->paymentService
                ->getByPurchaseOrder($purchase_order_id),
        ]);
    }

    /**
     * Menampilkan detail Payment.
     */
    public function show(string $payment_id)
    {
        return response()->json([
            'message' => 'Detail Payment berhasil diambil.',
            'data' => $this->paymentService
                ->getById($payment_id),
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