<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'uuid',
                'exists:users,id',
                'unique:suppliers,user_id',
                Rule::exists('users', 'id')
                    ->where('role', 'supplier'),
            ],

            'supplier_name' => [
                'required',
                'string',
                'max:50',
            ],

            'phone' => [
                'required',
                'string',
                'max:15',
            ],

            'address' => [
                'required',
                'string',
                'max:200',
            ],
        ];
    }
}