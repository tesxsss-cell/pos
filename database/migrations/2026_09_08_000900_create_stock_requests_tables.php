<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-03 Fitur "Request stok": permintaan stok dari cabang ke gudang utama.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                 // contoh: RQ-260908-0001
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();   // cabang peminta
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak', 'terpenuhi', 'dibatalkan'])->default('menunggu');
            $table->text('note')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }
};
