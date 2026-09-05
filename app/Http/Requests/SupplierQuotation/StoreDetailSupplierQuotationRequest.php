<?php

namespace App\Http\Requests\SupplierQuotation;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetailSupplierQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];
        foreach ((new StoreSupplierQuotationRequest)->rules() as $field => $fieldRules) {
            if (str_starts_with($field, 'details.*.')) {
                $rules[substr($field, strlen('details.*.'))] = $fieldRules;
            }
        }

        return $rules;
    }
}
