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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->uuid('purchase_request_id')->primary();
            $table->char('request_number', 20)->unique();
            $table->uuid('created_by');
            $table->date('request_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft','waiting_supplier','quotation_received', 'po_created', 'cancelled',
                ])->default('draft');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
