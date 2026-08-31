<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\HasApiTokens;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function userPurchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class, 'created_by', 'id');
    }

    public function userPurchaseOrder()
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by', 'id');
    }

    public function userSupplier()
    {
        return $this->hasOne(Supplier::class, 'user_id', 'id');
    }

    public function userPayment()
    {
        return $this->hasMany(Payment::class, 'created_by', 'id');
    }
    
    public function userPaymentConfirm()
    {
        return $this->hasMany(Payment::class, 'confirmed_by', 'id');
    }
}
