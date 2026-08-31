<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\RequestSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestSupplierService
{
    /**
     * Menampilkan seluruh Request Supplier
     * berdasarkan Purchase Request.
     */
    public function getByPurchaseRequest(
        string $purchase_request_id
    )
    {
        return RequestSupplier::with([
            'requestSupplierSupplier',
            'requestSupplierSupplierQuotation',
        ])
            ->where(
                'purchase_request_id',
                $purchase_request_id
            )
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Membuat dan mengirim Request Supplier
     * ke beberapa supplier sekaligus.
     */
    public function createMultiple(
        string $purchase_request_id,
        array $data
    ) {
        return DB::transaction(function () use (
            $purchase_request_id,
            $data
        ) {
            $purchaseRequest = PurchaseRequest::findOrFail(
                $purchase_request_id
            );

            /*
             * Request Supplier hanya dapat dibuat
             * ketika PR masih berstatus draft.
             */
            if ($purchaseRequest->status !== 'draft') {
                throw ValidationException::withMessages([
                    'purchase_request' => [
                        'Request Supplier hanya dapat dibuat ketika Purchase Request berstatus draft.',
                    ],
                ]);
            }

            $supplierIds = $data['supplier_ids'];

            /*
             * Pastikan tidak ada supplier yang sudah
             * terdaftar pada Purchase Request ini.
             */
            $existingSupplierIds = RequestSupplier::where(
                'purchase_request_id',
                $purchase_request_id
            )
                ->whereIn('supplier_id', $supplierIds)
                ->pluck('supplier_id')
                ->toArray();

            if (!empty($existingSupplierIds)) {
                throw ValidationException::withMessages([
                    'supplier_ids' => [
                        'Satu atau lebih supplier sudah memiliki Request Supplier pada Purchase Request ini.',
                    ],
                ]);
            }

            /*
             * Buat Request Supplier untuk seluruh supplier.
             */
            $requestSuppliers = [];

            foreach ($supplierIds as $supplier_id) {
                $requestSuppliers[] = RequestSupplier::create([
                    'purchase_request_id' => $purchase_request_id,
                    'supplier_id' => $supplier_id,
                    'status' => 'pending',
                    'sent_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            /*
             * Setelah request benar-benar dibuat/dikirim,
             * status Purchase Request berubah.
             */
            $purchaseRequest->update([
                'status' => 'waiting_supplier',
            ]);

            collect($requestSuppliers)->each->load('requestSupplierSupplier');
            return collect($requestSuppliers);
        });
    }

    public function respond(
        string $request_supplier_id,
        string $supplier_id,
        array $data
    ): RequestSupplier {
        $requestSupplier = RequestSupplier::where(
            'request_supplier_id',
            $request_supplier_id
        )
            ->where(
                'supplier_id',
                $supplier_id
            )
            ->firstOrFail();

        /*
        * Supplier hanya dapat merespons request
        * yang masih pending.
        */
        if ($requestSupplier->status !== 'pending') {
            throw ValidationException::withMessages([
                'request_supplier' => [
                    'Request Supplier ini sudah mendapatkan respons.',
                ],
            ]);
        }

        $updateData = [
            'status' => $data['status'],
            'responded_at' => now(),
        ];

        if ($data['status'] === 'rejected') {
            $updateData['rejection_reason'] =
                $data['rejection_reason'];
        }

        $requestSupplier->update($updateData);

        return $requestSupplier->fresh()->load([
            'requestSupplierPurchaseRequest',
            'requestSupplierSupplier',
        ]);
    }
}