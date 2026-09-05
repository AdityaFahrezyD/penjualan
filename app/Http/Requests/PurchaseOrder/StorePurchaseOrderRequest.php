<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accept_quantity_difference' => ['sometimes', 'boolean'],
            'order_date' => [
                'required',
                'date',
            ],

            'shipping_date' => ['prohibited'],
            'expected_delivery_date' => ['prohibited'],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
