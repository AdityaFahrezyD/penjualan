<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DetailSupplierQuotation extends Model
{
    use HasUuids;

    protected $primaryKey = 'detail_supplier_quotation_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $with = ['purchaseUnit', 'baseUnit'];

    protected $fillable = [
        'unit_id',
        'base_unit_id',
        'quantity',
        'conversion_qty',
        'base_quantity',
        'supplier_quotation_id',
        'detail_purchase_request_id',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function detailSupplierQuotationSupplierQuotation()
    {
        return $this->belongsTo(SupplierQuotation::class, 'supplier_quotation_id', 'supplier_quotation_id');
    }

    public function purchaseUnit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id', 'unit_id');
    }

    public function detailSupplierQuotationPurchaseRequestDetail()
    {
        return $this->belongsTo(DetailPurchaseRequest::class, 'detail_purchase_request_id', 'detail_purchase_request_id');
    }
}
