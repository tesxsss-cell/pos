> Edisi aktif: satu toko, peran Pengelola/Kasir, FULL ONLINE, UI hijau–putih. Rujuk README.md dan docs/PERUBAHAN.md. Catatan lama di bawah dipertahankan sebagai riwayat dan mungkin tidak berlaku.

# Catatan Perubahan Sistem POS Koperasi Desa

## 20 Oktober 2026 — Penyesuaian koperasi desa/BUMDes

### Perubahan fungsional

1. Mengganti perhitungan persediaan lama dengan HPP rata-rata bergerak per barang/lokasi pada `stocks.avg_cost` (empat desimal). Penerimaan menghitung rata-rata tertimbang, pengeluaran mempertahankan rata-rata, dan total uang dibulatkan dua desimal secara half-up.
2. Menyimpan HPP unit asli pada baris penjualan dan mutasi; pembatalan memakai biaya penjualan asli. Kartu stok mencatat biaya unit, total HPP, serta rata-rata saldo. Nilai persediaan diturunkan dari saldo dikalikan rata-rata.
3. Menambahkan migrasi peningkatan yang mengonversi nilai persediaan tersisa, merekonstruksi biaya unit penjualan historis, mempertahankan biaya mutasi yang belum diterima, lalu menghapus dua tabel persediaan lama. Seluruh migrasi terdahulu tetap tidak diubah.
4. Menghapus modul permintaan stok manual, termasuk pengendali, model, enum, relasi, tampilan, navigasi, rute, serta data demonstrasinya. Migrasi terpisah menghapus tabel dan kunci asingnya. Tidak ada kebijakan khusus modul tersebut dalam proyek awal.
5. Menambahkan `ReorderService`, `ReorderAlert`, tabel peringatan, indeks unik satu peringatan aktif per barang/lokasi, dan `products.lead_time_days` (bawaan tiga hari).
6. Menambahkan evaluasi ADS/ROP sesudah transaksi dan penerimaan/mutasi, penyelesaian peringatan otomatis, perintah `pos:scan-reorder`, serta jadwal harian pukul 00.00. Nilai bawaan ADS 14 hari dan pengaman dua hari tersedia pada `config/pos.php`.
7. Menambahkan panel **Peringatan Restock Otomatis**, urutan kritis lebih dahulu, tombol dibaca, tautan pengadaan dan mutasi terisi, serta lencana jumlah kritis. Pengelola dibatasi pada tokonya. Kasir tidak memiliki tindakan pengadaan/mutasi.
8. Mengizinkan pengelola membuat/memposting dokumen pengadaan pada tokonya dan memulai kiriman dari gudang utama menuju tokonya. Pengurus pusat tetap dapat mengelola seluruh lokasi. Akses dokumen lokasi lain ditolak.
9. Memperbarui nama aplikasi di `.env`, contoh konfigurasi, tata letak, halaman luring, manifest, label peran, serta formulir waktu tunggu pemasok. Nilai enum peran tetap kompatibel.
10. Mempertahankan mesin barcode dan antrean PWA; sinkronisasi luring mengevaluasi ulang peringatan setelah tanggal transaksi luring diterapkan. Service worker menggunakan versi `kopdes-v11`; daftar pramuat tidak memuat rute permintaan stok.
11. Mengganti data demonstrasi dengan Gudang Utama Koperasi, dua toko unit, lima sembako, penerimaan berbeda harga, distribusi, sif kasir, penjualan, serta peringatan stok rendah.
12. Mengganti pengujian HPP lama, menambahkan uji peningkatan data, pengadaan otomatis, hak akses dan render Blade, serta memperbaiki ekspektasi pengujian awal Laravel agar sesuai pengalihan rute aplikasi. Enam pengujian barcode lama tetap lulus tanpa perubahan berkasnya.
13. Membangun ulang `public/build` dengan Vite/Tailwind. Memulihkan empat dependensi native Linux opsional pada `node_modules` dan izin eksekusi Vite dari arsip awal; versi serta isi `package-lock.json` tidak diubah.
14. Menulis ulang README dalam Bahasa Indonesia dan menyusun catatan peningkatan ini. Tidak membuat komit, mengubah remote, melakukan push, atau mengubah alur penerapan `.github`.

