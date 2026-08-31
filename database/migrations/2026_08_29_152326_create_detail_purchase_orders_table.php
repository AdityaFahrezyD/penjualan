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
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();

            $table->foreign('purchase_order_id')->references('purchase_order_id')->on('purchase_orders')->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreign('item_id')->references('item_id')->on('items')->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['purchase_order_id','item_id',]);
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
