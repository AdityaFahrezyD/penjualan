<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                'uuid',
                'exists:suppliers,supplier_id',
            ],

            'tr_date' => [
                'nullable',
                'date',
            ],

            'payment_method' => [
                'required',
                'in:cash,cashless',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.item_id' => [
                'required',
                'uuid',
                'exists:items,item_id',
            ],

            'details.*.item_quant' => [
                'required',
                'integer',
                'min:1',
            ],

            'details.*.item_price' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}