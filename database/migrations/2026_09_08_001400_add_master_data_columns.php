<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan skema: dua kolom sudah dipakai controller dan tampilan tetapi
 * belum pernah dibuat, sehingga nilainya selalu hilang saat disimpan.
 * - suppliers.contact_name  : nama orang yang dihubungi di pemasok
 * - stock_requests.response_note : alasan persetujuan/penolakan request stok
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_name', 100)->nullable()->after('name');
        });

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->text('response_note')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('contact_name');
        });

        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropColumn('response_note');
        });
    }
};
