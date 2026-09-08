<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-02 Data Induk Barang + dukungan pemindaian barcode/QR (HF-04).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('sku', 40)->unique();
            $table->string('barcode', 60)->nullable()->unique();   // dipakai fitur Scan Barcode/QR
            $table->string('name');
            $table->string('unit', 20)->default('pcs');
            $table->decimal('sell_price', 18, 2)->default(0);
            $table->unsignedInteger('min_stock')->default(0);       // batas minimum default (peringatan stok)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
