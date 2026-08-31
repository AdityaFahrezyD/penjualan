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
        Schema::create('detail_purchase_requests', function (Blueprint $table) {
            $table->uuid('detail_purchase_request_id')->primary();
            $table->uuid('purchase_request_id');
            $table->uuid('item_id');
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('purchase_request_id')->references('purchase_request_id')->on('purchase_requests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('item_id')->references('item_id')->on('items')->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['purchase_request_id','item_id',]);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_purchase_requests');
    }
};
