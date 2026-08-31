<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DetailPurchaseOrder extends Model
{
    use HasUuids;

    protected $primaryKey = 'detail_purchase_order_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
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
        return $this->belongsTo(PurchaseOrder::class,'purchase_order_id','purchase_order_id');
    }

    public function detailPurchaseOrderItem()
    {
        return $this->belongsTo(Item::class,'item_id','item_id');
    }
}