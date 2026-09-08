<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-03 Modul Mutasi Stok: pengiriman gudang -> cabang beserta harga pokok
// per lapisan FIFO supaya riwayat harga tetap terbawa ke cabang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                 // contoh: MT-260908-0001
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('stock_request_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'dikirim', 'diterima', 'dibatalkan'])->default('draft');
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['to_branch_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_sent');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->decimal('cost_total', 18, 2)->default(0);
            $table->json('cost_layers')->nullable();   // rincian lapisan FIFO yang dikirim
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
