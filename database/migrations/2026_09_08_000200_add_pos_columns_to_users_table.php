<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-01 Autentikasi & Hak Akses: 4 peran (pemilik, admin, manager_cabang, kasir).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            $table->enum('role', ['pemilik', 'admin', 'manager_cabang', 'kasir'])->default('kasir')->after('email');
            $table->string('phone', 25)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};
