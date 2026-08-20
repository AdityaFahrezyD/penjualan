<?php

namespace App\Http\Requests;

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
                'required',
                'string',
                'max:60',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'item_price' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}