### Hasil verifikasi

- PHP CLI **8.4.26** beserta ekstensi SQLite, mbstring, XML, cURL, BCMath, Intl, dan ZIP berhasil dipasang.
- `php artisan test`: **31 lulus, 0 gagal, 158 asersi** (Pest, SQLite `:memory:`).
- `php artisan route:list`: berhasil, **72 rute**, termasuk rute bawaan Laravel; tidak ada rute permintaan stok manual.
- `php -l`: seluruh **51** berkas PHP yang ditambah/diubah lulus.
- `node --check` untuk service worker, sinkronisasi POS luring, dan dekoder barcode luring berhasil.
- Uji HTTP terautentikasi mencakup dasbor, formulir barang, pengadaan, mutasi, kartu stok, seluruh halaman laporan, POS, katalog, akun demonstrasi, dan batas hak akses.
- Uji peningkatan membuat skema lama pada SQLite terpisah, memasukkan stok dan mutasi perjalanan, kemudian menjalankan tiga migrasi baru serta memeriksa konversinya.
- `npm run build`: berhasil. Peringatan nonfatal berkaitan dengan kebutuhan Node.js 22 pada perkakas pengembangan `concurrently` dan paket opsional `fontaine` yang tidak tersedia; aset produksi tetap terbangun.
- `CACHE_STORE=array php artisan schedule:list`: jadwal harian terdaftar. `CACHE_STORE=array php artisan optimize:clear` berhasil tanpa mengubah `.env`.
- MySQL tidak dijalankan pada workbench; koneksi `.env` asli dipertahankan dan belum diuji terhadap server MySQL produksi. Verifikasi basis data menggunakan SQLite. Pengujian fisik pemindai USB/kamera dan perilaku PWA dalam peramban nyata tidak dilakukan.

### Petunjuk peningkatan

1. Cadangkan basis data, `.env`, dan aplikasi. Hentikan transaksi sementara selama konversi.
2. Jalankan **`php artisan migrate`**, bukan `migrate:fresh`. Jangan jalankan seeder demonstrasi pada produksi.
3. Jalankan `php artisan optimize:clear`, `npm run build`, lalu `php artisan pos:scan-reorder` dengan koneksi basis data dan cache server yang benar.
4. Aktifkan cron `php artisan schedule:run` setiap menit. Penjadwal belum diaktifkan pada mesin ini; yang ditambahkan adalah definisi jadwal aplikasi.
5. Periksa saldo/HPP, mutasi perjalanan, panel peringatan, serta sinkronisasi perangkat kasir setelah service worker diperbarui.

Migrasi konversi dan penghapusan permintaan manual tidak dapat dibalik otomatis; pulihkan cadangan untuk kembali ke versi sebelumnya. Tabel lama memang dihapus; referensi namanya hanya dipertahankan pada migrasi historis, kode peningkatan, dan pengujian peningkatan/penghapusan. Dokumen pengadaan yang digunakan adalah modul penerimaan yang tersedia, bukan sistem PO terpisah; tautan tidak memposting stok atau mengirim pesanan ke pemasok. Tidak ada notifikasi eksternal.

### Inventaris berkas

Daftar berikut relatif terhadap akar proyek. `.env`, `public/build`, `node_modules`, dan cache merupakan artefak lokal yang diabaikan Git; perubahan artefak tersebut tetap disertakan dalam paket sesuai ketentuan.

#### Ditambahkan

