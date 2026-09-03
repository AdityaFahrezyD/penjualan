<?php

namespace App\Services;

use App\Models\DetailPurchaseRequest;
use App\Models\DetailSupplierQuotation;
use App\Models\RequestSupplier;
use App\Models\SupplierQuotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierQuotationService
{
    /**
     * Menampilkan seluruh Request Supplier, atau hanya milik supplier
     * yang sedang login ketika supplier ID diberikan.
     */
    public function getSupplierRequests(?string $supplier_id = null)
    {
        $query = RequestSupplier::with([
            'requestSupplierPurchaseRequest.purchaseRequestDetailPurchaseRequest.detailPurchaseRequestItem',
            'requestSupplierSupplierQuotation',
        ]);

        if ($supplier_id !== null) {
            $query->where('supplier_id', $supplier_id);
        }

        return $query
            ->orderByDesc('sent_at')
            ->get();
    }

    /**
     * Menampilkan detail Request Supplier.
     */
    public function getRequestDetail(
        string $request_supplier_id,
        ?string $supplier_id = null
    ): RequestSupplier {
        $query = RequestSupplier::with([
            'requestSupplierPurchaseRequest.purchaseRequestDetailPurchaseRequest.detailPurchaseRequestItem',
            'requestSupplierSupplierQuotation.supplierQuotationDetailSupplierQuotation.detailSupplierQuotationPurchaseRequestDetail.detailPurchaseRequestItem',
        ])->where('request_supplier_id', $request_supplier_id);

        if ($supplier_id !== null) {
            $query->where('supplier_id', $supplier_id);
        }

        return $query->firstOrFail();
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
                ->lockForUpdate()
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
                if (! in_array($detailId, $validDetailIds)) {
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

            if (! empty($missingDetails)) {
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
                'quotation_number' => $this->generateQuotationNumber(),

                'request_supplier_id' => $request_supplier_id,

                'quotation_date' => $data['quotation_date'],

                'valid_until' => $data['valid_until'] ?? null,

                'subtotal' => 0,

                'discount_total_percentage' => $data['discount_total_percentage'] ?? 0,

                'discount_amount' => 0,

                'total' => 0,

                'status' => 'submitted',

                'notes' => $data['notes'] ?? null,
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

                $unitPrice = $this->money($detailData['unit_price']);

                $discountPercentage = (string) (
                    $detailData[
                        'discount_percentage'
                    ] ?? 0
                );

                /*
                 * Harga sebelum diskon.
                 */
                $grossAmount = bcmul(
                    (string) $purchaseRequestDetail->quantity,
                    $unitPrice,
                    2
                );

                $this->ensureMoneyFitsColumn($grossAmount, 'details');

                /*
                 * Nominal diskon detail.
                 */
                $discountAmount = $this->percentageAmount(
                    $grossAmount,
                    $discountPercentage
                );

                /*
                 * Subtotal detail setelah diskon.
                 */
                $detailSubtotal = bcsub($grossAmount, $discountAmount, 2);

                DetailSupplierQuotation::create([
                    'supplier_quotation_id' => $quotation->supplier_quotation_id,

                    'detail_purchase_request_id' => $purchaseRequestDetail
                        ->detail_purchase_request_id,

                    'unit_price' => $unitPrice,

                    'discount_percentage' => $discountPercentage,

                    'discount_amount' => $discountAmount,

                    'subtotal' => $detailSubtotal,
                ]);

                $quotationSubtotal = bcadd(
                    (string) $quotationSubtotal,
                    $detailSubtotal,
                    2
                );

                $this->ensureMoneyFitsColumn($quotationSubtotal, 'details');
            }

            /*
             * Hitung diskon total quotation.
             */
            $totalDiscountPercentage = (string) (
                $data[
                    'discount_total_percentage'
                ] ?? 0
            );

            $totalDiscountAmount = $this->percentageAmount(
                (string) $quotationSubtotal,
                $totalDiscountPercentage
            );

            /*
             * Hitung total akhir.
             */
            $quotationTotal = bcsub(
                (string) $quotationSubtotal,
                $totalDiscountAmount,
                2
            );

            /*
             * Update nilai perhitungan quotation.
             */
            $quotation->update([
                'subtotal' => $quotationSubtotal,

                'discount_amount' => $totalDiscountAmount,

                'total' => $quotationTotal,
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

            if (
                isset($data['valid_until']) &&
                $data['valid_until'] !== null &&
                $quotation->quotation_date->isAfter($data['valid_until'])
            ) {
                throw ValidationException::withMessages([
                    'valid_until' => [
                        'Tanggal berlaku harus sama dengan atau setelah tanggal quotation.',
                    ],
                ]);
            }

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
            $grossAmount = bcmul((string) $quantity, (string) $unitPrice, 2);

            $this->ensureMoneyFitsColumn($grossAmount, 'unit_price');

            /*
            * Diskon detail.
            */
            $discountAmount = $this->percentageAmount(
                $grossAmount,
                (string) $discountPercentage
            );

            /*
            * Subtotal setelah diskon.
            */
            $subtotal = bcsub($grossAmount, $discountAmount, 2);

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
        $subtotal = $details->reduce(
            fn (string $total, DetailSupplierQuotation $detail): string => bcadd($total, (string) $detail->subtotal, 2),
            '0.00'
        );

        $this->ensureMoneyFitsColumn($subtotal, 'details');

        /*
        * Diskon tambahan di level header.
        */
        $discountPercentage =
            $quotation->discount_total_percentage ?? 0;

        $discountAmount = $this->percentageAmount(
            $subtotal,
            (string) $discountPercentage
        );

        /*
        * Total akhir quotation.
        */
        $total = bcsub($subtotal, $discountAmount, 2);

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

        if (in_array($quotation->status, $lockedStatuses, true)) {
            throw ValidationException::withMessages([
                'supplier_quotation' => [
                    'Quotation tidak dapat diperbarui.',
                ],
            ]);
        }

        if (
            $quotation->valid_until !== null &&
            $quotation->valid_until->isBefore(today())
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
            .now()->format('Ymd')
            .'-'
            .strtoupper(Str::random(6));
    }

    private function money(string|int|float $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    private function percentageAmount(string $amount, string $percentage): string
    {
        $unrounded = bcdiv(bcmul($amount, $percentage, 4), '100', 4);

        return bcadd($unrounded, '0.005', 2);
    }

    private function ensureMoneyFitsColumn(string $amount, string $field): void
    {
        if (bccomp($amount, '9999999999999.99', 2) === 1) {
            throw ValidationException::withMessages([
                $field => ['Nilai quotation melebihi batas yang dapat disimpan.'],
            ]);
        }
    }
}
