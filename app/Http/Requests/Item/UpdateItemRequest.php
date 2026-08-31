<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_name' => [
                'sometimes',
                'required',
                'string',
                'max:60',
            ],

            'unit_id' => [
                'sometimes',
                'required',
                'uuid',
                'exists:units,unit_id',
            ],
        ];
    }
}