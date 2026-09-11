<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HF-04 Scan barcode barang dari halaman kasir.
 *
 * Satu barang boleh punya banyak barcode (kemasan berbeda, barcode toko sendiri,
 * atau barcode pabrik yang berubah). Tiap barcode menyimpan:
 *  - harga jual yang dipakai saat barcode dipindai (boleh kosong = ikut harga master)
 *  - jumlah bawaan yang langsung masuk keranjang saat dipindai
 *
 * Barcode pada kolom lama products.barcode ikut didaftarkan supaya barang yang
 * sudah ada tetap bisa langsung dipindai tanpa didaftarkan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('barcode', 60)->unique();               // hasil pemindaian (sudah dinormalkan)
            $table->decimal('sell_price', 18, 2)->nullable();      // null = selalu ikut harga master barang
            $table->unsignedInteger('default_quantity')->default(1); // jumlah bawaan saat dipindai
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id']);
        });

        // Pindahkan barcode lama agar langsung bisa dipakai fitur pemindaian baru.
        $waktu = now();

        DB::table('products')
            ->whereNotNull('barcode')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select(['id', 'barcode'])
            ->chunk(200, function ($barang) use ($waktu) {
                $baris = [];

                foreach ($barang as $satu) {
                    $kode = trim((string) $satu->barcode);

                    if ($kode === '') {
                        continue;
                    }

                    $baris[] = [
                        'product_id' => $satu->id,
                        'barcode' => $kode,
                        'sell_price' => null,          // ikut harga master
                        'default_quantity' => 1,
                        'branch_id' => null,
                        'registered_by' => null,
                        'created_at' => $waktu,
                        'updated_at' => $waktu,
                    ];
                }

                if ($baris !== []) {
                    DB::table('product_barcodes')->insertOrIgnore($baris);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
