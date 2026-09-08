<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Menyiapkan satu cabang, satu kasir, dan satu barang uji. */
function skenarioFifo(): array
{
    $branch = Branch::create([
        'code' => 'CB99', 'name' => 'Cabang Uji', 'type' => 'cabang', 'is_active' => true,
    ]);

    $user = User::create([
        'name' => 'Kasir Uji',
        'email' => 'kasir.uji@pos.test',
        'password' => 'password',
        'role' => 'kasir',
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);

    $product = Product::create([
        'sku' => 'UJI-001', 'name' => 'Barang Uji', 'unit' => 'pcs',
        'sell_price' => 15000, 'min_stock' => 0, 'is_active' => true,
    ]);

    $inventory = app(InventoryService::class);

    // Batch lama 10 x Rp10.000, batch baru 10 x Rp12.000.
    $inventory->receive($product->id, $branch->id, 10, 10000, receivedAt: now()->subDays(2));
    $inventory->receive($product->id, $branch->id, 10, 12000, receivedAt: now()->subDay());

    return [$branch, $user, $product, $inventory];
}

it('menghitung HPP penjualan dari batch yang masuk lebih dahulu (FIFO)', function () {
    [$branch, $user, $product, $inventory] = skenarioFifo();

    $sale = app(SaleService::class)->checkout([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'payment_method' => PaymentMethod::Tunai->value,
        'paid_amount' => 250000,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 15, 'unit_price' => 15000],
        ],
    ]);

    // 10 x 10.000 + 5 x 12.000 = 160.000
    expect((float) $sale->cogs_total)->toBe(160000.0)
        ->and((float) $sale->total)->toBe(225000.0)
        ->and((float) $sale->gross_profit)->toBe(65000.0)
        ->and($inventory->availableQuantity($product->id, $branch->id))->toBe(5)
        ->and($inventory->layerQuantity($product->id, $branch->id))->toBe(5);
});

it('mengembalikan stok dan lapisan FIFO ketika transaksi dibatalkan', function () {
    [$branch, $user, $product, $inventory] = skenarioFifo();
    $sales = app(SaleService::class);

    $sale = $sales->checkout([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'payment_method' => PaymentMethod::Tunai->value,
        'paid_amount' => 200000,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 12, 'unit_price' => 15000],
        ],
    ]);

    $sales->void($sale, $user->id, 'salah input');

    expect($sale->fresh()->status)->toBe(SaleStatus::Dibatalkan)
        ->and($inventory->availableQuantity($product->id, $branch->id))->toBe(20)
        ->and($inventory->layerQuantity($product->id, $branch->id))->toBe(20);
});

it('menolak penjualan ketika stok tidak mencukupi', function () {
    [$branch, $user, $product] = skenarioFifo();

    app(SaleService::class)->checkout([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'payment_method' => PaymentMethod::Tunai->value,
        'paid_amount' => 1000000,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 25, 'unit_price' => 15000],
        ],
    ]);
})->throws(InsufficientStockException::class);
