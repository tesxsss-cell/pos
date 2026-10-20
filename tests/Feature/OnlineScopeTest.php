<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
function onlineFixture(): array
{
    $shop = Branch::sole();
    $manager = User::create(['name' => 'Pengelola', 'email' => 'manager@test.local', 'password' => 'password', 'role' => 'pengelola', 'branch_id' => $shop->id, 'is_active' => true]);
    $cashier = User::create(['name' => 'Kasir', 'email' => 'cashier@test.local', 'password' => 'password', 'role' => 'kasir', 'branch_id' => $shop->id, 'is_active' => true]);
    $other = User::create(['name' => 'Kasir 2', 'email' => 'cashier2@test.local', 'password' => 'password', 'role' => 'kasir', 'branch_id' => $shop->id, 'is_active' => true]);
    $category = Category::create(['code' => 'KAT', 'name' => 'Sembako', 'is_active' => true]);
    $product = Product::create(['category_id' => $category->id, 'sku' => 'GULA', 'name' => 'Gula 1 kg', 'barcode' => '8991234567890', 'unit' => 'pak', 'sell_price' => 15000, 'min_stock' => 5, 'is_active' => true]);
    app(InventoryService::class)->receive($product->id, $shop->id, 10, 10000);
    $shift = CashierShift::create(['user_id' => $cashier->id, 'branch_id' => $shop->id, 'opened_at' => now(), 'opening_cash' => 100000, 'expected_cash' => 100000, 'status' => 'dibuka']);

    return compact('shop', 'manager', 'cashier', 'other', 'category', 'product', 'shift');
}
function onlinePayload(array $f, array $overrides = []): array
{
    return array_replace(['request_id' => (string) Str::uuid(), 'cashier_shift_id' => $f['shift']->id, 'payment_method' => 'tunai', 'paid_amount' => 50000, 'items' => [['product_id' => $f['product']->id, 'quantity' => 2]]], $overrides);
}
it('hanya memiliki dua peran dan tidak mendaftarkan rute fitur yang dihapus', function () {
    expect(array_keys(UserRole::options()))->toBe(['pengelola', 'kasir']);
    foreach (['pos.sync', 'pos.catalog', 'pos.sync-barcode', 'pos.barcode.store', 'transfers.index', 'branches.index', 'reorder.acknowledge'] as $route) {
        expect(Route::has($route))->toBeFalse();
    }
});
it('merender seluruh halaman pengelola dengan satu toko', function () {
    $f = onlineFixture();
    $this->actingAs($f['manager']);
    foreach (['dashboard', 'products.index', 'products.create', 'categories.index', 'categories.create', 'suppliers.index', 'suppliers.create', 'users.index', 'users.create', 'purchases.index', 'purchases.create', 'sales.index', 'reports.daily', 'reports.monthly', 'reports.profit', 'reports.stock', 'reports.stock-card', 'reports.shift'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('users.edit', $f['cashier']))->assertOk();
    $this->get(route('users.edit', $f['manager']))->assertOk();
    $this->get(route('products.edit', $f['product']))->assertOk();
});
it('mengarahkan kasir ke POS dan tidak membuka shift otomatis', function () {
    $f = onlineFixture();
    $this->actingAs($f['other'])->get(route('dashboard'))->assertRedirect(route('pos.index'));
    $this->get(route('pos.index'))->assertOk()->assertSee('Buka shift terlebih dahulu.');
    expect($f['other']->openShift())->toBeNull();
});
it('membatasi akses data induk laporan dan POS sesuai peran', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier']);
    foreach (['products.index', 'users.index', 'purchases.index', 'sales.index', 'reports.daily', 'reports.shift'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
    $this->actingAs($f['manager'])->get(route('pos.index'))->assertForbidden();
});
it('kasir dapat mencari barcode online dan mencatat transaksi harga server', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier'])->getJson(route('pos.lookup', ['q' => $f['product']->barcode]))->assertOk()->assertJsonPath('data.0.name', 'Gula 1 kg');
    $this->postJson(route('pos.checkout'), onlinePayload($f))->assertOk()->assertJsonPath('total', 30000)->assertJsonPath('change_amount', 20000);
    expect(Stock::first()->quantity)->toBe(8)->and((float) $f['shift']->fresh()->expected_cash)->toBe(130000.0);
});
it('retry UUID yang sama tidak menggandakan penjualan stok atau kas', function () {
    $f = onlineFixture();
    $payload = onlinePayload($f);
    $this->actingAs($f['cashier']);
    $one = $this->postJson(route('pos.checkout'), $payload)->assertOk()->json('invoice_no');
    $this->postJson(route('pos.checkout'), $payload)->assertOk()->assertJsonPath('invoice_no', $one);
    expect(Sale::count())->toBe(1)->and(Stock::first()->quantity)->toBe(8)->and((float) $f['shift']->fresh()->expected_cash)->toBe(130000.0);
});
it('UUID yang sama tidak boleh dipakai dengan isi transaksi berbeda', function () {
    $f = onlineFixture();
    $payload = onlinePayload($f);
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), $payload)->assertOk();
    $payload['items'][0]['quantity'] = 3;
    $this->postJson(route('pos.checkout'), $payload)->assertUnprocessable();
    expect(Sale::count())->toBe(1);
});
it('menolak manipulasi harga diskon dan jumlah pecahan', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier']);
    $p = onlinePayload($f);
    $p['items'][0]['unit_price'] = 1;
    $this->postJson(route('pos.checkout'), $p)->assertUnprocessable();
    $p = onlinePayload($f, ['discount' => 100]);
    $this->postJson(route('pos.checkout'), $p)->assertUnprocessable();
    $p = onlinePayload($f);
    $p['items'][0]['quantity'] = 0.5;
    $this->postJson(route('pos.checkout'), $p)->assertUnprocessable();
    expect(Sale::count())->toBe(0);
});
it('menolak stok kurang dan rollback seluruh transaksi', function () {
    $f = onlineFixture();
    $p = onlinePayload($f, ['paid_amount' => 999999]);
    $p['items'][0]['quantity'] = 11;
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), $p)->assertUnprocessable();
    expect(Sale::count())->toBe(0)->and(Stock::first()->quantity)->toBe(10)->and((float) $f['shift']->fresh()->expected_cash)->toBe(100000.0);
});
it('menolak barang nonaktif', function () {
    $f = onlineFixture();
    $f['product']->update(['is_active' => false]);
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), onlinePayload($f))->assertUnprocessable();
    expect(Sale::count())->toBe(0);
});
it('pembayaran tunai kurang tidak disimpan', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), onlinePayload($f, ['paid_amount' => 100]))->assertUnprocessable();
    expect(Sale::count())->toBe(0);
});
it('pembayaran non tunai harus dikonfirmasi dan tidak menambah kas laci', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier']);
    $this->postJson(route('pos.checkout'), onlinePayload($f, ['payment_method' => 'qris']))->assertUnprocessable();
    $this->postJson(route('pos.checkout'), onlinePayload($f, ['payment_method' => 'qris', 'payment_confirmed' => true, 'paid_amount' => null]))->assertOk();
    expect((float) $f['shift']->fresh()->expected_cash)->toBe(100000.0)->and((float) Sale::first()->paid_amount)->toBe(30000.0);
});
it('shift harus dibuka eksplisit dan satu akun tidak boleh membuka dua kali', function () {
    $f = onlineFixture();
    $this->actingAs($f['other']);
    $this->post(route('pos.shift.open'), ['opening_cash' => 200000])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('pos.shift.open'), ['opening_cash' => 1])->assertSessionHasErrors('opening_cash');
    expect($f['other']->cashierShifts()->where('status', 'dibuka')->count())->toBe(1);
});
it('transaksi wajib menggunakan shift aktif milik kasir sendiri', function () {
    $f = onlineFixture();
    $this->actingAs($f['other'])->postJson(route('pos.checkout'), onlinePayload($f))->assertUnprocessable();
    $f['shift']->update(['status' => 'ditutup', 'closed_at' => now()]);
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), onlinePayload($f))->assertUnprocessable();
    expect(Sale::count())->toBe(0);
});
it('tutup shift menghitung selisih dan retry transaksi lama tetap idempoten', function () {
    $f = onlineFixture();
    $p = onlinePayload($f);
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), $p)->assertOk();
    $this->post(route('pos.shift.close'), ['actual_cash' => 129000])->assertRedirect()->assertSessionHasNoErrors();
    expect((float) $f['shift']->fresh()->difference)->toBe(-1000.0)->and($f['shift']->fresh()->status)->toBe('ditutup');
    $this->postJson(route('pos.checkout'), $p)->assertOk();
    expect(Sale::count())->toBe(1);
});
it('hanya Pengelola dapat membatalkan transaksi beserta stok dan kas', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), onlinePayload($f))->assertOk();
    $sale = Sale::first();
    $this->post(route('pos.void', $sale), ['reason' => 'Salah', 'confirm_return' => 1])->assertForbidden();
    $this->actingAs($f['manager'])->post(route('pos.void', $sale), ['reason' => '', 'confirm_return' => 1])->assertSessionHasErrors('reason');
    $this->post(route('pos.void', $sale), ['reason' => 'Transaksi ganda', 'confirm_return' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect(Sale::count())->toBe(1)->and($sale->fresh()->void_reason)->toBe('Transaksi ganda')->and($sale->fresh()->status->value)->toBe('dibatalkan')->and(Stock::first()->quantity)->toBe(10)->and((float) $f['shift']->fresh()->expected_cash)->toBe(100000.0);
    $this->post(route('pos.void', $sale), ['reason' => 'Ulang', 'confirm_return' => 1])->assertSessionHasErrors('reason');
    expect(Stock::first()->quantity)->toBe(10);
});
it('pembatalan non tunai tidak mengurangi laci dan laporan mengecualikan void', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), onlinePayload($f, ['payment_method' => 'transfer', 'payment_confirmed' => true]))->assertOk();
    $this->actingAs($f['manager'])->post(route('pos.void', Sale::first()), ['reason' => 'Salah transaksi', 'confirm_return' => 1])->assertSessionHasNoErrors();
    expect((float) $f['shift']->fresh()->expected_cash)->toBe(100000.0)->and(app(ReportService::class)->summary($f['shop']->id, now()->toDateString(), now()->toDateString())['transactions'])->toBe(0);
});
it('melarang void setelah shift tutup agar rekap tidak berubah diam diam', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), onlinePayload($f))->assertOk();
    $this->post(route('pos.shift.close'), ['actual_cash' => 130000]);
    $this->actingAs($f['manager'])->post(route('pos.void', Sale::first()), ['reason' => 'Terlambat', 'confirm_return' => 1])->assertSessionHasErrors('reason');
    expect(Stock::first()->quantity)->toBe(8);
});
it('kasir hanya bisa melihat struk sendiri dan status batal terlihat', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), onlinePayload($f))->assertOk();
    $sale = Sale::first();
    $this->get(route('pos.receipt', $sale))->assertOk();
    $this->actingAs($f['other'])->get(route('pos.receipt', $sale))->assertForbidden();
    $this->actingAs($f['manager'])->post(route('pos.void', $sale), ['reason' => 'Salah', 'confirm_return' => 1]);
    $this->get(route('pos.receipt', $sale))->assertOk()->assertSee('TRANSAKSI DIBATALKAN');
});
it('Pengelola membuat kasir tanpa bisa mengeskalasi peran', function () {
    $f = onlineFixture();
    $this->actingAs($f['manager']);
    $data = ['name' => 'Kasir Baru', 'email' => 'baru@test.local', 'password' => 'Password2026!', 'password_confirmation' => 'Password2026!', 'role' => 'kasir', 'is_active' => 1];
    $this->post(route('users.store'), $data)->assertRedirect(route('users.index'));
    expect(User::where('email', $data['email'])->first()->role->value)->toBe('kasir');
    $data['email'] = 'escalate@test.local';
    $data['role'] = 'pengelola';
    $this->post(route('users.store'), $data)->assertSessionHasErrors('role');
});
it('akun nonaktif tidak dapat menggunakan sesi lama', function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier']);
    $f['cashier']->update(['is_active' => false]);
    $this->get(route('pos.index'))->assertForbidden();
});
it('tidak menonaktifkan akun sendiri atau kasir dengan shift terbuka', function () {
    $f = onlineFixture();
    $this->actingAs($f['manager'])->delete(route('users.destroy', $f['manager']))->assertSessionHasErrors('name');
    $this->delete(route('users.destroy', $f['cashier']))->assertSessionHasErrors('name');
    expect($f['cashier']->fresh()->is_active)->toBeTrue();
});
it('mencatat penerimaan ke satu toko dan posting hanya sekali', function () {
    $f = onlineFixture();
    $supplier = Supplier::create(['code' => 'SUP', 'name' => 'Pemasok', 'is_active' => true]);
    $this->actingAs($f['manager']);
    $this->post(route('purchases.store'), ['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'items' => [['product_id' => $f['product']->id, 'quantity' => 10, 'unit_cost' => 14000]]])->assertRedirect();
    $purchase = Purchase::first();
    $this->get(route('purchases.show', $purchase))->assertOk();
    $this->post(route('purchases.post', $purchase))->assertRedirect()->assertSessionHasNoErrors();
    expect(Stock::first()->quantity)->toBe(20)->and((float) Stock::first()->avg_cost)->toBe(12000.0);
    $this->post(route('purchases.post', $purchase))->assertSessionHasErrors('status');
    expect(Stock::first()->quantity)->toBe(20);
});
it('barang baru mempunyai saldo nol dan muncul pada peringatan minimum', function () {
    $f = onlineFixture();
    $this->actingAs($f['manager'])->post(route('products.store'), ['sku' => 'BARU', 'name' => 'Barang Baru', 'category_id' => $f['category']->id, 'unit' => 'pak', 'sell_price' => 10000, 'min_stock' => 3, 'is_active' => 1])->assertRedirect(route('products.index'));
    $product = Product::where('sku', 'BARU')->firstOrFail();
    expect($product->stocks()->first()->quantity)->toBe(0);
    $this->get(route('dashboard'))->assertOk()->assertSee('Barang Baru');
    $this->get(route('reports.stock'))->assertOk()->assertSee('Barang Baru');
});
it('stok positif tidak boleh diarsipkan dan histori tetap tersimpan', function () {
    $f = onlineFixture();
    $this->actingAs($f['manager'])->delete(route('products.destroy', $f['product']))->assertSessionHasErrors('name');
    expect($f['product']->fresh())->not->toBeNull();
});
it('seeder menghasilkan satu Pengelola dua Kasir dan aman dijalankan ulang', function () {
    $this->seed(DatabaseSeeder::class);
    $qty = Stock::sum('quantity');
    $count = Purchase::count();
    $this->seed(DatabaseSeeder::class);
    expect(Branch::count())->toBe(1)->and(User::where('role', 'pengelola')->count())->toBe(1)->and(User::where('role', 'kasir')->count())->toBe(2)->and(Stock::sum('quantity'))->toBe($qty)->and(Purchase::count())->toBe($count);
});
it('menolak rentang tanggal laporan yang terbalik', function () {
    $f = onlineFixture();
    $this->actingAs($f['manager'])->get(route('reports.daily', ['dari' => '2026-10-20', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
});

it('menolak harga keranjang yang sudah berubah tanpa mengubah stok', function () {
    $f = onlineFixture();
    $p = onlinePayload($f);
    $p['items'][0]['expected_price'] = 14000;
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), $p)->assertUnprocessable()->assertJsonPath('message', 'Harga barang berubah. Hapus barang dari keranjang lalu cari ulang sebelum membayar.');
    expect(Stock::first()->quantity)->toBe(10)->and(Sale::count())->toBe(0);
});
it('ID transaksi milik kasir lain tidak membocorkan struk', function () {
    $f = onlineFixture();
    $p = onlinePayload($f);
    $this->actingAs($f['cashier'])->postJson(route('pos.checkout'), $p)->assertOk();
    $this->actingAs($f['other'])->postJson(route('pos.checkout'),$p)->assertUnprocessable();
    expect(Sale::count())->toBe(1);
});
it('endpoint offline lama tidak tersedia dan respons POS tidak boleh di cache',function () {
    $f = onlineFixture();
    $this->actingAs($f['cashier']);
    foreach (['/kasir/sinkronisasi', '/kasir/sinkronisasi-barcode'] as $url) {
        $this->postJson($url,[])->assertNotFound();
    }
    $this->get(route('pos.index'))->assertOk()->assertHeader('Cache-Control','max-age=0, no-store, private');
});
