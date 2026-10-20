<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tidak pernah menggabungkan saldo/riwayat beberapa lokasi secara diam-diam.
        if (DB::table('branches')->count() > 1) {
            throw new RuntimeException('Versi ini hanya untuk satu toko. Gunakan database baru; migrasi data multi-lokasi harus direkonsiliasi terpisah.');
        }
        $shop = DB::table('branches')->first();
        $shopId = $shop?->id ?? DB::table('branches')->insertGetId([
            'code' => 'TK01', 'name' => 'Toko Koperasi Desa', 'type' => 'cabang', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->where('id', $shopId)->update(['type' => 'cabang', 'is_active' => true]);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('pemilik','admin','manager_cabang','pengelola','kasir') NOT NULL DEFAULT 'kasir'");
        }
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('kasir')->change());
        }
        DB::table('users')->whereIn('role', ['pemilik', 'admin', 'manager_cabang'])->update(['role' => 'pengelola']);
        DB::table('users')->update(['branch_id' => $shopId]);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('pengelola','kasir') NOT NULL DEFAULT 'kasir'");
        }
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', fn (Blueprint $table) => $table->enum('role', ['pengelola', 'kasir'])->default('kasir')->change());
        }
        Schema::table('sales', function (Blueprint $table) {
            $table->char('request_hash', 64)->nullable();
            $table->string('void_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['request_hash', 'void_reason']));
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('pemilik','admin','manager_cabang','pengelola','kasir') NOT NULL DEFAULT 'kasir'");
        }
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('kasir')->change());
        }
        DB::table('users')->where('role', 'pengelola')->update(['role' => 'admin']);
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', fn (Blueprint $table) => $table->enum('role', ['pemilik', 'admin', 'manager_cabang', 'kasir'])->default('kasir')->change());
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('pemilik','admin','manager_cabang','kasir') NOT NULL DEFAULT 'kasir'");
        }
    }
};
