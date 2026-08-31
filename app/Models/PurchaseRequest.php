<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PurchaseRequest extends Model
{
    use HasUuids;

    protected $primaryKey = 'purchase_request_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'request_number',
        'created_by',
        'request_date',
        'notes',
        'status',
    ];

    public function purchaseRequestUser()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function purchaseRequestRequestSupplier()
    {
        return $this->hasMany(RequestSupplier::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function purchaseRequestPurchaseOrder()
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function purchaseRequestDetailPurchaseRequest()
    {
        return $this->hasMany(DetailPurchaseRequest::class,'purchase_request_id','purchase_request_id');
    }
}
