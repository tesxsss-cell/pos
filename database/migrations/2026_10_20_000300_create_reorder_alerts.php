<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('lead_time_days')->default(3));
        Schema::create('reorder_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['open', 'acknowledged', 'ordered', 'resolved'])->default('open');
            $table->enum('level', ['kritis', 'perlu_pesan']);
            $table->integer('current_qty');
            $table->unsignedInteger('reorder_point');
            $table->unsignedInteger('safety_stock');
            $table->decimal('avg_daily_sales', 18, 4);
            $table->unsignedInteger('suggested_qty');
            $table->timestamp('triggered_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            // Nilai 1 untuk peringatan aktif, null untuk riwayat; unik juga pada MySQL dan SQLite.
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->unique(['product_id', 'branch_id', 'active_slot']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reorder_alerts');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('lead_time_days'));
    }
};
