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
        // Schema::create('detail_transactions', function (Blueprint $table) {
        //     $table->uuid('tr_detail_id')->primary();
        //     $table->uuid('tr_id');
        //     $table->uuid('item_id');
        //     $table->integer('item_quant');
        //     $table->integer('item_price');
        //     $table->integer('subtotal');
            
        //     $table->foreign('tr_id')->references('tr_id')->on('ms_transactions')->cascadeOnUpdate()->cascadeOnDelete();
        //     $table->foreign('item_id')->references('item_id')->on('items')->restrictOnUpdate()->restrictOnDelete();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_transactions');
    }
};
