<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-03 Persediaan: saldo stok ringkas per barang per lokasi (gudang/cabang).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->unsignedInteger('min_stock')->nullable();  // override batas minimum per lokasi
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
            $table->index(['branch_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
