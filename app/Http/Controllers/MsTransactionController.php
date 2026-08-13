<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\msTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MsTransactionController extends Controller
{
    /**
     * Menampilkan semua transaksi.
     */
    public function index()
    {
        $transactions = msTransaction::with([
            'mstransactionsSuppliers',
            'mstransactionsDetailTransactions.itemDetailTransactions',
        ])
        ->orderByDesc('tr_date')
        ->orderByDesc('created_at')
        ->get();

        return response()->json([
            'message' => 'Data transaksi berhasil diambil',
            'data' => $transactions,
        ]);
    }

    /**
     * Menampilkan satu transaksi beserta detail.
     */
    public function show(string $tr_id)
    {
        $transaction = msTransaction::with([
            'mstransactionsSuppliers',
            'mstransactionsDetailTransactions.itemDetailTransactions',
        ])->find($tr_id);

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Data transaksi berhasil diambil',
            'data' => $transaction,
        ]);
    }

    /**
     * Membuat transaksi beserta seluruh detail.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => [
                'required',
                'uuid',
                'exists:suppliers,supplier_id',
            ],

            'payment_method' => [
                'nullable',
                'in:cash,cashless',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.item_id' => [
                'required',
                'uuid',
                'exists:items,item_id',
            ],

            'details.*.item_quant' => [
                'required',
                'integer',
                'min:1',
            ],

            'details.*.item_price' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        $transaction = DB::transaction(function () use ($validated) {

            $transaction = msTransaction::create([
                'supplier_id' => $validated['supplier_id'],
                'tr_date' => now()->toDateString(),
                'payment_method' => $validated['payment_method'] ?? null,
            ]);

            foreach ($validated['details'] as $detail) {

                $item = Item::lockForUpdate()
                    ->find($detail['item_id']);

                $subtotal =
                    $detail['item_quant'] * $detail['item_price'];

                $transaction
                    ->mstransactionsDetailTransactions()
                    ->create([
                        'item_id' => $item->item_id,
                        'item_quant' => $detail['item_quant'],
                        'item_price' => $detail['item_price'],
                        'subtotal' => $subtotal,
                    ]);

                $item->increment(
                    'stock',
                    $detail['item_quant']
                );
            }

            return $transaction;
        });

        $transaction->load([
            'mstransactionsSuppliers',
            'mstransactionsDetailTransactions.itemDetailTransactions',
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil dibuat',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Menghapus transaksi.
     */
    public function destroy(string $tr_id)
    {
        $transaction = msTransaction::find($tr_id);

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan',
            ], 404);
        }

        DB::transaction(function () use ($transaction) {

            $details = $transaction
                ->mstransactionsDetailTransactions()
                ->get();

            foreach ($details as $detail) {
                $item = Item::lockForUpdate()
                    ->find($detail->item_id);

                if ($item) {
                    $item->decrement(
                        'stock',
                        $detail->item_quant
                    );
                }
            }

            $transaction->delete();
        });

        return response()->json([
            'message' => 'Transaksi berhasil dihapus',
        ]);
    }
}