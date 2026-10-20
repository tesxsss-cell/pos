<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            throw new RuntimeException('Tabel users atau kolom role belum tersedia. Jalankan migrasi dasar proyek terlebih dahulu.');
        }

        $allowed = ['pemilik', 'admin', 'manager_cabang', 'pengelola', 'kasir'];
        $existing = DB::table('users')->distinct()->pluck('role')->all();
        foreach ($existing as $role) {
            if (! in_array($role, $allowed, true)) {
                throw new RuntimeException('Ada nilai role tidak dikenal/kosong pada tabel users. Periksa SELECT DISTINCT role FROM users sebelum melanjutkan; migrasi tidak mengubahnya otomatis.');
            }
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Perluas ENUM sebelum mengonversi data lama; jangan matikan strict mode.
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('pemilik','admin','manager_cabang','pengelola','kasir') NOT NULL DEFAULT 'kasir'");
            DB::table('users')->whereIn('role', ['pemilik', 'admin', 'manager_cabang'])->update(['role' => 'pengelola']);
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('pengelola','kasir') NOT NULL DEFAULT 'kasir'");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('users', fn ($table) => $table->string('role', 30)->default('kasir')->change());
            DB::table('users')->whereIn('role', ['pemilik', 'admin', 'manager_cabang'])->update(['role' => 'pengelola']);
            Schema::table('users', fn ($table) => $table->enum('role', ['pengelola', 'kasir'])->default('kasir')->change());
            return;
        }

        throw new RuntimeException('Migrasi perbaikan role ini mendukung MySQL/MariaDB dan SQLite.');
    }

    public function down(): void
    {
        // Sengaja tidak memetakan balik Pengelola: role asal tidak bisa direkonstruksi.
        // Tidak menghapus akun, password, stok, atau riwayat transaksi.
    }
};
