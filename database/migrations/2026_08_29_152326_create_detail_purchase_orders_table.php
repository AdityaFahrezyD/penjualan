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
        Schema::create('detail_purchase_orders', function (Blueprint $table) {
            $table->uuid('detail_purchase_order_id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('item_id');
            $table->foreignUuid('detail_purchase_request_id')->nullable()
                ->constrained('detail_purchase_requests', 'detail_purchase_request_id')->restrictOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('units', 'unit_id')->restrictOnDelete();
            $table->foreignUuid('base_unit_id')->nullable()->constrained('units', 'unit_id')->restrictOnDelete();
            $table->integer('quantity');
            $table->unsignedInteger('conversion_qty')->default(1);
            $table->unsignedInteger('base_quantity')->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();

            $table->foreign('purchase_order_id')->references('purchase_order_id')->on('purchase_orders')->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreign('item_id')->references('item_id')->on('items')->restrictOnUpdate()->restrictOnDelete();

            $table->index('purchase_order_id', 'po_detail_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_purchase_orders');
    }
};
