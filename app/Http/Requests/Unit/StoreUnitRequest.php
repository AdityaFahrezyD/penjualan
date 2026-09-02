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
                'between:2,3',
                'unique:units,unit_code',
            ],
        ];
    }
}