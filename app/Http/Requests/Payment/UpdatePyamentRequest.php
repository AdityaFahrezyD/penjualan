<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'sometimes',
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'sometimes',
                'required',
                'in:cash,bank_transfer,ewallet',
            ],

            'payment_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}