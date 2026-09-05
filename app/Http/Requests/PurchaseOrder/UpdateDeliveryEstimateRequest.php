<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role !== 'supplier') {
            return false;
        }
        $supplierId = $this->user()->userSupplier?->supplier_id;
        $po = PurchaseOrder::findOrFail($this->route('purchase_order_id'));

        return $supplierId !== null && $po->supplier_id === $supplierId;
    }

    public function rules(): array
    {
        return [
            'expected_delivery_date' => ['required', 'date_format:Y-m-d'],
            'shipping_date' => ['prohibited'],
            'supplier_id' => ['prohibited'],
            'order_date' => ['prohibited'],
            'status' => ['prohibited'],
            'notes' => ['prohibited'],
            'details' => ['prohibited'],
            'total' => ['prohibited'],
        ];
    }
}
