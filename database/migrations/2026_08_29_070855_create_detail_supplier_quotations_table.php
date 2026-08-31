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
        Schema::create('detail_supplier_quotations', function (Blueprint $table) {
            $table->uuid('detail_supplier_quotation_id')->primary();
            $table->uuid('supplier_quotation_id');
            $table->uuid('detail_purchase_request_id');
            // Harga per unit dari supplier
            $table->decimal('unit_price', 15, 2);
            // Supplier memasukkan angka 0–100
            // Contoh: 5 = 5%, 10 = 10%
            $table->decimal('discount_percentage', 5, 2)->default(0);
            // Nilai diskon dalam rupiah, hasil perhitungan backend
            $table->decimal('discount_amount', 15, 2)->default(0);
            // Total setelah diskon
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->foreign('supplier_quotation_id')->references('supplier_quotation_id')->on('supplier_quotations')->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreign('detail_purchase_request_id')->references('detail_purchase_request_id')->on('detail_purchase_requests')->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['supplier_quotation_id','detail_purchase_request_id',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_supplier_quotations');
    }
};
