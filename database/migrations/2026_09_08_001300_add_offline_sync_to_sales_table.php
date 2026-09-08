<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom penanda transaksi hasil sinkronisasi offline.
     *
     * client_uuid dipakai sebagai kunci idempoten: kasir membuat UUID di
     * peramban, jadi transaksi yang sama tidak akan tersimpan dua kali walau
     * proses pengiriman ulang terjadi berkali-kali.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->char('client_uuid', 36)->nullable()->unique()->after('invoice_no');
            $table->timestamp('synced_at')->nullable()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn(['client_uuid', 'synced_at']);
        });
    }
};
