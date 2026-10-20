<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function pengelolaUji($branch)
{
    return User::firstOrCreate(['email' => 'pengelola-uji@pos.test'], ['name' => 'Pengelola Uji', 'password' => 'password', 'role' => 'pengelola', 'branch_id' => $branch->id, 'is_active' => true]);
}

/** Menyiapkan dua penerimaan: 10 x 10.000 dan 20 x 13.000. */
function skenarioRataRata(): array
{
    $branch = Branch::sole();
    $category = Category::create(['code' => 'UJI', 'name' => 'Sembako Uji', 'is_active' => true]);
    $user = User::create(['name' => 'Pengurus Uji', 'email' => 'uji@kopdes.test', 'password' => 'password',
        'role' => 'kasir', 'branch_id' => $branch->id, 'is_active' => true]);
    $product = Product::create(['category_id' => $category->id, 'sku' => 'UJI-001', 'name' => 'Gula Uji',
        'unit' => 'pak', 'sell_price' => 15000, 'min_stock' => 0, 'is_active' => true]);
    CashierShift::create(['user_id' => $user->id, 'branch_id' => $branch->id, 'opened_at' => now(), 'opening_cash' => 0, 'expected_cash' => 0, 'status' => 'dibuka']);
    $inventory = app(InventoryService::class);
    $inventory->receive($product->id, $branch->id, 10, 10000);
    $inventory->receive($product->id, $branch->id, 20, 13000);

    return [$branch, $user, $product, $inventory];
}

function penjualanUji($branch, $user, $product, int $quantity = 5)
{
    return app(SaleService::class)->checkout(['request_id' => (string) Str::uuid(), 'cashier_shift_id' => $user->openShift()->id, 'payment_method' => 'tunai', 'branch_id' => $branch->id, 'user_id' => $user->id,
        'paid_amount' => 1000000, 'items' => [['product_id' => $product->id, 'quantity' => $quantity]]]);
}

it('menghitung rata-rata tertimbang dua penerimaan dan nilai persediaan', function () {
    [$branch, , $product, $inventory] = skenarioRataRata();
    expect((float) Stock::first()->avg_cost)->toBe(12000.0)
        ->and($inventory->availableQuantity($product->id, $branch->id))->toBe(30)
        ->and($inventory->inventoryValue($branch->id))->toBe(360000.0);
});

it('menyimpan HPP baris penjualan serta laba kotor tanpa mengubah rata-rata', function () {
    [$branch, $user, $product] = skenarioRataRata();
    $sale = penjualanUji($branch, $user, $product);
    $summary = app(ReportService::class)->summary($branch->id, now()->toDateString(), now()->toDateString());
    expect((float) $sale->cogs_total)->toBe(60000.0)
        ->and((float) $sale->gross_profit)->toBe(15000.0)
        ->and((float) $sale->items[0]->unit_cost)->toBe(12000.0)
        ->and((float) Stock::first()->avg_cost)->toBe(12000.0)
        ->and($summary['cogs'])->toBe(60000.0)
        ->and($summary['gross_profit'])->toBe(15000.0);
});

it('mengembalikan barang memakai biaya penjualan asli setelah harga rata-rata berubah', function () {
    [$branch, $user, $product, $inventory] = skenarioRataRata();
    $sale = penjualanUji($branch, $user, $product, 10);
    $inventory->receive($product->id, $branch->id, 10, 18000);
    expect((float) Stock::first()->avg_cost)->toBe(14000.0);
    app(SaleService::class)->void($sale, pengelolaUji($branch)->id, 'Salah transaksi');
    expect(Stock::first()->quantity)->toBe(40)
        ->and((float) Stock::first()->avg_cost)->toBe(13500.0)
        ->and((float) StockMovement::latest('id')->first()->unit_cost)->toBe(12000.0)
        ->and(app(ReportService::class)->summary($branch->id, now()->toDateString(), now()->toDateString())['cogs'])->toBe(0.0);
});

it('mencegah pembatalan berulang', function () {
    [$branch, $user, $product] = skenarioRataRata();
    $sale = penjualanUji($branch, $user, $product);
    app(SaleService::class)->void($sale, pengelolaUji($branch)->id, 'Salah transaksi');
    expect(fn () => app(SaleService::class)->void($sale, pengelolaUji($branch)->id, 'Salah transaksi'))->toThrow(RuntimeException::class);
    expect(Stock::first()->quantity)->toBe(30);
});

it('membulatkan rata-rata empat desimal dan HPP dua desimal secara half-up', function () {
    [$branch, $user, $product, $inventory] = skenarioRataRata();
    penjualanUji($branch, $user, $product, 30);
    $inventory->receive($product->id, $branch->id, 1, 10000);
    $inventory->receive($product->id, $branch->id, 2, 10001);
    expect((float) Stock::first()->avg_cost)->toBe(10000.6667);
    $sale = penjualanUji($branch, $user, $product, 2);
    expect((float) $sale->cogs_total)->toBe(20001.33)
        ->and((float) StockMovement::latest('id')->first()->avg_cost_after)->toBe(10000.6667);
    $inventory->receive($product->id, $branch->id, 1, 10000.6767);
    expect((float) Stock::first()->avg_cost)->toBe(10000.6717);
});

it('membulatkan total tepat setengah sen ke atas', function () {
    [$branch, $user, $product, $inventory] = skenarioRataRata();
    penjualanUji($branch, $user, $product, 30);
    $inventory->receive($product->id, $branch->id, 1, 1.005);
    expect((float) penjualanUji($branch, $user, $product, 1)->cogs_total)->toBe(1.01);
});

it('menolak stok tidak cukup dan membatalkan seluruh transaksi', function () {
    [$branch, $user, $product] = skenarioRataRata();
    expect(fn () => penjualanUji($branch, $user, $product, 31))->toThrow(InsufficientStockException::class);
    expect(Stock::first()->quantity)->toBe(30)->and(Sale::count())->toBe(0);
});
