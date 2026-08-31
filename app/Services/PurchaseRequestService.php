<?php

namespace App\Services;

use App\Models\DetailPurchaseRequest;
use App\Models\PurchaseRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function getAll()
    {
        return PurchaseRequest::with([
            'purchaseRequestUser',
            'purchaseRequestDetailPurchaseRequest.detailPurchaseRequestItem',
        ])
            ->orderByDesc('request_date')
            ->get();
    }

    public function getById(string $purchase_request_id): PurchaseRequest 
    {
        return PurchaseRequest::with([
            'purchaseRequestUser',
            'purchaseRequestDetailPurchaseRequest.detailPurchaseRequestItem',
        ])
            ->findOrFail($purchase_request_id);
    }

    /**
     * Membuat Purchase Request beserta seluruh detail.
     */
    public function create(array $data,string $user_id): PurchaseRequest 
    {
        return DB::transaction(function () use ($data, $user_id
        ) {
            $purchaseRequest = PurchaseRequest::create([
                'request_number' => $this->generateRequestNumber(),
                'created_by' => $user_id,
                'request_date' => $data['request_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);

            foreach ($data['details'] as $detail) {
                DetailPurchaseRequest::create([
                    'detail_purchase_request_id' => (string) Str::uuid(),
                    'purchase_request_id' =>
                        $purchaseRequest->purchase_request_id,
                    'item_id' => $detail['item_id'],
                    'quantity' => $detail['quantity'],
                    'notes' => $detail['notes'] ?? null,
                ]);
            }

            return $purchaseRequest->load([
                'purchaseRequestUser',
                'purchaseRequestDetailPurchaseRequest.detailPurchaseRequestItem',
            ]);
        });
    }

    /**
     * Menambahkan satu detail ke Purchase Request.
     */
    public function addDetail(string $purchase_request_id,array $data): DetailPurchaseRequest 
    {
        $purchaseRequest = PurchaseRequest::findOrFail(
            $purchase_request_id
        );

        $this->ensureDraft($purchaseRequest);

        $itemExists = DetailPurchaseRequest::where(
            'purchase_request_id',
            $purchase_request_id
        )
            ->where('item_id', $data['item_id'])
            ->exists();

        if ($itemExists) {
            throw ValidationException::withMessages([
                'item_id' => [
                    'Item tersebut sudah ada dalam Purchase Request ini.',
                ],
            ]);
        }

        return DetailPurchaseRequest::create([
            'purchase_request_id' => $purchase_request_id,
            'item_id' => $data['item_id'],
            'quantity' => $data['quantity'],
            'notes' => $data['notes'] ?? null,
        ])->load('item');
    }

    /**
     * Mengubah satu detail Purchase Request.
     *
     * Header Purchase Request tidak disentuh.
     */
    public function updateDetail(string $purchase_request_id,string $detail_purchase_request_id,array $data): DetailPurchaseRequest 
    {
        $purchaseRequest = PurchaseRequest::findOrFail(
            $purchase_request_id
        );

        $this->ensureDraft($purchaseRequest);

        $detail = DetailPurchaseRequest::where(
            'detail_purchase_request_id',
            $detail_purchase_request_id
        )
            ->where(
                'purchase_request_id',
                $purchase_request_id
            )
            ->firstOrFail();

        /*
         * Jika item diubah, pastikan item tersebut
         * belum digunakan oleh detail lain dalam PR ini.
         */
        if (
            isset($data['item_id']) &&
            $data['item_id'] !== $detail->item_id
        ) {
            $itemExists = DetailPurchaseRequest::where(
                'purchase_request_id',
                $purchase_request_id
            )
                ->where('item_id', $data['item_id'])
                ->where(
                    'detail_purchase_request_id',
                    '!=',
                    $detail_purchase_request_id
                )
                ->exists();

            if ($itemExists) {
                throw ValidationException::withMessages([
                    'item_id' => [
                        'Item tersebut sudah ada dalam Purchase Request ini.',
                    ],
                ]);
            }
        }

        $detail->update($data);

        return $detail->fresh()->load('item');
    }

    /**
     * Menghapus satu detail Purchase Request.
     *
     * Header tidak dihapus.
     */
    public function deleteDetail(
        string $purchase_request_id,
        string $detail_purchase_request_id
    ): void {
        $purchaseRequest = PurchaseRequest::findOrFail(
            $purchase_request_id
        );

        $this->ensureDraft($purchaseRequest);

        $detail = DetailPurchaseRequest::where(
            'detail_purchase_request_id',
            $detail_purchase_request_id
        )
            ->where(
                'purchase_request_id',
                $purchase_request_id
            )
            ->firstOrFail();

        /*
         * PR harus tetap memiliki minimal satu detail.
         */
        $detailCount = DetailPurchaseRequest::where(
            'purchase_request_id',
            $purchase_request_id
        )->count();

        if ($detailCount <= 1) {
            throw ValidationException::withMessages([
                'detail' => [
                    'Purchase Request harus memiliki minimal satu detail item.',
                ],
            ]);
        }

        try {
            $detail->delete();
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'detail' => [
                    'Detail tidak dapat dihapus karena sudah digunakan dalam proses berikutnya.',
                ],
            ]);
        }
    }

    /**
     * Memastikan Purchase Request masih dapat diedit.
     */
    private function ensureDraft(PurchaseRequest $purchaseRequest
    ): void {
        if ($purchaseRequest->status !== 'draft') {
            throw ValidationException::withMessages([
                'purchase_request' => [
                    'Purchase Request hanya dapat diubah ketika berstatus draft.',
                ],
            ]);
        }
    }

    /**
     * Generate nomor Purchase Request.
     */
    private function generateRequestNumber(): string
    {
        return 'PR-'
            . now()->format('Ymd')
            . '-'
            . strtoupper(Str::random(6));
    }
}