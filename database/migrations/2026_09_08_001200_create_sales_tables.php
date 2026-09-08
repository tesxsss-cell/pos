<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-04 Transaksi Penjualan (POS) + HF-05 penyimpanan HPP & laba kotor per baris.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();     // kasir
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->timestamp('sold_at');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('change_amount', 18, 2)->default(0);
            $table->enum('payment_method', ['tunai', 'transfer', 'qris', 'debit'])->default('tunai');
            $table->decimal('cogs_total', 18, 2)->default(0);       // HPP hasil FIFO
            $table->decimal('gross_profit', 18, 2)->default(0);     // laba kotor
            $table->enum('status', ['selesai', 'dibatalkan'])->default('selesai');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'sold_at']);
            $table->index(['user_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');        // salinan nama saat transaksi
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('cogs_total', 18, 2)->default(0);
            $table->decimal('gross_profit', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
