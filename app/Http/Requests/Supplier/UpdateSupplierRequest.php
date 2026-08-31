<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'user_id' => [
                'sometimes',
                'required',
                'uuid',
                'exists:users,id',
                Rule::unique('suppliers', 'user_id')
                    ->ignore(
                        $supplier->supplier_id,
                        'supplier_id'
                    ),
                Rule::exists('users', 'id')
                    ->where('role', 'supplier'),
            ],

            'supplier_name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],

            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:15',
            ],

            'address' => [
                'sometimes',
                'required',
                'string',
                'max:200',
            ],
        ];
    }
}