<?php

namespace App\Http\Requests\PurchaseRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_date' => [
                'required',
                'date',
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

            'details.*.item_id' => [
                'required',
                'uuid',
                'exists:items,item_id',
                'distinct',
            ],

            'details.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'details.*.notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}