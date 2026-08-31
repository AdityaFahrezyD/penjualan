<?php

namespace App\Services\unused;

use App\Models\Item;
use App\Models\msTransaction;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    private array $relations = [
        'mstransactionsSuppliers',
        'mstransactionsDetailTransactions.itemDetailTransactions',
    ];

    public function getAll()
    {
        return msTransaction::with($this->relations)
            ->orderByDesc('tr_date')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getById(string $tr_id): msTransaction
    {
        $transaction = msTransaction::with($this->relations)
            ->find($tr_id);

        if (!$transaction) {
            abort(404, 'Transaksi tidak ditemukan');
        }

        return $transaction;
    }

    public function create(array $data): msTransaction
    {
        return DB::transaction(function () use ($data) {

            /*
             * Header transaksi dibuat di sini.
             * Jadi frontend tidak perlu membuat msTransaction
             * terlebih dahulu.
             */
            $transaction = msTransaction::create([
                'supplier_id' => $data['supplier_id'],

                'tr_date' => $data['tr_date']
                    ?? now()->toDateString(),

                'payment_method' => $data['payment_method'],

                'total' => 0,

                'status' => 'pending',
            ]);

            $total = 0;

            foreach ($data['details'] as $detail) {

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

            return $transaction->fresh($this->relations);
        });
    }

    public function update(
        string $tr_id,
        array $data
    ): msTransaction {

        return DB::transaction(function () use (
            $tr_id,
            $data
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
             * Update header.
             */
            $transaction->update([
                'supplier_id' =>
                    $data['supplier_id']
                    ?? $transaction->supplier_id,

                'tr_date' =>
                    $data['tr_date']
                    ?? $transaction->tr_date,

                'payment_method' =>
                    $data['payment_method']
                    ?? $transaction->payment_method,
            ]);

            /*
             * Jika detail tidak dikirim,
             * cukup update header.
             */
            if (!array_key_exists('details', $data)) {
                return $transaction->fresh($this->relations);
            }

            $existingDetailIds = $transaction
                ->mstransactionsDetailTransactions
                ->pluck('tr_detail_id')
                ->toArray();

            $incomingDetailIds = [];

            foreach ($data['details'] as $detail) {

                $subtotal =
                    $detail['item_quant'] *
                    $detail['item_price'];

                /*
                 * Update detail lama.
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
                        'item_id' =>
                            $detail['item_id'],

                        'item_quant' =>
                            $detail['item_quant'],

                        'item_price' =>
                            $detail['item_price'],

                        'subtotal' =>
                            $subtotal,
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
                            'item_id' =>
                                $detail['item_id'],

                            'item_quant' =>
                                $detail['item_quant'],

                            'item_price' =>
                                $detail['item_price'],

                            'subtotal' =>
                                $subtotal,
                        ]);

                    $incomingDetailIds[] =
                        $newDetail->tr_detail_id;
                }
            }

            /*
             * Hapus detail yang sudah tidak dikirim.
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
             * Hitung ulang total berdasarkan database.
             */
            $total = $transaction
                ->mstransactionsDetailTransactions()
                ->sum('subtotal');

            $transaction->update([
                'total' => $total,
            ]);

            return $transaction->fresh($this->relations);
        });
    }

    public function complete(string $tr_id): msTransaction
    {
        return DB::transaction(function () use ($tr_id) {

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

            /*
             * Tambahkan stock setiap item.
             */
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
             * Hitung ulang total.
             */
            $total = $transaction
                ->mstransactionsDetailTransactions()
                ->sum('subtotal');

            $transaction->update([
                'total' => $total,
                'status' => 'completed',
            ]);

            return $transaction->fresh($this->relations);
        });
    }

    public function cancel(string $tr_id): msTransaction
    {
        $transaction = msTransaction::find($tr_id);

        if (!$transaction) {
            abort(404, 'Transaksi tidak ditemukan');
        }

        if ($transaction->status !== 'pending') {
            abort(
                422,
                'Transaksi hanya dapat dibatalkan ketika berstatus pending'
            );
        }

        $transaction->update([
            'status' => 'cancelled',
        ]);

        return $transaction->fresh($this->relations);
    }

    public function delete(string $tr_id): void
    {
        $transaction = msTransaction::find($tr_id);

        if (!$transaction) {
            abort(404, 'Transaksi tidak ditemukan');
        }

        if ($transaction->status !== 'pending') {
            abort(
                422,
                'Transaksi completed atau cancelled tidak dapat dihapus'
            );
        }

        $transaction->delete();
    }
}