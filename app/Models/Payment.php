<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Payment extends Model
{
    use HasUuids;

    protected $primaryKey = 'payment_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'payment_number',
        'purchase_order_id',
        'created_by',
        'amount',
        'payment_method',
        'payment_date',
        'status',
        'confirmed_at',
        'confirmed_by',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function paymentPurchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function paymentUser()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function paymentUserConfirm()
    {
        return $this->belongsTo(User::class, 'confirmed_by', 'id');
    }
}
