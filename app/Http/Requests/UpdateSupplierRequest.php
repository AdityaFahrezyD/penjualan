<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_name' => [
                'required',
                'string',
                'max:50',
            ],

            'phone' => [
                'required',
                'string',
                'max:12',
            ],

            'address' => [
                'required',
                'string',
                'max:60',
            ],
        ];
    }
}