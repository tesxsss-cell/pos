<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stock_transfers', 'stock_request_id')) {
            Schema::table('stock_transfers', fn (Blueprint $table) => $table->dropConstrainedForeignId('stock_request_id'));
        }
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }

    public function down(): void
    {
        throw new RuntimeException('Modul permintaan manual telah dihapus. Pulihkan cadangan untuk kembali ke versi sebelumnya.');
    }
};
