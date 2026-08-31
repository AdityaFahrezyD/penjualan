<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RequestSupplier extends Model
{
    use HasUuids;

    protected $primaryKey = 'request_supplier_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'purchase_request_id',
        'supplier_id',
        'status',
        'sent_at',
        'responded_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'sent_at'=>'datetime',
        'responded_at'=>'datetime'
    ];

    public function requestSupplierPurchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function requestSupplierSupplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function requestSupplierSupplierQuotation()
    {
        return $this->hasOne(SupplierQuotation::class, 'request_supplier_id', 'request_supplier_id');
    }
}
