<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DetailSupplierQuotation extends Model
{
    use HasUuids;

    protected $primaryKey = 'detail_supplier_quotation_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'supplier_quotation_id',
        'detail_purchase_request_id',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'subtotal',
    ];

    public function detailSupplierQuotationSupplierQuotation()
    {
        return $this->belongsTo(SupplierQuotation::class,'supplier_quotation_id','supplier_quotation_id');
    }

    public function detailSupplierQuotationPurchaseRequestDetail()
    {
        return $this->belongsTo(DetailPurchaseRequest::class,'detail_purchase_request_id','detail_purchase_request_id');
    }
}