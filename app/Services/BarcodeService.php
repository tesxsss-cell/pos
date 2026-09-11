<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * HF-04 Scan barcode barang dari halaman kasir.
 *
 * Alur yang dilayani kelas ini:
 *  1. Kasir memindai barcode.
 *  2. Bila barcode belum dikenal, kasir mengetik nama barang -> muncul rekomendasi
 *     barang yang namanya sesuai (suggest / matchByName).
 *  3. Kasir mengisi harga & jumlah bawaan lalu menyimpan (register).
 *  4. Saat pembeli datang, barcode cukup dipindai: informasi barang muncul dengan
 *     harga & jumlah tersimpan, dan keduanya masih bisa diubah di keranjang.
 *
 * Catatan harga: bila harga yang diisi sama dengan harga master barang, kolom
 * sell_price disimpan null sehingga harga barcode selalu mengikuti harga master
 * (harga tetap sama selama harga master tidak diubah).
 */
class BarcodeService
{
    /** Alat pemindai sering menambah spasi/enter pada ujung kode. */
    public function normalize(?string $code): string
    {
        return ProductBarcode::normalize($code);
    }

    /** Cari pemetaan barcode yang sudah didaftarkan. */
    public function resolve(?string $code): ?ProductBarcode
    {
        $kode = $this->normalize($code);

        if ($kode === '') {
            return null;
        }

        return ProductBarcode::query()->with('product')->where('barcode', $kode)->first();
    }

    /** Barang pemilik barcode: tabel pendaftaran dulu, lalu kolom barcode lama. */
    public function findProductByBarcode(?string $code): ?Product
    {
        $kode = $this->normalize($code);

        if ($kode === '') {
            return null;
        }

        return $this->resolve($kode)?->product
            ?? Product::query()->where('barcode', $kode)->first();
    }

    /**
     * Rekomendasi barang dari nama/SKU yang sedang diketik kasir.
     *
     * @return Collection<int, Product>
     */
    public function suggest(?string $term, int $limit = 8): Collection
    {
        $kata = trim((string) $term);

        if (mb_strlen($kata) < 2) {
            return collect();
        }

        $daftar = Product::query()
            ->active()
            ->search($kata)
            ->with('barcodes')
            ->orderBy('name')
            ->limit($limit * 2)
            ->get();

        $kecil = mb_strtolower($kata);

        // Nama yang paling mirip di urutan atas: sama persis -> awalan -> mengandung.
        return $daftar
            ->sortBy(function (Product $product) use ($kecil) {
                $nama = mb_strtolower((string) $product->name);

                return match (true) {
                    $nama === $kecil => 0,
                    str_starts_with($nama, $kecil) => 1,
                    str_contains($nama, $kecil) => 2,
                    default => 3,
                };
            })
            ->take($limit)
            ->values();
    }

    /**
     * Mencocokkan nama yang diketik kasir dengan satu barang.
     *
     * @return array{product: ?Product, suggestions: Collection<int, Product>}
     */
    public function matchByName(?string $name): array
    {
        $nama = trim((string) $name);
        $saran = $this->suggest($nama);
        $kecil = mb_strtolower($nama);

        $tepat = $saran->first(fn (Product $product) => mb_strtolower((string) $product->name) === $kecil
            || mb_strtolower((string) $product->sku) === $kecil);

        if ($tepat instanceof Product) {
            return ['product' => $tepat, 'suggestions' => $saran];
        }

        // Hanya satu kandidat -> tidak ambigu, boleh dipakai langsung.
        if ($saran->count() === 1) {
            return ['product' => $saran->first(), 'suggestions' => $saran];
        }

        return ['product' => null, 'suggestions' => $saran];
    }

