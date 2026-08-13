<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUuids;
    
    protected $primaryKey = 'supplier_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'supplier_name',
        'phone',
        'address',
    ];

    public function suppliersMstransactions()
    {
        return $this->hasMany(MsTransaction::class, 'supplier_id', 'supplier_id');
    }

}
