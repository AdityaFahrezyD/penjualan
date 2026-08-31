<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasUuids;
    
    protected $primaryKey = 'item_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'item_name',
        'stock',
        'unit_id',
    ];

    // public function itemDetailTransactions()
    // {
    //     return $this->hasMany(detailTransaction::class, 'item_id', 'item_id');
    // }

    public function itemUnit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }

    public function itemDetailPurchaseRequest()
    {
        return $this->hasMany(DetailPurchaseRequest::class,'item_id','item_id');
    }

    public function itemDetailPurchaseOrder()
    {
        return $this->hasMany(DetailPurchaseOrder::class,'item_id','item_id');
    }
}
