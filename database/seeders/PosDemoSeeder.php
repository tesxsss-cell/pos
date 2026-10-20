<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Aman dijalankan ulang: tidak mereset akun, stok, atau transaksi yang sudah ada.
        $shop = Branch::query()->sole();
        foreach ([['Pengelola', 'pengelola@pos.test', 'pengelola'], ['Kasir Satu', 'kasir1@pos.test', 'kasir'], ['Kasir Dua', 'kasir2@pos.test', 'kasir']] as [$name,$email,$role]) {
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'DemoKopdes2026!', 'role' => $role, 'branch_id' => $shop->id, 'is_active' => true]);
        }
        $manager = User::where('email', 'pengelola@pos.test')->firstOrFail();
        $category = Category::firstOrCreate(['code' => 'SEMBAKO'], ['name' => 'Sembako', 'is_active' => true]);
        $supplier = Supplier::firstOrCreate(['code' => 'SUP01'], ['name' => 'Distributor Pangan Desa', 'phone' => '080000000001', 'is_active' => true]);
        $rows = [['BERAS5', 'Beras Kemasan 5 kg', '8991001100011', 'karung', 72000, 60000], ['MINYAK1', 'Minyak Goreng 1 L', '8991001100028', 'botol', 18000, 14000],
            ['GULA1', 'Gula Pasir 1 kg', '8991001100035', 'pak', 18000, 14000], ['MIE1', 'Mie Instan', '8991001100059', 'bungkus', 3500, 2500]];
        foreach ($rows as [$sku,$name,$barcode,$unit,$price,$cost]) {
            // Satu barang + saldo awalnya harus atomik, agar kegagalan tidak
            // menyisakan barang tanpa penerimaan pada percobaan berikutnya.
            DB::transaction(function () use ($sku, $name, $barcode, $unit, $price, $cost, $category, $supplier, $shop, $manager) {
                $product = Product::firstOrCreate(['sku' => $sku], ['category_id' => $category->id, 'name' => $name, 'barcode' => $barcode, 'unit' => $unit, 'sell_price' => $price, 'min_stock' => 5, 'is_active' => true]);
                if (! $product->wasRecentlyCreated) {
                    // Pulihkan hanya barang demo identik yang belum pernah dipakai.
                    // Ini mencakup BERAS5 yang tertinggal saat seed lama gagal.
                    $sameDemo = $product->name === $name
                        && $product->barcode === $barcode
                        && $product->unit === $unit
                        && (int) $product->category_id === (int) $category->id
                        && (float) $product->sell_price === (float) $price
                        && (int) $product->min_stock === 5
                        && $product->is_active;
                    $hasActivity = $product->movements()->exists()
                        || DB::table('purchase_items')->where('product_id', $product->id)->exists()
                        || DB::table('sale_items')->where('product_id', $product->id)->exists()
                        || $product->stocks()->where(function ($query) {
                            $query->where('quantity', '<>', 0)->orWhere('avg_cost', '<>', 0);
                        })->exists();
                    if (! $sameDemo || $hasActivity) {
                        return;
                    }
                }
                Stock::firstOrCreate(['product_id' => $product->id, 'branch_id' => $shop->id], ['quantity' => 0, 'avg_cost' => 0]);
                $service = app(PurchaseService::class);
                $purchase = $service->create(['supplier_id' => $supplier->id, 'branch_id' => $shop->id, 'purchase_date' => now()->toDateString(), 'note' => 'Saldo awal demo'], [['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => $cost]], $manager->id);
                $service->post($purchase, $manager->id);
            });
        }
        $this->command?->info('Demo: pengelola@pos.test, kasir1@pos.test, kasir2@pos.test | Sandi: DemoKopdes2026! (ganti sebelum digunakan).');
    }
}


