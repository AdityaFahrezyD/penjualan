<?php

namespace App\Http\Requests\SupplierQuotation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDetailSupplierQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
                'max:9999999999999.99',
            ],

            'discount_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
                'max:100',
            ],
        ];
    }
}
