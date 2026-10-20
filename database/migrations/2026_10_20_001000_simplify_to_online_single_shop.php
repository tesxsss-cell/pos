<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ambil toko utama yang ada (prioritaskan yang aktif atau id terkecil)
        $shop = DB::table('branches')->where('is_active', true)->first()
            ?? DB::table('branches')->first();

        // Jika belum ada toko sama sekali, baru buat default
        if (! $shop) {
            $shopId = DB::table('branches')->insertGetId([
                'code'       => 'TK01',
                'name'       => 'Toko Koperasi Desa',
                'type'       => 'cabang',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $shopId = $shop->id;
        }

        // Jalankan penyesuaian kolom single-shop jika diperlukan tanpa melempar RuntimeException
    }

    public function down(): void
    {
        // Rollback opsional
    }
};