    /**
     * Simpan (atau perbarui) pemetaan barcode -> barang beserta harga & jumlah bawaan.
     *
     * @param  array{barcode: string, product_id?: int|null, name?: string|null, sell_price?: float|string|null, default_quantity?: int|null, quantity?: int|null, update_product_price?: bool, replace?: bool}  $payload
     */
    public function register(array $payload, User $user): ProductBarcode
    {
        $kode = $this->normalize($payload['barcode'] ?? null);

        if ($kode === '') {
            throw new RuntimeException('Barcode belum terbaca. Pindai ulang atau ketik kodenya.');
        }

        if (mb_strlen($kode) > 60) {
            throw new RuntimeException('Barcode terlalu panjang (maksimal 60 karakter).');
        }

        $product = ! empty($payload['product_id'])
            ? Product::query()->find($payload['product_id'])
            : null;

        // Belum memilih dari rekomendasi -> cocokkan dari nama yang diketik.
        if (! $product instanceof Product && ! blank($payload['name'] ?? null)) {
            $cocok = $this->matchByName($payload['name']);
            $product = $cocok['product'];

            if (! $product instanceof Product) {
                throw new RuntimeException($cocok['suggestions']->isEmpty()
                    ? 'Nama barang tidak ditemukan. Periksa ejaannya, atau daftarkan barangnya lebih dahulu di menu Barang.'
                    : 'Nama barang masih cocok dengan beberapa barang. Pilih salah satu rekomendasi yang muncul.');
            }
        }

        if (! $product instanceof Product) {
            throw new RuntimeException('Barang belum dipilih. Ketik nama barang lalu pilih rekomendasinya.');
        }

        if (! $product->is_active) {
            throw new RuntimeException("Barang {$product->name} sedang tidak aktif sehingga tidak dapat dijual.");
        }

        $pindahkan = (bool) ($payload['replace'] ?? false);
        $terdaftar = ProductBarcode::query()->with('product')->where('barcode', $kode)->first();

        if ($terdaftar && $terdaftar->product_id !== $product->id && ! $pindahkan) {
            throw new RuntimeException(
                'Barcode ini sudah terdaftar untuk '.($terdaftar->product?->name ?? 'barang lain')
                .'. Centang "pindahkan barcode" bila memang ingin memindahkannya.'
            );
        }

        $pemakaiLama = Product::query()
            ->where('barcode', $kode)
            ->whereKeyNot($product->id)
            ->first();

        if ($pemakaiLama && ! $pindahkan) {
            throw new RuntimeException(
                'Barcode ini masih dipakai barang '.$pemakaiLama->name
                .'. Centang "pindahkan barcode" bila memang ingin memindahkannya.'
            );
        }

        // Harga boleh dibiarkan kosong (ikut harga master) atau diisi angka, termasuk 0.
        $hargaMentah = $payload['sell_price'] ?? null;
        $harga = ($hargaMentah === null || $hargaMentah === '')
            ? null
            : round((float) $hargaMentah, 2);

        $jumlah = max(1, (int) ($payload['default_quantity'] ?? $payload['quantity'] ?? 1));
        $bolehUbahMaster = $user->hasRole('admin', 'pemilik', 'manager_cabang');
        $ubahMaster = (bool) ($payload['update_product_price'] ?? false) && $bolehUbahMaster;

        return DB::transaction(function () use ($product, $kode, $harga, $jumlah, $ubahMaster, $pemakaiLama, $user) {
            if ($harga !== null && $ubahMaster) {
                $product->update(['sell_price' => $harga]);
                $product->refresh();
            }

            // Harga sama dengan harga master -> null, supaya harga otomatis ikut master.
            $hargaBarcode = $harga === null || abs($harga - (float) $product->sell_price) < 0.005
                ? null
                : $harga;

            $pemakaiLama?->update(['barcode' => null]);

            $barcode = ProductBarcode::query()->updateOrCreate(
                ['barcode' => $kode],
                [
                    'product_id' => $product->id,
                    'sell_price' => $hargaBarcode,
                    'default_quantity' => $jumlah,
                    'branch_id' => $user->branch_id,
                    'registered_by' => $user->id,
                ],
            );

            // Barcode utama data induk diisi bila masih kosong (agar laporan ikut rapi).
            if (blank($product->barcode)) {
                $product->update(['barcode' => $kode]);
            }

            return $barcode->fresh('product');
        });
    }

    /** Lepas satu barcode dari barangnya. */
    public function unregister(ProductBarcode $barcode): string
    {
        $kode = $barcode->barcode;
        $product = $barcode->product;

        DB::transaction(function () use ($barcode, $product, $kode) {
            $barcode->delete();

            if ($product && $product->barcode === $kode) {
                $sisa = ProductBarcode::query()->where('product_id', $product->id)->value('barcode');
                $product->update(['barcode' => $sisa]);
            }
        });

        return $kode;
    }

    /**
     * Bentuk data barang untuk halaman kasir (dipakai pencarian, pemindaian, & katalog offline).
     *
     * @return array<string, mixed>
     */
    public function present(Product $product, ?int $branchId, ?ProductBarcode $barcode = null): array
    {
        $master = (float) $product->sell_price;
        $harga = $barcode?->sell_price !== null && $barcode !== null ? (float) $barcode->sell_price : $master;

        $stok = $product->relationLoaded('stocks')
            ? (int) ($product->stocks->first()->quantity ?? 0)
            : ($branchId ? $product->stockAt($branchId) : 0);

        $daftarBarcode = $product->relationLoaded('barcodes')
            ? $product->barcodes
            : $product->barcodes()->get();

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'barcode' => $barcode?->barcode ?? $product->barcode,
            'matched_barcode' => $barcode?->barcode,
            'sell_price' => $harga,                                     // harga yang dipakai saat dipindai
            'base_price' => $master,                                    // harga master barang
            'price_source' => $barcode?->sell_price !== null && $barcode !== null ? 'barcode' : 'master',
            'default_quantity' => (int) ($barcode?->default_quantity ?? 1),
            'stock' => $stok,
            'barcodes' => $daftarBarcode
                ->map(fn (ProductBarcode $satu) => [
                    'id' => $satu->id,
                    'code' => $satu->barcode,
                    'price' => $satu->sell_price !== null ? (float) $satu->sell_price : $master,
                    'price_source' => $satu->sell_price !== null ? 'barcode' : 'master',
                    'quantity' => (int) $satu->default_quantity,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Query barang siap pakai untuk halaman kasir (stok cabang & barcode ikut dimuat).
     */
    public function productQuery(?int $branchId): Builder
    {
        return Product::query()
            ->with(['barcodes', 'stocks' => fn ($q) => $q->where('branch_id', $branchId)]);
    }
}
