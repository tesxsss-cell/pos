<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_transfers') && Schema::hasColumn('stock_transfers', 'stock_request_id')) {
            // Kolom bisa ada tanpa FK, atau FK menggunakan nama non-default.
            // Baca constraint aktual; jangan menebak namanya dari nama kolom.
            foreach (Schema::getForeignKeys('stock_transfers') as $foreignKey) {
                if (in_array('stock_request_id', $foreignKey['columns'], true)) {
                    Schema::table('stock_transfers', function (Blueprint $table) use ($foreignKey) {
                        $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
                    });
                }
            }

            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->dropColumn('stock_request_id');
            });
        }

        // Penghapusan modul lama sesuai migrasi aslinya. Backup sebelum migrate.
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }

    public function down(): void
    {
        throw new RuntimeException('Modul permintaan manual telah dihapus. Pulihkan cadangan untuk kembali ke versi sebelumnya.');
    }
};
