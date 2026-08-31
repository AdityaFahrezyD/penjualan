<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class detailTransaction extends Model
{
    // use HasUuids;
    
    // protected $primaryKey = 'tr_detail_id';
    // protected $keyType = 'string';
    // public $incrementing = false;

    // protected $fillable = [
    //     'tr_id',
    //     'item_id',
    //     'item_quant',
    //     'item_price',
    //     'subtotal',
    // ];

    // public function detailtransactionMsTransactions()
    // {
    //     return $this->belongsTo(MsTransaction::class, 'tr_id', 'tr_id');
    // }

    // public function itemDetailTransactions()
    // {
    //     return $this->belongsTo(Item::class, 'item_id', 'item_id');
    // }
}
