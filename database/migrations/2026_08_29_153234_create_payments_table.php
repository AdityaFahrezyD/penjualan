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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('payment_id')->primary();
            $table->char('payment_number', 20)->unique();
            $table->uuid('purchase_order_id');
            $table->uuid('created_by');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash','bank_transfer','ewallet',]);
            $table->datetime('payment_date')->nullable();
            // $table->string('payment_proof')->nullable();
            $table->enum('status', ['draft','waiting_confirmation','confirmed','rejected','cancelled',
                ])->default('draft');
            $table->datetime('confirmed_at')->nullable();
            $table->uuid('confirmed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('purchase_order_id')->on('purchase_orders')->restrictOnUpdate()->restrictOnDelete();

            $table->foreign('created_by')->references('id')->on('users')->restrictOnUpdate()->restrictOnDelete();

            $table->foreign('confirmed_by')->references('id')->on('users')->restrictOnUpdate()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
