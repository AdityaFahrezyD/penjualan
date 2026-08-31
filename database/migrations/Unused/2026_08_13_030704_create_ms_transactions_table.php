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
        // Schema::create('ms_transactions', function (Blueprint $table) {
        //     $table->uuid('tr_id')->primary();
        //     $table->uuid('supplier_id');
        //     $table->date('tr_date');
        //     $table->enum('payment_method', ['cash', 'cashless'])->nullable();
        //     $table->integer('total')->default(0);
        //     $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
        //     $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->restrictOnUpdate()->restrictOnDelete();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ms_transactions');
    }
};
