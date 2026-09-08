<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-06 Rekap shift kasir: uang tunai di laci vs catatan sistem.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();   // kasir
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 18, 2)->default(0);
            $table->decimal('expected_cash', 18, 2)->default(0);   // modal awal + penjualan tunai
            $table->decimal('actual_cash', 18, 2)->nullable();     // hasil hitung fisik laci
            $table->decimal('difference', 18, 2)->default(0);      // selisih (lebih/kurang)
            $table->enum('status', ['dibuka', 'ditutup'])->default('dibuka');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
};
