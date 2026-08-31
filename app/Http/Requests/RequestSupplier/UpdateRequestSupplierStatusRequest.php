<?php

namespace App\Http\Requests\RequestSupplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestSupplierStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'in:accepted,rejected',
            ],

            'rejection_reason' => [
                'nullable',
                'string',
                'required_if:status,rejected',
            ],
        ];
    }
}