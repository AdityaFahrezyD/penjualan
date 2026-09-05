<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role === 'supplier') {
            $supplierId = $this->user()->userSupplier?->supplier_id;
            $po = PurchaseOrder::findOrFail($this->route('purchase_order_id'));

            return $supplierId !== null && $po->supplier_id === $supplierId;
        }

        return in_array($this->user()?->role, ['admin', 'akuntan'], true);
    }

    public function rules(): array
    {
        return [
            'shipping_date' => $this->user()?->role === 'supplier' && $this->input('status') === 'shipping'
                ? ['required', 'date_format:Y-m-d', 'before_or_equal:today'] : ['prohibited'],
            'expected_delivery_date' => $this->user()?->role === 'supplier' && $this->input('status') === 'shipping'
                ? ['required', 'date_format:Y-m-d', 'after_or_equal:shipping_date'] : ['prohibited'],
            'supplier_id' => ['prohibited'],
            'order_date' => ['prohibited'],
            'notes' => ['prohibited'],
            'details' => ['prohibited'],
            'total' => ['prohibited'],
            'status' => [
                'required',
                'in:sent,accepted,shipping,delivered,completed,failed,cancelled',
            ],
        ];
    }
}
