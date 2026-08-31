<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'sometimes',
                'required',
                'uuid',
                'exists:suppliers,supplier_id',
            ],

            'tr_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'payment_method' => [
                'sometimes',
                'required',
                'in:cash,cashless',
            ],

            'details' => [
                'sometimes',
                'required',
                'array',
                'min:1',
            ],

            'details.*.tr_detail_id' => [
                'nullable',
                'uuid',
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