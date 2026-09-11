<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Menyiapkan cabang, kasir, dan satu barang beserta stoknya. */
function skenarioBarcode(): array
{
    $branch = Branch::create([
        'code' => 'CB88', 'name' => 'Cabang Barcode', 'type' => 'cabang', 'is_active' => true,
    ]);

    $category = Category::create([
        'code' => 'KAT-BC', 'name' => 'Kebutuhan Dapur', 'is_active' => true,
    ]);

    $kasir = User::create([
        'name' => 'Kasir Barcode',
        'email' => 'kasir.barcode@pos.test',
        'password' => 'password',
        'role' => 'kasir',
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRC-001',
        'name' => 'Minyak Goreng 1 Liter',
        'unit' => 'pcs',
        'sell_price' => 18000,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    app(InventoryService::class)->receive($product->id, $branch->id, 20, 15000);

    return [$branch, $kasir, $product];
}

it('mendaftarkan barcode dari halaman kasir lalu memunculkan harga & jumlah tersimpan saat dipindai', function () {
    [$branch, $kasir, $product] = skenarioBarcode();

    // Kasir memindai barcode, mengetik nama barang, mengisi harga & jumlah, lalu menyimpan.
    $this->actingAs($kasir)
        ->postJson(route('pos.barcode.store'), [
            'barcode' => ' 8991002 100015 ',            // spasi dari alat pemindai ikut dibersihkan
            'name' => 'Minyak Goreng 1 Liter',
            'sell_price' => 19500,
            'quantity' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('barcode', '8991002100015');

    // Saat ada pembeli: barcode dipindai, informasi barang langsung muncul.
    $hasil = $this->actingAs($kasir)
        ->getJson(route('pos.barcode.resolve', ['barcode' => '8991002100015']))
        ->assertOk();

    expect($hasil->json('registered'))->toBeTrue()
        ->and($hasil->json('product.name'))->toBe('Minyak Goreng 1 Liter')
        ->and((float) $hasil->json('product.sell_price'))->toBe(19500.0)
        ->and($hasil->json('product.price_source'))->toBe('barcode')
        ->and((int) $hasil->json('product.default_quantity'))->toBe(2)
        ->and((int) $hasil->json('product.stock'))->toBe(20)
        // Harga master barang tidak berubah karena tidak diminta.
        ->and((float) $product->fresh()->sell_price)->toBe(18000.0);
});

it('menyimpan harga mengikuti master ketika harga tidak berubah', function () {
    [$branch, $kasir, $product] = skenarioBarcode();

    $this->actingAs($kasir)
        ->postJson(route('pos.barcode.store'), [
            'barcode' => 'AQUA600',
            'product_id' => $product->id,
            'sell_price' => 18000,   // sama dengan harga master
            'quantity' => 1,
        ])
        ->assertOk();

    // Harga disimpan null supaya selalu ikut harga master.
    expect(ProductBarcode::where('barcode', 'AQUA600')->value('sell_price'))->toBeNull();

    // Ketika harga master diperbarui, harga hasil pemindaian ikut menyesuaikan.
    $product->update(['sell_price' => 20000]);

    $hasil = $this->actingAs($kasir)
        ->getJson(route('pos.barcode.resolve', ['barcode' => 'AQUA600']))
        ->assertOk();

    expect((float) $hasil->json('product.sell_price'))->toBe(20000.0)
        ->and($hasil->json('product.price_source'))->toBe('master');
});

it('merekomendasikan barang dari nama yang diketik kasir', function () {
    [$branch, $kasir] = skenarioBarcode();

    $hasil = $this->actingAs($kasir)
        ->getJson(route('pos.barcode.suggest', ['q' => 'minyak']))
        ->assertOk();

    expect($hasil->json('data.0.name'))->toBe('Minyak Goreng 1 Liter');
});

it('melaporkan barcode yang belum terdaftar agar bisa didaftarkan kasir', function () {
    [$branch, $kasir] = skenarioBarcode();

    $hasil = $this->actingAs($kasir)
        ->getJson(route('pos.barcode.resolve', ['barcode' => '9999999999999']))
        ->assertOk();

    expect($hasil->json('registered'))->toBeFalse()
        ->and($hasil->json('product'))->toBeNull();
});

it('menolak barcode yang sudah dipakai barang lain kecuali diminta dipindahkan', function () {
    [$branch, $kasir, $product] = skenarioBarcode();

    $lain = Product::create([
        'category_id' => $product->category_id,
        'sku' => 'BRC-002',
        'name' => 'Gula Pasir 1 Kg',
        'unit' => 'pcs',
        'sell_price' => 14000,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($kasir)->postJson(route('pos.barcode.store'), [
        'barcode' => 'KODE-BERSAMA',
        'product_id' => $product->id,
    ])->assertOk();

    $this->actingAs($kasir)->postJson(route('pos.barcode.store'), [
        'barcode' => 'KODE-BERSAMA',
        'product_id' => $lain->id,
    ])->assertStatus(422);

    $this->actingAs($kasir)->postJson(route('pos.barcode.store'), [
        'barcode' => 'KODE-BERSAMA',
        'product_id' => $lain->id,
        'replace' => true,
    ])->assertOk();

    expect(ProductBarcode::where('barcode', 'KODE-BERSAMA')->value('product_id'))->toBe($lain->id);
});

it('mengembalikan barang pemilik barcode di urutan pertama pada pencarian kasir', function () {
    [$branch, $kasir, $product] = skenarioBarcode();

    $this->actingAs($kasir)->postJson(route('pos.barcode.store'), [
        'barcode' => '1122334455',
        'product_id' => $product->id,
        'sell_price' => 21000,
        'quantity' => 3,
    ])->assertOk();

    $hasil = $this->actingAs($kasir)
        ->getJson(route('pos.lookup', ['q' => '1122334455']))
        ->assertOk();

    expect($hasil->json('barcode_registered'))->toBeTrue()
        ->and($hasil->json('scanned_barcode'))->toBe('1122334455')
        ->and($hasil->json('data.0.id'))->toBe($product->id)
        ->and((float) $hasil->json('data.0.sell_price'))->toBe(21000.0)
        ->and((int) $hasil->json('data.0.default_quantity'))->toBe(3);
});
