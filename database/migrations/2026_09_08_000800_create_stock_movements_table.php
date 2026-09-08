<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-03 Kartu stok: satu sumber penelusuran semua perubahan persediaan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'pembelian_masuk', 'transfer_keluar', 'transfer_masuk',
                'penjualan_keluar', 'pembatalan_penjualan', 'penyesuaian_masuk', 'penyesuaian_keluar',
            ]);
            $table->integer('quantity');                 // positif = masuk, negatif = keluar
            $table->integer('balance_after');            // saldo stok setelah pergerakan
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->decimal('cost_total', 18, 2)->nullable();
            $table->nullableMorphs('reference');         // dokumen sumber
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'branch_id', 'created_at'], 'stock_card_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
