<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Checkout online sudah memakai hash untuk memvalidasi kirim ulang UUID.
        // Hanya tambahkan kolom yang belum ada, tanpa mengubah transaksi lama.
        if (! Schema::hasColumn('sales', 'request_hash')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->char('request_hash', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Sengaja mempertahankan hash transaksi untuk keamanan kirim ulang.
        // Rollback migrasi ini tidak menghapus data ataupun kolom yang sudah ada.
    }
};
