<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HF-02 Pengelolaan Data Induk: gudang utama & cabang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // contoh: GDG-01, CBG-01
            $table->string('name');
            $table->enum('type', ['gudang', 'cabang'])->default('cabang');
            $table->string('phone', 25)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
