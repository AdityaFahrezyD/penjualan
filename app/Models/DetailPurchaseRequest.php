<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DetailPurchaseRequest extends Model
{
    use HasUuids;

    protected $primaryKey = 'detail_purchase_request_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'purchase_request_id',
        'item_id',
        'quantity',
        'notes',
    ];

    public function detailPurchaseRequestPurchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class,'purchase_request_id','purchase_request_id');
    }

    public function detailPurchaseRequestItem()
    {
        return $this->belongsTo(Item::class,'item_id','item_id');
    }

    public function detailPurchaseRequestSupplierQuotationDetail()
    {
        return $this->hasMany(DetailSupplierQuotation::class,'detail_purchase_request_id','detail_purchase_request_id');
    }
}