- `CHANGELOG-KOPDES.md`
- `app/Http/Controllers/ReorderAlertController.php`
- `app/Models/ReorderAlert.php`
- `app/Services/ReorderService.php`
- `config/pos.php`
- `database/migrations/2026_10_20_000100_use_moving_average_cost.php`
- `database/migrations/2026_10_20_000200_remove_manual_stock_requests.php`
- `database/migrations/2026_10_20_000300_create_reorder_alerts.php`
- `tests/Feature/AutoReorderTest.php`
- `tests/Feature/KopdesUpgradeTest.php`
- `tests/Feature/KopdesViewsTest.php`
- `tests/Feature/MovingAverageHppTest.php`

#### Dihapus

- `app/Enums/StockRequestStatus.php`
- `app/Http/Controllers/StockRequestController.php`
- `app/Models/InventoryLayer.php`
- `app/Models/InventoryLayerConsumption.php`
- `app/Models/StockRequest.php`
- `app/Models/StockRequestItem.php`
- `resources/views/stock-requests/form.blade.php`
- `resources/views/stock-requests/index.blade.php`
- `resources/views/stock-requests/show.blade.php`
- `tests/Feature/FifoHppTest.php`

#### Diubah (terpantau Git)

- `.env.example`
- `README.md`
- `app/Enums/UserRole.php`
- `app/Exceptions/InsufficientStockException.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/OfflineSyncController.php`
- `app/Http/Controllers/PosController.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/PurchaseController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/StockTransferController.php`
- `app/Models/Product.php`
- `app/Models/Purchase.php`
- `app/Models/SaleItem.php`
- `app/Models/Stock.php`
- `app/Models/StockMovement.php`
- `app/Models/StockTransfer.php`
- `app/Models/StockTransferItem.php`
- `app/Services/InventoryService.php`
- `app/Services/PurchaseService.php`
- `app/Services/ReportService.php`
- `app/Services/SaleService.php`
- `app/Services/StockTransferService.php`
- `config/app.php`
- `database/seeders/PosDemoSeeder.php`
- `public/manifest.webmanifest`
- `public/offline.html`
- `public/sw.js`
- `resources/views/dashboard.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/products/form.blade.php`
- `resources/views/purchases/form.blade.php`
- `resources/views/purchases/index.blade.php`
- `resources/views/purchases/show.blade.php`
- `resources/views/reports/daily.blade.php`
- `resources/views/reports/monthly.blade.php`
- `resources/views/reports/profit.blade.php`
- `resources/views/reports/stock-card.blade.php`
- `resources/views/reports/stock.blade.php`
- `resources/views/transfers/form.blade.php`
- `resources/views/transfers/index.blade.php`
- `resources/views/transfers/show.blade.php`
- `routes/console.php`
- `routes/web.php`
- `tests/Feature/ExampleTest.php`

#### Artefak lokal/terabaikan Git

- `.env`: hanya `APP_NAME` diperbarui; berkas dan nilai konfigurasi lainnya dipertahankan.
- `public/build/manifest.json`, `public/build/fonts-manifest.json`, serta seluruh aset di `public/build/assets/`: hasil pembangunan ulang.
- `node_modules/`: isi awal dipertahankan, dependensi native Linux opsional dilengkapi, dan peluncur Vite diberi izin eksekusi.
- `bootstrap/cache/` serta tampilan terkompilasi: dibersihkan; berkas PHP cache bootstrap tidak dimasukkan ke paket.
- `storage/logs/*.log`: tidak dimasukkan ke paket; berkas `.gitignore` tetap disertakan.

### Pengemasan

Paket lokal: `/home/user/out/pos13-kopdes.zip`, dengan folder tingkat atas tunggal `pos/`. Paket mencakup sumber aplikasi, `.env`, `vendor/`, `node_modules/`, `.git/`, dan `public/build/`; tidak mengunggah berkas ke layanan mana pun. Basis data sumber asli tidak dihapus, diisi ulang, atau dimigrasikan ke server produksi oleh pekerjaan ini.
