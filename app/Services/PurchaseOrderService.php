<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RequestSupplier;
use App\Models\SupplierQuotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    /**
     * Menampilkan seluruh Purchase Order.
     */
    public function getAll()
    {
        return PurchaseOrder::with([
            'purchaseOrderPurchaseRequest',
            'purchaseOrderSupplier',
            'purchaseOrderSupplierQuotation',
            'purchaseOrderUser',
        ])
            ->latest()
            ->get();
    }

    /**
     * Menampilkan detail Purchase Order.
     */
    public function getById(
        string $purchase_order_id
    ): PurchaseOrder {
        return PurchaseOrder::with([
            'purchaseOrderPurchaseRequest',
            'purchaseOrderSupplier',
            'purchaseOrderSupplierQuotation',
            'purchaseOrderUser',
            'purchaseOrderDetailPurchaseOrder.detailPurchaseOrderItem',
            'purchaseOrderPayment',
        ])->findOrFail($purchase_order_id);
    }

    /**
     * Membuat Purchase Order dari Supplier Quotation
     * yang dipilih.
     */
    public function create(
        string $supplier_quotation_id,
        string $user_id,
        array $data
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $supplier_quotation_id,
            $user_id,
            $data
        ) {
            /**
             * Ambil dan lock quotation yang dipilih.
             */
            $quotation = SupplierQuotation::query()
                ->with([
                    'supplierQuotationRequestSupplier.requestSupplierPurchaseRequest',
                    'supplierQuotationRequestSupplier.requestSupplierSupplier',
                    'supplierQuotationDetailSupplierQuotation.detailSupplierQuotationPurchaseRequestDetail',
                ])
                ->lockForUpdate()
                ->findOrFail($supplier_quotation_id);

            /**
             * Quotation harus masih dapat dipilih.
             */
            if ($quotation->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'supplier_quotation' => [
                        'Purchase Order hanya dapat dibuat dari quotation yang berstatus submitted.',
                    ],
                ]);
            }

            /**
             * Quotation yang sudah expired tidak dapat digunakan.
             */
            if (
                $quotation->valid_until !== null &&
                $quotation->valid_until->isPast()
            ) {
                throw ValidationException::withMessages([
                    'supplier_quotation' => [
                        'Supplier Quotation sudah melewati masa berlaku.',
                    ],
                ]);
            }

            $requestSupplier =
                $quotation->supplierQuotationRequestSupplier;

            $purchaseRequest =
                $requestSupplier->requestSupplierPurchaseRequest;

            /**
             * Lock Purchase Request untuk mencegah
             * dua quotation dari PR yang sama dipilih
             * secara bersamaan.
             */
            $purchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail(
                    $purchaseRequest->purchase_request_id
                );

            /**
             * Pastikan Purchase Request belum memiliki PO.
             */
            $purchaseOrderExists = PurchaseOrder::where(
                'purchase_request_id',
                $purchaseRequest->purchase_request_id
            )->exists();

            if ($purchaseOrderExists) {
                throw ValidationException::withMessages([
                    'purchase_request' => [
                        'Purchase Request ini sudah memiliki Purchase Order.',
                    ],
                ]);
            }

            /**
             * Pastikan quotation belum pernah digunakan.
             */
            $quotationAlreadyUsed = PurchaseOrder::where(
                'supplier_quotation_id',
                $quotation->supplier_quotation_id
            )->exists();

            if ($quotationAlreadyUsed) {
                throw ValidationException::withMessages([
                    'supplier_quotation' => [
                        'Supplier Quotation ini sudah digunakan untuk membuat Purchase Order.',
                    ],
                ]);
            }

            /**
             * Buat Purchase Order header sebagai snapshot
             * dari quotation yang dipilih.
             */
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $this->generatePONumber(),

                'purchase_request_id' =>
                    $purchaseRequest->purchase_request_id,

                'supplier_id' =>
                    $requestSupplier->supplier_id,

                'supplier_quotation_id' =>
                    $quotation->supplier_quotation_id,

                'created_by' =>
                    $user_id,

                'order_date' =>
                    $data['order_date'],

                'expected_delivery_date' =>
                    $data['expected_delivery_date'] ?? null,

                'subtotal' =>
                    $quotation->subtotal,

                'discount_total_percentage' =>
                    $quotation->discount_total_percentage,

                'discount_amount' =>
                    $quotation->discount_amount,

                'total' =>
                    $quotation->total,

                'status' =>
                    'draft',

                'payment_status' =>
                    'unpaid',

                'notes' =>
                    $data['notes'] ?? null,
            ]);

            /**
             * Snapshot seluruh detail quotation
             * menjadi Detail Purchase Order.
             */
            foreach (
                $quotation->supplierQuotationDetailSupplierQuotation
                as $quotationDetail
            ) {
                $purchaseRequestDetail =
                    $quotationDetail
                        ->detailSupplierQuotationPurchaseRequestDetail;

                $purchaseOrder
                    ->purchaseOrderDetailPurchaseOrder()
                    ->create([
                        'item_id' =>
                            $purchaseRequestDetail->item_id,

                        'quantity' =>
                            $purchaseRequestDetail->quantity,

                        'unit_price' =>
                            $quotationDetail->unit_price,

                        'discount_percentage' =>
                            $quotationDetail->discount_percentage,

                        'discount_amount' =>
                            $quotationDetail->discount_amount,

                        'subtotal' =>
                            $quotationDetail->subtotal,
                    ]);
            }

            /**
             * Quotation yang dipilih menjadi sumber PO.
             */
            $quotation->update([
                'status' => 'po_created',
            ]);

            /**
             * Ambil seluruh Request Supplier
             * dari Purchase Request yang sama.
             */
            $requestSupplierIds = RequestSupplier::where(
                'purchase_request_id',
                $purchaseRequest->purchase_request_id
            )->pluck('request_supplier_id');

            /**
             * Seluruh quotation lain yang masih submitted
             * otomatis tidak terpilih.
             */
            SupplierQuotation::whereIn(
                'request_supplier_id',
                $requestSupplierIds
            )
                ->where(
                    'supplier_quotation_id',
                    '!=',
                    $quotation->supplier_quotation_id
                )
                ->where('status', 'submitted')
                ->update([
                    'status' => 'not_selected',
                ]);

            /**
             * Purchase Request sudah masuk
             * ke tahap Purchase Order.
             */
            $purchaseRequest->update([
                'status' => 'po_created',
            ]);

            return $purchaseOrder->fresh()->load([
                'purchaseOrderPurchaseRequest',
                'purchaseOrderSupplier',
                'purchaseOrderSupplierQuotation',
                'purchaseOrderUser',
                'purchaseOrderDetailPurchaseOrder.detailPurchaseOrderItem',
            ]);
        });
    }

    /**
     * Memperbarui informasi header Purchase Order.
     *
     * PO hanya dapat diperbarui selama masih draft.
     */
    public function update(
        string $purchase_order_id,
        array $data
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::findOrFail(
            $purchase_order_id
        );

        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages([
                'purchase_order' => [
                    'Purchase Order hanya dapat diperbarui ketika masih berstatus draft.',
                ],
            ]);
        }

        $purchaseOrder->update($data);

        return $purchaseOrder->fresh();
    }

    /**
     * Memperbarui status Purchase Order.
     */
    public function updateStatus(
        string $purchase_order_id,
        string $status
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $purchase_order_id,
            $status
        ) {
            $purchaseOrder = PurchaseOrder::with([
                'purchaseOrderDetailPurchaseOrder.detailPurchaseOrderItem',
            ])
                ->lockForUpdate()
                ->findOrFail($purchase_order_id);

            $allowedTransitions = [
                'draft' => [
                    'sent',
                    'cancelled',
                ],

                'sent' => [
                    'accepted',
                    'failed',
                    'cancelled',
                ],

                'accepted' => [
                    'shipping',
                    'cancelled',
                ],

                'shipping' => [
                    'delivered',
                    'failed',
                ],

                'delivered' => [
                    'completed',
                ],
            ];

            $currentStatus = $purchaseOrder->status;

            if (
                !isset($allowedTransitions[$currentStatus]) ||
                !in_array(
                    $status,
                    $allowedTransitions[$currentStatus]
                )
            ) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Status tidak dapat diubah dari {$currentStatus} ke {$status}.",
                    ],
                ]);
            }

            /**
             * Stock bertambah ketika barang
             * benar-benar delivered.
             */
            if ($status === 'delivered') {
                foreach (
                    $purchaseOrder->purchaseOrderDetailPurchaseOrder
                    as $detail
                ) {
                    $detail->detailPurchaseOrderItem()
                        ->increment(
                            'stock',
                            $detail->quantity
                        );
                }
            }

            /**
             * Completed berarti:
             * - Barang sudah delivered
             * - Payment sudah lunas
             */
            if (
                $status === 'completed' &&
                $purchaseOrder->payment_status !== 'paid'
            ) {
                throw ValidationException::withMessages([
                    'purchase_order' => [
                        'Purchase Order belum dapat diselesaikan karena pembayaran belum lunas.',
                    ],
                ]);
            }

            $purchaseOrder->update([
                'status' => $status,
            ]);

            return $purchaseOrder->fresh();
        });
    }
    private function generatePONumber(): string
    {
        return 'PO-'
            . now()->format('Ymd')
            . '-'
            . strtoupper(Str::random(6));
    }
}