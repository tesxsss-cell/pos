<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PB + '-' + ymd + '-' + ULID = 36 karakter, melebihi VARCHAR(30).
        // Pertahankan nilai dan unique index yang sudah ada.
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('code', 64)->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak menyempitkan kolom: kode 36 karakter yang sudah
        // tersimpan tidak boleh terpotong atau membuat rollback gagal.
    }
};






