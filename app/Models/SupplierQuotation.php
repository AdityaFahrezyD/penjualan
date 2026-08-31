<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SupplierQuotation extends Model
{
    use HasUuids;

    protected $primaryKey = 'supplier_quotation_id';
    protected $keyType = 'string';
    public $incrementing = false;

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
        return $this->hasMany(DetailSupplierQuotation::class,'supplier_quotation_id','supplier_quotation_id');
    }
}
