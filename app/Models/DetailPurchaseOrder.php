<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DetailPurchaseOrder extends Model
{
    use HasUuids;

    protected $primaryKey = 'detail_purchase_order_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $with = ['purchaseUnit', 'baseUnit'];

    protected $fillable = [
        'detail_purchase_request_id',
        'unit_id',
        'base_unit_id',
        'conversion_qty',
        'base_quantity',
        'purchase_order_id',
        'item_id',
        'quantity',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'subtotal',
    ];

    public function detailPurchaseOrderPurchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function detailPurchaseOrderItem()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function purchaseUnit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }

    public function baseUnit()
    {
        return $this->belongsTo(Unit::class, 'base_unit_id', 'unit_id');
    }
}
