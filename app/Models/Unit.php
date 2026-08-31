<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Unit extends Model
{
    use HasUuids;
    
    protected $primaryKey = 'unit_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'unit_name',
        'unit_code',
    ];

    public function unitItem()
    {
        return $this->hasMany(Item::class, 'unit_id', 'unit_id');
    }

}
