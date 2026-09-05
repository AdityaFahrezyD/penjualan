<?php

namespace App\Http\Requests\SupplierQuotation;

use App\Services\QuotationPackaging;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...collect(QuotationPackaging::rules())
                ->mapWithKeys(fn ($rules, $field) => ['details.*.'.$field => $rules])->all(),
            'details.*.base_quantity' => ['prohibited'],
            'details.*.base_unit_id' => ['prohibited'],
            'quotation_date' => [
                'required',
                'date',
            ],

            'valid_until' => [
                'nullable',
                'date',
                'after_or_equal:quotation_date',
            ],

            'discount_total_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.detail_purchase_request_id' => [
                'required',
                'uuid',
                'exists:detail_purchase_requests,detail_purchase_request_id',
            ],

            'details.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
                'max:9999999999999.99',
            ],

            'details.*.discount_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
                'max:100',
            ],
        ];
    }
}
