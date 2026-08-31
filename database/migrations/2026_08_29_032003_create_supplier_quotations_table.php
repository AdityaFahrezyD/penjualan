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
        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->uuid('supplier_quotation_id')->primary();
            $table->char('quotation_number', 20)->unique();
            $table->uuid('request_supplier_id');
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('status', ['draft','submitted','expired', 'po_created', 'not_selected', 'cancelled',
                ])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('request_supplier_id')->references('request_supplier_id')->on('request_suppliers')->cascadeOnUpdate()->cascadeOnDelete();

            $table->unique(['request_supplier_id',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quotations');
    }
};
