<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-03 Penerimaan barang dari pemasok ke gudang utama (sumber lapisan FIFO).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                  // contoh: PB-260908-0001
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();   // lokasi penerimaan
            $table->foreignId('user_id')->constrained()->restrictOnDelete();     // admin pencatat
            $table->date('purchase_date');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 18, 2);                   // harga beli per satuan (dasar HPP FIFO)
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
