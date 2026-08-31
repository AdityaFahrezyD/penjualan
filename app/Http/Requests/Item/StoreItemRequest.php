<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_name' => [
                'required',
                'string',
                'max:60',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'unit_id' => [
                'required',
                'uuid',
                'exists:units,unit_id',
            ],
        ];
    }
}