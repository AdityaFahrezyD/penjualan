<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DetailPurchaseRequest extends Model
{
    use HasUuids;

    protected $primaryKey = 'detail_purchase_request_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $with = ['baseUnit'];

    protected $fillable = [
        'base_unit_id',
        'purchase_request_id',
        'item_id',
        'quantity',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $detail) {
            if (! $detail->exists || $detail->isDirty('item_id')) {
                $detail->base_unit_id = Item::query()->lockForUpdate()->findOrFail($detail->item_id)->unit_id;
            }
        });
    }

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id', 'unit_id');
    }

    public function detailPurchaseRequestPurchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function detailPurchaseRequestItem()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function detailPurchaseRequestSupplierQuotationDetail()
    {
        return $this->hasMany(DetailSupplierQuotation::class, 'detail_purchase_request_id', 'detail_purchase_request_id');
    }
}
