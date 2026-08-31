<?php

namespace App\Http\Requests\SupplierQuotation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valid_until' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:today',
            ],

            'discount_total_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
        ];
    }
}