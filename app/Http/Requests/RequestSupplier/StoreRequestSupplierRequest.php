<?php

namespace App\Http\Requests\RequestSupplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequestSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'supplier_ids.*' => [
                'required',
                'uuid',
                'exists:suppliers,supplier_id',
                'distinct',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}