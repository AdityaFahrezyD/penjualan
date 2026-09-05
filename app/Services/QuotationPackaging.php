<?php

namespace App\Services;

use App\Models\DetailPurchaseRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuotationPackaging
{
    public static function rules(string $presence = 'required'): array
    {
        return [
            'unit_id' => [$presence, 'required', 'uuid', 'exists:units,unit_id'],
            'quantity' => [$presence, 'required', 'integer', 'min:1', 'max:2147483647'],
            'conversion_qty' => [$presence, 'required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public static function calculate(DetailPurchaseRequest $request, array $data): array
    {
        $values = Validator::make($data, self::rules())->validate();
        if ($values['unit_id'] === $request->base_unit_id && (int) $values['conversion_qty'] !== 1) {
            throw ValidationException::withMessages(['conversion_qty' => ['Konversi satuan dasar harus 1.']]);
        }
        $baseQuantity = bcmul((string) $values['quantity'], (string) $values['conversion_qty'], 0);
        if (bccomp($baseQuantity, '2147483647', 0) > 0) {
            throw ValidationException::withMessages(['quantity' => ['Jumlah satuan dasar melebihi kapasitas stok.']]);
        }

        return [...$values, 'base_unit_id' => $request->base_unit_id, 'base_quantity' => (int) $baseQuantity];
    }
}
