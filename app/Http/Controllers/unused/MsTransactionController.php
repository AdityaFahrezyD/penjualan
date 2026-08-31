<?php

namespace App\Http\Controllers\unused;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Services\TransactionService;

class MsTransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function index()
    {
        return response()->json([
            'message' => 'Data transaksi berhasil diambil',
            'data' => $this->transactionService->getAll(),
        ]);
    }

    public function show(string $tr_id)
    {
        return response()->json([
            'message' => 'Data transaksi berhasil diambil',
            'data' => $this->transactionService->getById($tr_id),
        ]);
    }

    public function store(StoreTransactionRequest $request)
    {
        $transaction = $this->transactionService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Transaksi berhasil dibuat',
            'data' => $transaction,
        ], 201);
    }

    public function update(
        UpdateTransactionRequest $request,
        string $tr_id
    ) {
        $transaction = $this->transactionService->update(
            $tr_id,
            $request->validated()
        );

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui',
            'data' => $transaction,
        ]);
    }

    public function complete(string $tr_id)
    {
        return response()->json([
            'message' => 'Transaksi berhasil diselesaikan',
            'data' => $this->transactionService->complete($tr_id),
        ]);
    }

    public function cancel(string $tr_id)
    {
        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan',
            'data' => $this->transactionService->cancel($tr_id),
        ]);
    }

    public function destroy(string $tr_id)
    {
        $this->transactionService->delete($tr_id);

        return response()->json([
            'message' => 'Transaksi berhasil dihapus',
        ]);
    }
}