# Hasil pengujian edisi full-folder hijau

## Otomatis
PHP 8.4, SQLite: 43 tests, 195 assertions, lulus. Meliputi hak akses, penjualan, stok/HPP Moving Average, penerimaan, shift, pembatalan, idempotensi, dan penonaktifan legacy. Output tersedia pada hasil-pest.txt.

## Browser Chromium lokal
Skenario lulus: login/error, kas awal, scan/pencarian, tunai, respons terputus setelah transaksi committed lalu retry tanpa duplikasi, QRIS manual, pembatalan oleh Pengelola, stok kembali, laporan, dan tutup shift. Tidak dibuat IndexedDB, localStorage, atau service worker transaksi. Tidak ada JavaScript page error pada skenario.
18 halaman Pengelola: HTTP 200 dan tanpa overflow dokumen pada 390px. Form penerimaan tambahan: 320, 390, 768, 1440px tanpa overflow dokumen. Tabel laporan lebar dapat digeser di dalam area tabel.

## Batas pengujian
Diuji lokal; bukan uji beban atau audit keamanan produksi. Printer fisik/scanner USB fisik, server hosting, MySQL, dan setiap kombinasi OS/versi PHP tidak diuji. Scan diuji sebagai input keyboard. Pembayaran non-tunai adalah pencatatan dan konfirmasi manual.

Pratinjau visual ada pada pratinjau/. Database demonstrasi pengujian tidak disertakan sebagai database aktif dalam ZIP.

## Uji hasil ekstraksi ZIP
Pada folder ekstraksi baru, tanpa composer/npm/key:generate: php artisan migrate berhasil, php artisan db:seed berhasil, php artisan test: 43 passed, 195 assertions. Konfigurasi SQLite demo bawaan dipakai langsung.
