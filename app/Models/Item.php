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
    ];

    protected $casts = [
        'stock' => 'integer',
    ];


    public function itemDetailTransactions()
    {
        return $this->hasMany(detailTransaction::class, 'item_id', 'item_id');
    }
}
