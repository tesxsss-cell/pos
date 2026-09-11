<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockRequestController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// ---------- HF-01 Autentikasi ----------
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---------- HF-04 Transaksi Penjualan (POS) ----------
    Route::middleware('role:kasir,admin,manager_cabang')->prefix('kasir')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('produk', [PosController::class, 'products'])->name('products');         // daftar produk cabang + daftar/ganti barcode
        Route::get('cari-produk', [PosController::class, 'lookup'])->name('lookup');       // scan barcode/QR & pencarian
        Route::post('checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::get('struk/{sale}', [PosController::class, 'receipt'])->name('receipt');
        Route::post('{sale}/batal', [PosController::class, 'void'])->name('void');
        Route::post('shift/buka', [PosController::class, 'openShift'])->name('shift.open');
        Route::post('shift/tutup', [PosController::class, 'closeShift'])->name('shift.close');

        // HF-04 Scan & pendaftaran barcode barang langsung dari halaman kasir
        Route::get('barcode', [PosController::class, 'resolveBarcode'])->name('barcode.resolve');
        Route::get('barcode/rekomendasi', [PosController::class, 'suggestProducts'])->name('barcode.suggest');
        Route::post('barcode', [PosController::class, 'storeBarcode'])->name('barcode.store');
        Route::delete('barcode/{productBarcode}', [PosController::class, 'destroyBarcode'])->name('barcode.destroy');

        // HF-04 Mode offline: unduh katalog & kirim antrian transaksi
        Route::get('katalog-offline', [OfflineSyncController::class, 'catalog'])->name('catalog');
        Route::post('sinkronisasi', [OfflineSyncController::class, 'store'])->name('sync');
        Route::post('sinkronisasi-barcode', [OfflineSyncController::class, 'storeBarcodes'])->name('sync-barcode');
    });

    // ---------- HF-02 Pengelolaan Data Induk ----------
    Route::middleware('role:admin,pemilik')->group(function () {
        Route::resource('barang', ProductController::class)
            ->parameters(['barang' => 'product'])->names('products')->except('show');
        Route::resource('kategori', CategoryController::class)
            ->parameters(['kategori' => 'category'])->names('categories')->except('show');
        Route::resource('pemasok', SupplierController::class)
            ->parameters(['pemasok' => 'supplier'])->names('suppliers')->except('show');
        Route::resource('cabang', BranchController::class)
            ->parameters(['cabang' => 'branch'])->names('branches')->except('show');
        Route::resource('pengguna', UserController::class)
            ->parameters(['pengguna' => 'user'])->names('users')->except('show');

        // HF-03 Penerimaan barang dari pemasok (pembentuk lapisan FIFO)
        Route::resource('penerimaan', PurchaseController::class)
            ->parameters(['penerimaan' => 'purchase'])->names('purchases')->only(['index', 'create', 'store', 'show']);
        Route::post('penerimaan/{purchase}/posting', [PurchaseController::class, 'post'])->name('purchases.post');
    });

    // ---------- HF-03 Request stok cabang ----------
    Route::middleware('role:manager_cabang,admin,pemilik')->prefix('permintaan-stok')->name('stock-requests.')->group(function () {
        Route::get('/', [StockRequestController::class, 'index'])->name('index');
        Route::get('buat', [StockRequestController::class, 'create'])->name('create');
        Route::post('/', [StockRequestController::class, 'store'])->name('store');
        Route::get('{stockRequest}', [StockRequestController::class, 'show'])->name('show');
        Route::post('{stockRequest}/setujui', [StockRequestController::class, 'approve'])->name('approve');
        Route::post('{stockRequest}/tolak', [StockRequestController::class, 'reject'])->name('reject');
    });

    // ---------- HF-03 Modul Mutasi Stok (gudang -> cabang) ----------
    Route::middleware('role:admin,pemilik,manager_cabang')->prefix('mutasi-stok')->name('transfers.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('buat', [StockTransferController::class, 'create'])->name('create');
        Route::post('/', [StockTransferController::class, 'store'])->name('store');
        Route::get('{transfer}', [StockTransferController::class, 'show'])->name('show');
        Route::post('{transfer}/kirim', [StockTransferController::class, 'ship'])->name('ship');
        Route::post('{transfer}/terima', [StockTransferController::class, 'receive'])->name('receive');
    });

    // ---------- HF-06 Dasbor & Pelaporan ----------
    Route::middleware('role:pemilik,admin,manager_cabang')->prefix('laporan')->name('reports.')->group(function () {
        Route::get('harian', [ReportController::class, 'daily'])->name('daily');
        Route::get('bulanan', [ReportController::class, 'monthly'])->name('monthly');
        Route::get('laba-rugi', [ReportController::class, 'profit'])->name('profit');
        Route::get('stok', [ReportController::class, 'stock'])->name('stock');
        Route::get('kartu-stok', [ReportController::class, 'stockCard'])->name('stock-card');
        Route::get('shift-kasir', [ReportController::class, 'shift'])->name('shift');
    });
});
