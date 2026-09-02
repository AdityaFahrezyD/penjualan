<?php

namespace App\Services;

use App\Models\DetailPurchaseRequest;
use App\Models\DetailSupplierQuotation;
use App\Models\RequestSupplier;
use App\Models\SupplierQuotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class SupplierQuotationService
{
    /**
     * Menampilkan seluruh Request Supplier
     * milik supplier yang sedang login.
     */
    public function getSupplierRequests(string $supplier_id)
    {
        return RequestSupplier::with([
            'requestSupplierPurchaseRequest.purchaseRequestDetailPurchaseRequest.detailPurchaseRequestItem',
            'requestSupplierSupplierQuotation',
        ])
            ->where('supplier_id', $supplier_id)
            ->orderByDesc('sent_at')
            ->get();
    }

    /**
     * Menampilkan detail Request Supplier.
     */
    public function getRequestDetail(
        string $request_supplier_id,
        string $supplier_id
    ): RequestSupplier {
        return RequestSupplier::with([
            'requestSupplierPurchaseRequest.purchaseRequestDetailPurchaseRequest.detailPurchaseRequestItem',
            'requestSupplierSupplierQuotation',
        ])
            ->where(
                'request_supplier_id',
                $request_supplier_id
            )
            ->where('supplier_id', $supplier_id)
            ->firstOrFail();
    }

    /**
     * Membuat Supplier Quotation beserta detailnya.
     */
    public function create(
        string $request_supplier_id,
        string $supplier_id,
        array $data
    ): SupplierQuotation {
        return DB::transaction(function () use (
            $request_supplier_id,
            $supplier_id,
            $data
        ) {
            /*
             * Pastikan Request Supplier milik supplier
             * yang sedang login.
             */
            $requestSupplier = RequestSupplier::with([
                'requestSupplierPurchaseRequest.purchaseRequestDetailPurchaseRequest',
            ])
                ->where(
                    'request_supplier_id',
                    $request_supplier_id
                )
                ->where(
                    'supplier_id',
                    $supplier_id
                )
                ->firstOrFail();

            /*
             * Supplier harus menerima request terlebih dahulu.
             */
            if ($requestSupplier->status !== 'accepted') {
                throw ValidationException::withMessages([
                    'request_supplier' => [
                        'Quotation hanya dapat dibuat untuk Request Supplier yang berstatus accepted.',
                    ],
                ]);
            }

            /*
             * Satu Request Supplier hanya boleh memiliki
             * satu Supplier Quotation.
             */
            $quotationExists = SupplierQuotation::where(
                'request_supplier_id',
                $request_supplier_id
            )->exists();

            if ($quotationExists) {
                throw ValidationException::withMessages([
                    'request_supplier' => [
                        'Request Supplier ini sudah memiliki quotation.',
                    ],
                ]);
            }

            /*
             * Ambil seluruh Detail Purchase Request
             * yang valid untuk quotation ini.
             */
            $validDetailIds = $requestSupplier
                ->requestSupplierPurchaseRequest
                ->purchaseRequestDetailPurchaseRequest
                ->pluck('detail_purchase_request_id')
                ->toArray();

            $submittedDetailIds = collect($data['details'])
                ->pluck('detail_purchase_request_id')
                ->toArray();

            /*
             * Pastikan setiap detail yang dikirim
             * benar-benar milik Purchase Request.
             */
            foreach ($submittedDetailIds as $detailId) {
                if (!in_array($detailId, $validDetailIds)) {
                    throw ValidationException::withMessages([
                        'details' => [
                            'Terdapat detail Purchase Request yang tidak valid.',
                        ],
                    ]);
                }
            }

            /*
             * Untuk desain saat ini, quotation harus
             * mencakup seluruh item dalam Purchase Request.
             */
            $missingDetails = array_diff(
                $validDetailIds,
                $submittedDetailIds
            );

            if (!empty($missingDetails)) {
                throw ValidationException::withMessages([
                    'details' => [
                        'Quotation harus mencakup seluruh detail item dalam Purchase Request.',
                    ],
                ]);
            }

            /*
             * Buat quotation header terlebih dahulu.
             */
            $quotation = SupplierQuotation::create([
                'quotation_number' =>
                    $this->generateQuotationNumber(),

                'request_supplier_id' =>
                    $request_supplier_id,

                'quotation_date' =>
                    $data['quotation_date'],

                'valid_until' =>
                    $data['valid_until'] ?? null,

                'subtotal' => 0,

                'discount_total_percentage' =>
                    $data['discount_total_percentage'] ?? 0,

                'discount_amount' => 0,

                'total' => 0,

                'status' => 'submitted',

                'notes' =>
                    $data['notes'] ?? null,
            ]);

            $quotationSubtotal = 0;

            /*
             * Buat seluruh quotation detail.
             */
            foreach ($data['details'] as $detailData) {

                $purchaseRequestDetail =
                    DetailPurchaseRequest::findOrFail(
                        $detailData[
                            'detail_purchase_request_id'
                        ]
                    );

                $unitPrice =
                    (float) $detailData['unit_price'];

                $discountPercentage =
                    (float) (
                        $detailData[
                            'discount_percentage'
                        ] ?? 0
                    );

                /*
                 * Harga sebelum diskon.
                 */
                $grossAmount =
                    $purchaseRequestDetail->quantity
                    * $unitPrice;

                /*
                 * Nominal diskon detail.
                 */
                $discountAmount =
                    $grossAmount
                    * ($discountPercentage / 100);

                /*
                 * Subtotal detail setelah diskon.
                 */
                $detailSubtotal =
                    $grossAmount
                    - $discountAmount;

                DetailSupplierQuotation::create([
                    'supplier_quotation_id' =>
                        $quotation->supplier_quotation_id,

                    'detail_purchase_request_id' =>
                        $purchaseRequestDetail
                            ->detail_purchase_request_id,

                    'unit_price' =>
                        $unitPrice,

                    'discount_percentage' =>
                        $discountPercentage,

                    'discount_amount' =>
                        $discountAmount,

                    'subtotal' =>
                        $detailSubtotal,
                ]);

                $quotationSubtotal += $detailSubtotal;
            }

            /*
             * Hitung diskon total quotation.
             */
            $totalDiscountPercentage =
                (float) (
                    $data[
                        'discount_total_percentage'
                    ] ?? 0
                );

            $totalDiscountAmount =
                $quotationSubtotal
                * ($totalDiscountPercentage / 100);

            /*
             * Hitung total akhir.
             */
            $quotationTotal =
                $quotationSubtotal
                - $totalDiscountAmount;

            /*
             * Update nilai perhitungan quotation.
             */
            $quotation->update([
                'subtotal' =>
                    $quotationSubtotal,

                'discount_amount' =>
                    $totalDiscountAmount,

                'total' =>
                    $quotationTotal,
            ]);

            /*
             * Kembalikan data lengkap.
             */
            return $quotation->fresh()->load([
                'supplierQuotationRequestSupplier.requestSupplierPurchaseRequest',
                'supplierQuotationDetailSupplierQuotation.detailSupplierQuotationPurchaseRequestDetail.detailPurchaseRequestItem',
            ]);
        });
    }

    public function updateHeader(
        string $supplier_quotation_id,
        string $supplier_id,
        array $data
    ): SupplierQuotation {
        return DB::transaction(function () use (
            $supplier_quotation_id,
            $supplier_id,
            $data
        ) {
            $quotation = SupplierQuotation::with([
                'supplierQuotationRequestSupplier',
                'supplierQuotationDetailSupplierQuotation',
            ])
                ->where(
                    'supplier_quotation_id',
                    $supplier_quotation_id
                )
                ->firstOrFail();

            $this->ensureSupplierOwnership(
                $quotation,
                $supplier_id
            );

            $this->ensureQuotationEditable($quotation);

            $quotation->update($data);

            /*
            * Menghitung ulang total header karena
            * discount_total_percentage mungkin berubah.
            */
            $this->recalculateQuotation($quotation);

            return $quotation->fresh()->load([
                'supplierQuotationRequestSupplier',
                'supplierQuotationDetailSupplierQuotation',
            ]);
        });
    }

    public function updateDetail(
        string $supplier_quotation_id,
        string $detail_supplier_quotation_id,
        string $supplier_id,
        array $data
    ): DetailSupplierQuotation {
        return DB::transaction(function () use (
            $supplier_quotation_id,
            $detail_supplier_quotation_id,
            $supplier_id,
            $data
        ) {
            $quotation = SupplierQuotation::with([
                'supplierQuotationRequestSupplier',
            ])
                ->where(
                    'supplier_quotation_id',
                    $supplier_quotation_id
                )
                ->firstOrFail();

            $this->ensureSupplierOwnership(
                $quotation,
                $supplier_id
            );

            $this->ensureQuotationEditable($quotation);

            /*
            * Pastikan detail memang milik quotation tersebut.
            */
            $detail = DetailSupplierQuotation::where(
                'detail_supplier_quotation_id',
                $detail_supplier_quotation_id
            )
                ->where(
                    'supplier_quotation_id',
                    $supplier_quotation_id
                )
                ->firstOrFail();

            $detail->update($data);

            /*
            * Ambil quantity dari Detail Purchase Request.
            */
            $detail->load(
                'detailSupplierQuotationPurchaseRequestDetail'
            );

            $purchaseRequestDetail =
                $detail->detailSupplierQuotationPurchaseRequestDetail;

            $quantity = $purchaseRequestDetail->quantity;

            $unitPrice = $detail->unit_price;

            $discountPercentage =
                $detail->discount_percentage ?? 0;

            /*
            * Harga kotor.
            */
            $grossAmount = $quantity * $unitPrice;

            /*
            * Diskon detail.
            */
            $discountAmount =
                $grossAmount
                * ($discountPercentage / 100);

            /*
            * Subtotal setelah diskon.
            */
            $subtotal =
                $grossAmount - $discountAmount;

            $detail->update([
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
            ]);

            /*
            * Perubahan detail harus menyebabkan
            * total header dihitung ulang.
            */
            $this->recalculateQuotation($quotation);

            return $detail->fresh()->load([
                'detailSupplierQuotationPurchaseRequestDetail',
            ]);
        });
    }

    private function recalculateQuotation(
        SupplierQuotation $quotation
    ): void {
        $details = DetailSupplierQuotation::where(
            'supplier_quotation_id',
            $quotation->supplier_quotation_id
        )->get();

        /*
        * Total seluruh subtotal detail.
        */
        $subtotal = $details->sum('subtotal');

        /*
        * Diskon tambahan di level header.
        */
        $discountPercentage =
            $quotation->discount_total_percentage ?? 0;

        $discountAmount =
            $subtotal
            * ($discountPercentage / 100);

        /*
        * Total akhir quotation.
        */
        $total =
            $subtotal - $discountAmount;

        $quotation->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => $total,
        ]);
    }

    private function ensureSupplierOwnership(
        SupplierQuotation $quotation,
        string $supplier_id
    ): void {
        if (
            $quotation
                ->supplierQuotationRequestSupplier
                ->supplier_id !== $supplier_id
        ) {
            abort(403, 'Anda tidak memiliki akses ke quotation ini.');
        }
    }

    private function ensureQuotationEditable(
        SupplierQuotation $quotation
    ): void {
        $lockedStatuses = [
            'po_created',
            'not_selected',
            'cancelled',
        ];

        if (in_array($quotation->status, $lockedStatuses)) {
            throw ValidationException::withMessages([
                'supplier_quotation' => [
                    'Quotation tidak dapat diperbarui.',
                ],
            ]);
        }

        if (
            $quotation->valid_until !== null &&
            $quotation->valid_until->isPast()
        ) {
            throw ValidationException::withMessages([
                'supplier_quotation' => [
                    'Quotation sudah expired dan tidak dapat diperbarui.',
                ],
            ]);
        }
    }

    private function generateQuotationNumber(): string
    {
        return 'QR-'
            . now()->format('Ymd')
            . '-'
            . strtoupper(Str::random(6));
    }
}