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
     * Membuat transaksi baru beserta detail.
     *
     * Transaksi dibuat dengan status pending.
     * Stock BELUM bertambah pada tahap ini.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => [
                'required',
                'uuid',
                'exists:suppliers,supplier_id',
            ],

            'tr_date' => [
                'nullable',
                'date',
            ],

            'payment_method' => [
                'required',
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
                'tr_date' => $validated['tr_date']
                    ?? now()->toDateString(),
                'payment_method' => $validated['payment_method'],
                'total' => 0,
                'status' => 'pending',
            ]);

            $total = 0;

            foreach ($validated['details'] as $detail) {

                $subtotal =
                    $detail['item_quant'] *
                    $detail['item_price'];

                $transaction
                    ->mstransactionsDetailTransactions()
                    ->create([
                        'item_id' => $detail['item_id'],
                        'item_quant' => $detail['item_quant'],
                        'item_price' => $detail['item_price'],
                        'subtotal' => $subtotal,
                    ]);

                $total += $subtotal;
            }

            $transaction->update([
                'total' => $total,
            ]);

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
     * Mengubah transaksi yang masih pending.
     *
     * Detail transaksi dapat ditambah, diubah,
     * atau dihapus melalui request yang sama.
     */
    public function update(Request $request, string $tr_id)
    {
        $validated = $request->validate([
            'supplier_id' => [
                'sometimes',
                'required',
                'uuid',
                'exists:suppliers,supplier_id',
            ],

            'tr_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'payment_method' => [
                'sometimes',
                'required',
                'in:cash,cashless',
            ],

            'details' => [
                'sometimes',
                'required',
                'array',
                'min:1',
            ],

            'details.*.tr_detail_id' => [
                'nullable',
                'uuid',
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

        $transaction = DB::transaction(function () use (
            $validated,
            $tr_id
        ) {

            $transaction = msTransaction::with(
                'mstransactionsDetailTransactions'
            )
            ->lockForUpdate()
            ->find($tr_id);

            if (!$transaction) {
                abort(404, 'Transaksi tidak ditemukan');
            }

            if ($transaction->status !== 'pending') {
                abort(
                    422,
                    'Transaksi hanya dapat diubah ketika berstatus pending'
                );
            }

            /*
             * Update header transaksi.
             */
            $transaction->update([
                'supplier_id' => $validated['supplier_id']
                    ?? $transaction->supplier_id,

                'tr_date' => $validated['tr_date']
                    ?? $transaction->tr_date,

                'payment_method' => $validated['payment_method']
                    ?? $transaction->payment_method,
            ]);


            /*
             * Update detail jika details dikirim.
             */
            if (array_key_exists('details', $validated)) {

                $existingDetailIds = $transaction
                    ->mstransactionsDetailTransactions
                    ->pluck('tr_detail_id')
                    ->toArray();

                $incomingDetailIds = [];

                foreach ($validated['details'] as $detail) {

                    $subtotal =
                        $detail['item_quant'] *
                        $detail['item_price'];

                    /*
                     * Detail lama.
                     */
                    if (!empty($detail['tr_detail_id'])) {

                        $detailModel = $transaction
                            ->mstransactionsDetailTransactions()
                            ->where(
                                'tr_detail_id',
                                $detail['tr_detail_id']
                            )
                            ->first();

                        if (!$detailModel) {
                            abort(
                                422,
                                'Detail transaksi tidak valid'
                            );
                        }

                        $detailModel->update([
                            'item_id' => $detail['item_id'],
                            'item_quant' => $detail['item_quant'],
                            'item_price' => $detail['item_price'],
                            'subtotal' => $subtotal,
                        ]);

                        $incomingDetailIds[] =
                            $detailModel->tr_detail_id;
                    }

                    /*
                     * Detail baru.
                     */
                    else {

                        $newDetail = $transaction
                            ->mstransactionsDetailTransactions()
                            ->create([
                                'item_id' => $detail['item_id'],
                                'item_quant' => $detail['item_quant'],
                                'item_price' => $detail['item_price'],
                                'subtotal' => $subtotal,
                            ]);

                        $incomingDetailIds[] =
                            $newDetail->tr_detail_id;
                    }
                }


                /*
                 * Hapus detail yang tidak lagi dikirim
                 * oleh frontend.
                 */
                $detailsToDelete = array_diff(
                    $existingDetailIds,
                    $incomingDetailIds
                );

                if (!empty($detailsToDelete)) {

                    $transaction
                        ->mstransactionsDetailTransactions()
                        ->whereIn(
                            'tr_detail_id',
                            $detailsToDelete
                        )
                        ->delete();
                }


                /*
                 * Hitung ulang total dari database.
                 */
                $total = $transaction
                    ->mstransactionsDetailTransactions()
                    ->sum('subtotal');

                $transaction->update([
                    'total' => $total,
                ]);
            }

            return $transaction->fresh([
                'mstransactionsSuppliers',
                'mstransactionsDetailTransactions.itemDetailTransactions',
            ]);
        });

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui',
            'data' => $transaction,
        ]);
    }


    /**
     * Menyelesaikan transaksi.
     *
     * Hanya transaksi pending yang dapat diselesaikan.
     * Stock item bertambah pada tahap ini.
     */
    public function complete(string $tr_id)
    {
        $transaction = DB::transaction(function () use ($tr_id) {

            $transaction = msTransaction::with(
                'mstransactionsDetailTransactions'
            )
            ->lockForUpdate()
            ->find($tr_id);

            if (!$transaction) {
                abort(404, 'Transaksi tidak ditemukan');
            }

            if ($transaction->status !== 'pending') {
                abort(
                    422,
                    'Transaksi hanya dapat diselesaikan ketika berstatus pending'
                );
            }

            if (
                $transaction
                    ->mstransactionsDetailTransactions
                    ->isEmpty()
            ) {
                abort(
                    422,
                    'Transaksi tidak memiliki detail'
                );
            }

            foreach (
                $transaction->mstransactionsDetailTransactions
                as $detail
            ) {

                $item = Item::lockForUpdate()
                    ->find($detail->item_id);

                if (!$item) {
                    abort(
                        422,
                        'Item pada detail transaksi tidak ditemukan'
                    );
                }

                $item->increment(
                    'stock',
                    $detail->item_quant
                );
            }

            /*
             * Pastikan total dihitung ulang
             * sebelum transaksi diselesaikan.
             */
            $total = $transaction
                ->mstransactionsDetailTransactions()
                ->sum('subtotal');

            $transaction->update([
                'total' => $total,
                'status' => 'completed',
            ]);

            return $transaction;
        });

        $transaction->load([
            'mstransactionsSuppliers',
            'mstransactionsDetailTransactions.itemDetailTransactions',
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil diselesaikan',
            'data' => $transaction,
        ]);
    }


    /**
     * Membatalkan transaksi.
     *
     * Hanya transaksi pending yang dapat dibatalkan.
     * Stock tidak berubah karena stock baru ditambahkan
     * ketika transaksi completed.
     */
    public function cancel(string $tr_id)
    {
        $transaction = msTransaction::find($tr_id);

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan',
            ], 404);
        }

        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' =>
                    'Transaksi hanya dapat dibatalkan ketika berstatus pending',
            ], 422);
        }

        $transaction->update([
            'status' => 'cancelled',
        ]);

        $transaction->load([
            'mstransactionsSuppliers',
            'mstransactionsDetailTransactions.itemDetailTransactions',
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan',
            'data' => $transaction,
        ]);
    }


    /**
     * Menghapus transaksi.
     *
     * Hanya transaksi pending yang dapat dihapus.
     *
     * Transaksi completed/cancelled tetap disimpan
     * sebagai histori.
     */
    public function destroy(string $tr_id)
    {
        $transaction = msTransaction::find($tr_id);

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan',
            ], 404);
        }

        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' =>
                    'Transaksi completed atau cancelled tidak dapat dihapus',
            ], 422);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaksi berhasil dihapus',
        ]);
    }
}