<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasUuids;

    protected $primaryKey = 'purchase_order_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'quantity_difference_accepted',
        'po_number',
        'purchase_request_id',
        'supplier_id',
        'supplier_quotation_id',
        'created_by',
        'order_date',
        'shipping_date',
        'expected_delivery_date',
        'subtotal',
        'discount_total_percentage',
        'discount_amount',
        'total',
        'status',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'quantity_difference_accepted' => 'boolean',
        'order_date' => 'date',
        'shipping_date' => 'date',
        'expected_delivery_date' => 'date',
    ];

    public function purchaseOrderPurchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id', 'purchase_request_id');
    }

    public function purchaseOrderSupplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function purchaseOrderSupplierQuotation()
    {
        return $this->belongsTo(SupplierQuotation::class, 'supplier_quotation_id', 'supplier_quotation_id');
    }

    public function purchaseOrderUser()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function purchaseOrderDetailPurchaseOrder()
    {
        return $this->hasMany(DetailPurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function purchaseOrderPayment()
    {
        return $this->hasMany(Payment::class, 'purchase_order_id', 'purchase_order_id');
    }
}
