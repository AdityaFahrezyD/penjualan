<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('purchase_order_id')->primary();
            $table->char('po_number', 20)->unique();
            $table->uuid('purchase_request_id');
            $table->uuid('supplier_id');
            $table->uuid('supplier_quotation_id');
            $table->uuid('created_by');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('status', ['draft','sent','accepted','shipping','delivered','completed','failed','cancelled',
                ])->default('draft');
            $table->enum('payment_status', ['unpaid','partially_paid','paid',
                ])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_request_id')->references('purchase_request_id')->on('purchase_requests')->restrictOnUpdate()->restrictOnDelete();

            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->restrictOnUpdate()->restrictOnDelete();

            $table->foreign('supplier_quotation_id')->references('supplier_quotation_id')->on('supplier_quotations')->restrictOnUpdate()->restrictOnDelete();

            $table->foreign('created_by')->references('id')->on('users')->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['supplier_quotation_id',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
