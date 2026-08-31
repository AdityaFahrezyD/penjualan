<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class msTransaction extends Model
{
    // use HasUuids;
    
    // protected $primaryKey = 'tr_id';
    // protected $keyType = 'string';
    // public $incrementing = false;

    // protected $fillable = [
    //     'supplier_id',
    //     'tr_date',
    //     'payment_method',
    //     'total',
    //     'status',
    // ];

    // protected $casts = [
    //     'tr_date' => 'date',
    //     'total' => 'integer',
    // ];
    
    // public function mstransactionsSuppliers()
    // {
    //     return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    // }

    // public function mstransactionsDetailTransactions()
    // {
    //     return $this->hasMany(detailTransaction::class, 'tr_id', 'tr_id');
    // }
}

