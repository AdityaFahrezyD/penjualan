<?php

namespace App\Services;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RequestSupplier;
use App\Models\SupplierQuotation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
                $quotation->valid_until->isBefore(today())
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
            $hasDifference = collect($quotation->quantity_summary)->contains(fn ($row) => $row['difference'] !== 0);
            if ($hasDifference && ! ($data['accept_quantity_difference'] ?? false)) {
                throw ValidationException::withMessages([
                    'accept_quantity_difference' => ['Jumlah penawaran berbeda dari kebutuhan PR. Periksa quantity_summary dan konfirmasi selisih sebelum membuat PO.'],
                ]);
            }

            $purchaseOrder = PurchaseOrder::create([
                'quantity_difference_accepted' => $hasDifference,
                'po_number' => $this->generatePONumber(),

                'purchase_request_id' => $purchaseRequest->purchase_request_id,

                'supplier_id' => $requestSupplier->supplier_id,

                'supplier_quotation_id' => $quotation->supplier_quotation_id,

                'created_by' => $user_id,

                'order_date' => $data['order_date'],

                'shipping_date' => null,
                'expected_delivery_date' => null,

                'subtotal' => $quotation->subtotal,

                'discount_total_percentage' => $quotation->discount_total_percentage,

                'discount_amount' => $quotation->discount_amount,

                'total' => $quotation->total,

                'status' => 'draft',

                'payment_status' => 'unpaid',

                'notes' => $data['notes'] ?? null,
            ]);

            /**
             * Snapshot seluruh detail quotation
             * menjadi Detail Purchase Order.
             */
            foreach (
                $quotation->supplierQuotationDetailSupplierQuotation as $quotationDetail
            ) {
                $purchaseRequestDetail =
                    $quotationDetail
                        ->detailSupplierQuotationPurchaseRequestDetail;

                $purchaseOrder
                    ->purchaseOrderDetailPurchaseOrder()
                    ->create([
                        'item_id' => $purchaseRequestDetail->item_id,

                        'quantity' => $quotationDetail->quantity,

                        'detail_purchase_request_id' => $purchaseRequestDetail->detail_purchase_request_id,
                        'unit_id' => $quotationDetail->unit_id,
                        'base_unit_id' => $quotationDetail->base_unit_id,
                        'conversion_qty' => $quotationDetail->conversion_qty,
                        'base_quantity' => $quotationDetail->base_quantity,

                        'unit_price' => $quotationDetail->unit_price,

                        'discount_percentage' => $quotationDetail->discount_percentage,

                        'discount_amount' => $quotationDetail->discount_amount,

                        'subtotal' => $quotationDetail->subtotal,
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

        $purchaseOrder->update(Arr::only($data, ['notes']));

        return $purchaseOrder->fresh();
    }

    /**
     * Memperbarui status Purchase Order.
     */
    public function updateStatus(
        string $purchase_order_id,
        string $status,
        string $role,
        ?string $supplier_id = null,
        array $data = []
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $purchase_order_id,
            $status,
            $role,
            $supplier_id,
            $data
        ) {
            $purchaseOrder = PurchaseOrder::with([
                'purchaseOrderDetailPurchaseOrder.detailPurchaseOrderItem',
            ])
                ->lockForUpdate()
                ->findOrFail($purchase_order_id);

            $currentStatus = $purchaseOrder->status;

            if ($role === 'supplier') {
                if ($supplier_id === null || $purchaseOrder->supplier_id !== $supplier_id) {
                    abort(403, 'Anda tidak memiliki akses ke Purchase Order ini.');
                }

                $allowed = in_array($currentStatus, ['draft', 'sent', 'accepted'], true)
                    && $status === 'shipping';
            } else {
                $allowed = in_array($role, ['admin', 'akuntan'], true)
                    && $currentStatus === 'shipping'
                    && $status === 'delivered';
            }

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Supplier hanya dapat mengubah status menjadi Dikirim, sedangkan Admin/Akuntan hanya dapat mengubah status menjadi Barang Diterima.',
                    ],
                ]);
            }

            if ($status === 'shipping') {
                $dates = Validator::make($data, [
                    'shipping_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$purchaseOrder->order_date->toDateString(), 'before_or_equal:today'],
                    'expected_delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:shipping_date'],
                ])->validate();
                $purchaseOrder->fill($dates);
            }

            /**
             * Stock bertambah ketika barang
             * benar-benar delivered.
             */
            if ($status === 'delivered') {
                // Lock items in a stable order and check the sum of all packaging rows.
                $totals = $purchaseOrder->purchaseOrderDetailPurchaseOrder->groupBy('item_id')->sortKeys();
                foreach ($totals as $itemId => $lines) {
                    $item = Item::lockForUpdate()->findOrFail($itemId);
                    $added = $lines->reduce(fn ($sum, $line) => bcadd($sum, (string) $line->base_quantity, 0), '0');
                    if ($lines->contains(fn ($line) => $line->base_unit_id !== $item->unit_id)
                        || bccomp(bcadd((string) $item->stock, $added, 0), '2147483647', 0) > 0) {
                        throw ValidationException::withMessages(['stock' => ['Satuan stok berubah atau stok melebihi kapasitas.']]);
                    }
                }
                foreach (
                    $purchaseOrder->purchaseOrderDetailPurchaseOrder as $detail
                ) {
                    $detail->detailPurchaseOrderItem()
                        ->increment(
                            'stock',
                            $detail->base_quantity
                        );
                }
            }

            $purchaseOrder->update([
                'status' => $status,
            ]);

            return $purchaseOrder->fresh();
        });
    }

    public function updateDeliveryEstimate(string $purchaseOrderId, string $supplierId, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrderId, $supplierId, $data) {
            $po = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrderId);
            abort_if($po->supplier_id !== $supplierId, 403, 'Anda tidak memiliki akses ke Purchase Order ini.');
            if ($po->status !== 'shipping' || $po->shipping_date === null) {
                throw ValidationException::withMessages([
                    'purchase_order' => ['Estimasi hanya dapat diperbarui untuk PO yang sedang dikirim dan memiliki tanggal pengiriman.'],
                ]);
            }
            $dates = Validator::make($data, [
                'expected_delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$po->shipping_date->toDateString()],
            ])->validate();
            $po->update($dates);

            return $po->fresh();
        });
    }

    private function generatePONumber(): string
    {
        return 'PO-'
            .now()->format('Ymd')
            .'-'
            .strtoupper(Str::random(6));
    }
}
