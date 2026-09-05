<?php

namespace App\Http\Requests\SupplierQuotation;

use App\Services\QuotationPackaging;
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
            ...QuotationPackaging::rules('sometimes'),
            'base_quantity' => ['prohibited'],
            'base_unit_id' => ['prohibited'],
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
