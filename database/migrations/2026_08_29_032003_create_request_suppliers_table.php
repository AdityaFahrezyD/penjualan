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
        Schema::create('request_suppliers', function (Blueprint $table) {
            $table->uuid('request_supplier_id')->primary();
            $table->uuid('purchase_request_id');
            $table->uuid('supplier_id');
            $table->enum('status', ['pending','accepted','rejected','selected', 'not_selected',
                ])->default('pending');
            $table->datetime('sent_at')->nullable();
            $table->datetime('responded_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_request_id')->references('purchase_request_id')->on('purchase_requests')->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreign('supplier_id')->references('supplier_id')->on('suppliers')->restrictOnUpdate()->restrictOnDelete();

            $table->unique(['purchase_request_id','supplier_id',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_suppliers');
    }
};
