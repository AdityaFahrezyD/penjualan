<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierQuotation\StoreSupplierQuotationRequest;
use App\Http\Requests\SupplierQuotation\UpdateDetailSupplierQuotationRequest;
use App\Http\Requests\SupplierQuotation\UpdateSupplierQuotationRequest;
use App\Services\SupplierQuotationService;
use Illuminate\Http\Request;

class SupplierQuotationController extends Controller
{
    public function __construct(
        protected SupplierQuotationService $supplierQuotationService
    ) {}

    /**
     * Menampilkan seluruh Request Supplier
     * milik supplier yang sedang login.
     */
    public function index(Request $request)
    {
        $supplierId = $request->user()->role === 'supplier'
            ? $request->user()->userSupplier?->supplier_id
            : null;

        abort_if(
            $request->user()->role === 'supplier' && $supplierId === null,
            403,
            'Akun supplier tidak terhubung dengan data supplier.'
        );

        return response()->json([
            'message' => 'Data Request Supplier berhasil diambil.',
            'data' => $this->supplierQuotationService
                ->getSupplierRequests($supplierId),
        ]);
    }

    /**
     * Menampilkan detail Request Supplier
     * sebelum supplier membuat quotation.
     */
    public function show(
        Request $request,
        string $request_supplier_id
    ) {
        $supplierId = $request->user()->userSupplier?->supplier_id;

        return response()->json([
            'message' => 'Detail Request Supplier berhasil diambil.',
            'data' => $this->supplierQuotationService
                ->getRequestDetail(
                    $request_supplier_id,
                    $supplierId
                ),
        ]);
    }

    /**
     * Supplier membuat quotation
     * beserta seluruh detail quotation.
     */
    public function store(
        StoreSupplierQuotationRequest $request,
        string $request_supplier_id
    ) {
        $supplierId = $request->user()->userSupplier?->supplier_id;

        abort_if($supplierId === null, 403, 'Akun supplier tidak terhubung dengan data supplier.');

        return response()->json([
            'message' => 'Supplier Quotation berhasil dibuat.',
            'data' => $this->supplierQuotationService
                ->create(
                    $request_supplier_id,
                    $supplierId,
                    $request->validated()
                ),
        ], 201);
    }

    /**
     * Supplier memperbarui header quotation.
     *
     * Field yang dapat diperbarui:
     * - valid_until
     * - discount_total_percentage
     */
    public function updateHeader(
        UpdateSupplierQuotationRequest $request,
        string $supplier_quotation_id
    ) {
        $supplierId = $request->user()->userSupplier?->supplier_id;

        abort_if($supplierId === null, 403, 'Akun supplier tidak terhubung dengan data supplier.');

        return response()->json([
            'message' => 'Header Supplier Quotation berhasil diperbarui.',
            'data' => $this->supplierQuotationService
                ->updateHeader(
                    $supplier_quotation_id,
                    $supplierId,
                    $request->validated()
                ),
        ]);
    }

    /**
     * Supplier memperbarui detail quotation.
     *
     * Field yang dapat diperbarui:
     * - unit_price
     * - discount_percentage
     */
    public function updateDetail(
        UpdateDetailSupplierQuotationRequest $request,
        string $supplier_quotation_id,
        string $detail_supplier_quotation_id
    ) {
        $supplierId = $request->user()->userSupplier?->supplier_id;

        abort_if($supplierId === null, 403, 'Akun supplier tidak terhubung dengan data supplier.');

        return response()->json([
            'message' => 'Detail Supplier Quotation berhasil diperbarui.',
            'data' => $this->supplierQuotationService
                ->updateDetail(
                    $supplier_quotation_id,
                    $detail_supplier_quotation_id,
                    $supplierId,
                    $request->validated()
                ),
        ]);
    }
}
