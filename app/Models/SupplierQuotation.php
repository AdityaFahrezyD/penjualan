<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupplierQuotation extends Model
{
    use HasUuids;

    protected $primaryKey = 'supplier_quotation_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $appends = ['quantity_summary'];

    protected $fillable = [
        'quotation_number',
        'request_supplier_id',
        'quotation_date',
        'valid_until',
        'subtotal',
        'discount_total_percentage',
        'discount_amount',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplierQuotationRequestSupplier()
    {
        return $this->belongsTo(RequestSupplier::class, 'request_supplier_id', 'request_supplier_id');
    }

    public function supplierQuotationPurchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class, 'supplier_quotation_id', 'supplier_quotation_id');
    }

    public function supplierQuotationDetailSupplierQuotation()
    {
        return $this->hasMany(DetailSupplierQuotation::class, 'supplier_quotation_id', 'supplier_quotation_id');
    }

    public function getQuantitySummaryAttribute(): array
    {
        $this->loadMissing([
            'supplierQuotationRequestSupplier.requestSupplierPurchaseRequest.purchaseRequestDetailPurchaseRequest',
            'supplierQuotationDetailSupplierQuotation',
        ]);
        $details = $this->supplierQuotationDetailSupplierQuotation;

        return $this->supplierQuotationRequestSupplier->requestSupplierPurchaseRequest
            ->purchaseRequestDetailPurchaseRequest->map(function ($request) use ($details) {
                $offered = $details->where('detail_purchase_request_id', $request->detail_purchase_request_id)
                    ->reduce(fn ($sum, $line) => bcadd($sum, (string) $line->base_quantity, 0), '0');
                $difference = bcsub($offered, (string) $request->quantity, 0);

                return [
                    'detail_purchase_request_id' => $request->detail_purchase_request_id,
                    'item_id' => $request->item_id,
                    'base_unit_id' => $request->base_unit_id,
                    'requested_quantity' => (int) $request->quantity,
                    'offered_quantity' => (int) $offered,
                    'difference' => (int) $difference,
                    'status' => bccomp($difference, '0', 0) === 0 ? 'exact' : (bccomp($difference, '0', 0) > 0 ? 'over' : 'under'),
                ];
            })->all();
    }
}
