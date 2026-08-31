<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_name' => [
                'required',
                'string',
                'max:20',
            ],

            'unit_code' => [
                'required',
                'string',
                'size:5',
                'unique:units,unit_code',
            ],
        ];
    }
}