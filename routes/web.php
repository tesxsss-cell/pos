<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('role:kasir')->prefix('kasir')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('daftarkan-barcode', [PosController::class, 'products'])->name('products');
        Route::get('riwayat', [PosController::class, 'history'])->name('history');
        Route::get('cari-produk', [PosController::class, 'lookup'])->name('lookup');
        Route::post('barcode', [PosController::class, 'storeBarcode'])->middleware('throttle:30,1')->name('barcode.store');
        Route::post('checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::post('shift/buka', [PosController::class, 'openShift'])->name('shift.open');
        Route::post('shift/tutup', [PosController::class, 'closeShift'])->name('shift.close');
    });
    Route::get('penjualan/{sale}/struk', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::middleware('role:pengelola')->group(function () {
        Route::get('penjualan', [PosController::class, 'sales'])->name('sales.index');
        Route::resource('barang', ProductController::class)->parameters(['barang' => 'product'])->names('products')->except('show');
        Route::resource('kategori', CategoryController::class)->parameters(['kategori' => 'category'])->names('categories')->except('show');
        Route::resource('pemasok', SupplierController::class)->parameters(['pemasok' => 'supplier'])->names('suppliers')->except('show');
        Route::resource('pengguna', UserController::class)->parameters(['pengguna' => 'user'])->names('users')->except('show');
        Route::resource('penerimaan', PurchaseController::class)->parameters(['penerimaan' => 'purchase'])->names('purchases')->only(['index', 'create', 'store', 'show']);
        Route::post('penerimaan/{purchase}/posting', [PurchaseController::class, 'post'])->name('purchases.post');
        Route::prefix('laporan')->name('reports.')->group(function () {
            Route::get('harian', [ReportController::class, 'daily'])->name('daily');
            Route::get('bulanan', [ReportController::class, 'monthly'])->name('monthly');
            Route::get('laba-kotor', [ReportController::class, 'profit'])->name('profit');
            Route::get('stok', [ReportController::class, 'stock'])->name('stock');
            Route::get('kartu-stok', [ReportController::class, 'stockCard'])->name('stock-card');
            Route::get('shift-kasir', [ReportController::class, 'shift'])->name('shift');
        });
    });
});
