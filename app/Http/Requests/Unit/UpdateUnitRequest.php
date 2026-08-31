<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit_id');

        return [
            'unit_name' => [
                'sometimes',
                'required',
                'string',
                'max:20',
            ],

            'unit_code' => [
                'sometimes',
                'required',
                'string',
                'size:5',
                Rule::unique('units', 'unit_code')
                    ->ignore($unitId, 'unit_id'),
            ],
        ];
    }
}