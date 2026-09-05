<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_date' => ['prohibited'],
            'expected_delivery_date' => ['prohibited'],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
