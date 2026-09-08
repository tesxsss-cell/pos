<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\StockRequestStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\StockTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data simulasi sesuai project charter: 1 gudang pusat + 2 cabang, empat peran
 * pengguna, penerimaan barang dua batch harga berbeda (bukti FIFO), request
 * stok cabang, mutasi stok, dan beberapa transaksi penjualan.
 */
class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gudang = Branch::create([
            'code' => 'GD01', 'name' => 'Gudang Pusat', 'type' => 'gudang',
            'phone' => '0411-000001', 'address' => 'Jl. Perintis Kemerdekaan No. 1, Makassar', 'is_active' => true,
        ]);

        $cabangA = Branch::create([
            'code' => 'CB01', 'name' => 'Cabang Panakkukang', 'type' => 'cabang',
            'phone' => '0411-000002', 'address' => 'Jl. Boulevard No. 12, Makassar', 'is_active' => true,
        ]);

        $cabangB = Branch::create([
            'code' => 'CB02', 'name' => 'Cabang Sudirman', 'type' => 'cabang',
            'phone' => '0411-000003', 'address' => 'Jl. Jend. Sudirman No. 5, Makassar', 'is_active' => true,
        ]);

        $pemilik = $this->user('Pemilik Usaha', 'pemilik@pos.test', UserRole::Pemilik, null);
        $admin = $this->user('Admin Pusat', 'admin@pos.test', UserRole::Admin, $gudang->id);
        $manager = $this->user('Manager Panakkukang', 'manager@pos.test', UserRole::ManagerCabang, $cabangA->id);
        $kasir = $this->user('Kasir Panakkukang', 'kasir@pos.test', UserRole::Kasir, $cabangA->id);
        $this->user('Kasir Sudirman', 'kasir2@pos.test', UserRole::Kasir, $cabangB->id);

        // Kolom `code` pada tabel categories bersifat wajib (NOT NULL + unique).
        $kategori = collect([
            ['code' => 'KAT-MNM', 'name' => 'Minuman'],
            ['code' => 'KAT-MKN', 'name' => 'Makanan'],
            ['code' => 'KAT-RMH', 'name' => 'Kebutuhan Rumah'],
        ])->mapWithKeys(fn (array $row) => [$row['name'] => Category::create([
            'code' => $row['code'],
            'name' => $row['name'],
            'is_active' => true,
        ])]);

        // Kolom `code` pada tabel suppliers juga wajib (NOT NULL + unique).
        $pemasokA = Supplier::create([
            'code' => 'SUP-001', 'name' => 'PT Sumber Rejeki', 'phone' => '0811000111',
            'email' => 'sales@sumberrejeki.test', 'address' => 'Makassar (kontak: Bpk. Hasan)', 'is_active' => true,
        ]);

        $pemasokB = Supplier::create([
            'code' => 'SUP-002', 'name' => 'CV Mitra Pangan', 'phone' => '0811000222',
            'email' => 'order@mitrapangan.test', 'address' => 'Gowa (kontak: Ibu Sari)', 'is_active' => true,
        ]);

        $barang = collect([
            ['sku' => 'MNM-001', 'barcode' => '8991001100011', 'name' => 'Air Mineral 600ml', 'unit' => 'botol', 'sell_price' => 4000, 'min_stock' => 24, 'kategori' => 'Minuman'],
            ['sku' => 'MNM-002', 'barcode' => '8991001100028', 'name' => 'Teh Kotak 250ml', 'unit' => 'kotak', 'sell_price' => 5500, 'min_stock' => 24, 'kategori' => 'Minuman'],
            ['sku' => 'MKN-001', 'barcode' => '8991001100035', 'name' => 'Mi Instan Goreng', 'unit' => 'pcs', 'sell_price' => 3500, 'min_stock' => 40, 'kategori' => 'Makanan'],
            ['sku' => 'MKN-002', 'barcode' => '8991001100042', 'name' => 'Biskuit Kelapa 300g', 'unit' => 'pak', 'sell_price' => 14500, 'min_stock' => 12, 'kategori' => 'Makanan'],
            ['sku' => 'RMH-001', 'barcode' => '8991001100059', 'name' => 'Sabun Cuci Piring 800ml', 'unit' => 'pcs', 'sell_price' => 17500, 'min_stock' => 10, 'kategori' => 'Kebutuhan Rumah'],
            ['sku' => 'RMH-002', 'barcode' => '8991001100066', 'name' => 'Tisu Wajah 250 lembar', 'unit' => 'pak', 'sell_price' => 12500, 'min_stock' => 10, 'kategori' => 'Kebutuhan Rumah'],
        ])->mapWithKeys(fn (array $row) => [$row['sku'] => Product::create([
            'sku' => $row['sku'],
            'barcode' => $row['barcode'],
            'name' => $row['name'],
            'category_id' => $kategori[$row['kategori']]->id,
            'unit' => $row['unit'],
            'sell_price' => $row['sell_price'],
            'min_stock' => $row['min_stock'],
            'is_active' => true,
        ])]);

        $purchases = app(PurchaseService::class);
        $transfers = app(StockTransferService::class);
        $sales = app(SaleService::class);

        // Batch pertama (harga beli lama) -> akan terpakai lebih dahulu oleh FIFO.
        $batchSatu = $purchases->create([
            'supplier_id' => $pemasokA->id,
            'branch_id' => $gudang->id,
            'purchase_date' => now()->subDays(20)->toDateString(),
            'note' => 'Pembelian awal (batch harga lama)',
        ], [
            ['product_id' => $barang['MNM-001']->id, 'quantity' => 240, 'unit_cost' => 2400],
            ['product_id' => $barang['MNM-002']->id, 'quantity' => 120, 'unit_cost' => 3900],
            ['product_id' => $barang['MKN-001']->id, 'quantity' => 200, 'unit_cost' => 2500],
            ['product_id' => $barang['MKN-002']->id, 'quantity' => 60, 'unit_cost' => 10500],
            ['product_id' => $barang['RMH-001']->id, 'quantity' => 48, 'unit_cost' => 12500],
            ['product_id' => $barang['RMH-002']->id, 'quantity' => 48, 'unit_cost' => 8800],
        ], $admin->id);
        $purchases->post($batchSatu, $admin->id);

        // Batch kedua: harga beli naik, dipakai setelah batch pertama habis.
        $batchDua = $purchases->create([
            'supplier_id' => $pemasokB->id,
            'branch_id' => $gudang->id,
            'purchase_date' => now()->subDays(6)->toDateString(),
            'note' => 'Pembelian ulang (harga beli naik)',
        ], [
            ['product_id' => $barang['MNM-001']->id, 'quantity' => 240, 'unit_cost' => 2650],
            ['product_id' => $barang['MKN-001']->id, 'quantity' => 200, 'unit_cost' => 2750],
            ['product_id' => $barang['MKN-002']->id, 'quantity' => 60, 'unit_cost' => 11200],
        ], $admin->id);
        $purchases->post($batchDua, $admin->id);

        // Request stok cabang -> disetujui pusat -> mutasi stok dikirim & diterima.
        $request = StockRequest::create([
            'code' => 'RQ-'.now()->subDays(4)->format('ymd').'-0001',
            'branch_id' => $cabangA->id,
            'requested_by' => $manager->id,
            'status' => StockRequestStatus::Disetujui,
            'responded_by' => $admin->id,
            'responded_at' => now()->subDays(3),
            'note' => 'Kebutuhan stok awal cabang (disetujui penuh)',
        ]);

        foreach ([['MNM-001', 120], ['MNM-002', 60], ['MKN-001', 100], ['MKN-002', 30], ['RMH-001', 24], ['RMH-002', 24]] as [$sku, $qty]) {
            $request->items()->create([
                'product_id' => $barang[$sku]->id,
                'quantity_requested' => $qty,
                'quantity_approved' => $qty,
            ]);
        }

        $mutasi = $transfers->createFromRequest($request->load('items'), $gudang->id, 'Pengiriman perdana ke cabang');
        $transfers->ship($mutasi, $admin->id);
        $transfers->receive($mutasi->fresh('items'), $manager->id);

        // Mutasi tambahan ke cabang kedua.
        $mutasiB = $transfers->create($gudang->id, $cabangB->id, [
            ['product_id' => $barang['MNM-001']->id, 'quantity' => 96],
            ['product_id' => $barang['MKN-001']->id, 'quantity' => 80],
        ], 'Distribusi rutin');
        $transfers->ship($mutasiB, $admin->id);
        $transfers->receive($mutasiB->fresh('items'), $admin->id);

        // Shift kasir + transaksi penjualan simulasi.
        $shift = CashierShift::create([
            'branch_id' => $cabangA->id,
            'user_id' => $kasir->id,
            'opened_at' => now()->startOfDay()->addHours(8),
            'opening_cash' => 200000,
            'expected_cash' => 200000,
            'status' => 'dibuka',
        ]);

        $keranjang = [
            [['MNM-001', 4], ['MKN-001', 3]],
            [['MNM-002', 2], ['MKN-002', 1], ['RMH-001', 1]],
            [['MKN-001', 10], ['MNM-001', 6]],
            [['RMH-002', 2], ['MNM-002', 3]],
            [['MNM-001', 12], ['MKN-002', 2], ['MKN-001', 5]],
        ];

        foreach ($keranjang as $index => $isi) {
            $items = collect($isi)->map(fn (array $row) => [
                'product_id' => $barang[$row[0]]->id,
                'quantity' => $row[1],
            ])->all();

            $sales->checkout([
                'branch_id' => $cabangA->id,
                'user_id' => $kasir->id,
                'cashier_shift_id' => $shift->id,
                'customer_name' => $index % 2 === 0 ? null : 'Pelanggan '.($index + 1),
                'payment_method' => $index % 3 === 0 ? PaymentMethod::Tunai->value : PaymentMethod::Qris->value,
                'discount' => 0,
                'paid_amount' => 500000,
                'items' => $items,
            ]);
        }

        $this->command?->info('Data demo POS multi-cabang siap. Login: pemilik@pos.test / admin@pos.test / manager@pos.test / kasir@pos.test (kata sandi: password).');
    }

    private function user(string $name, string $email, UserRole $role, ?int $branchId): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'branch_id' => $branchId,
            'phone' => '08110001'.random_int(100, 999),
            'is_active' => true,
        ]);
    }
}
