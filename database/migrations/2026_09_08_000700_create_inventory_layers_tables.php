<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-05 Inti metode FIFO: setiap barang masuk menjadi satu lapisan persediaan
// (batch) dengan harga belinya sendiri, dan pemakaiannya dicatat per lapisan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_cost', 18, 2);              // harga beli lapisan ini
            $table->unsignedInteger('quantity');              // jumlah masuk
            $table->unsignedInteger('remaining_quantity');    // sisa yang belum terpakai
            $table->nullableMorphs('source');                 // asal: Purchase / StockTransferItem
            $table->timestamp('received_at');                 // urutan FIFO
            $table->timestamps();

            $table->index(['product_id', 'branch_id', 'received_at'], 'inv_layers_fifo_index');
            $table->index(['branch_id', 'remaining_quantity']);
        });

        Schema::create('inventory_layer_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_layer_id')->constrained()->cascadeOnDelete();
            $table->morphs('consumer');                       // pemakai: SaleItem / StockTransferItem
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('cost_total', 18, 2);             // kontribusi terhadap HPP
            $table->timestamp('consumed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_layer_consumptions');
        Schema::dropIfExists('inventory_layers');
    }
};
