# Kopdes POS — Full Folder, Green & Online

Edisi demo tugas kuliah untuk satu toko koperasi desa. Dua peran: Pengelola dan Kasir.

## Mulai cepat
Ekstrak ZIP ke folder baru, masuk ke folder `pos`, lalu jalankan:

```sh
php artisan migrate
php artisan db:seed
php artisan serve
```

Buka http://127.0.0.1:8000. `vendor`, `node_modules`, aset hasil build, `.env` demo dengan APP_KEY, dan berkas SQLite kosong sudah disertakan. Tidak perlu composer install, npm install, npm run build, atau key:generate untuk mencoba paket ini.

Prasyarat: PHP 8.3+ beserta ekstensi Laravel dan PDO SQLite; diuji memakai PHP 8.4. Sistem PHP/ekstensi tetap harus tersedia di komputer Anda. SQLite dipakai agar tidak perlu membuat database MySQL. Jika memilih MySQL, sesuaikan koneksi `.env` sendiri; MySQL tidak diuji dalam lingkungan pengujian paket ini.

## Akun demo
| Peran | Email | Kata sandi |
|---|---|---|
| Pengelola | pengelola@pos.test | DemoKopdes2026! |
| Kasir 1 | kasir1@pos.test | DemoKopdes2026! |
| Kasir 2 | kasir2@pos.test | DemoKopdes2026! |

Seeder membuat data awal empat barang, kategori, pemasok, dan penerimaan awal. Seeder tidak mengosongkan database lama. Shift belum dibuka; Kasir memasukkan kas awal sendiri. Untuk demonstrasi dua kasir, masing-masing diasumsikan menggunakan laci kas terpisah.

## Fitur aktif
- Login tanpa registrasi publik, pembatasan akses Pengelola/Kasir, akun aktif.
- Barang, kategori, pemasok, dan akun kasir; barcode USB sebagai keyboard dan pencarian nama/SKU.
- Penerimaan barang draft lalu posting; menambah stok dan menghitung HPP Moving Average.
- Penjualan online, stok tidak boleh negatif, harga ditetapkan server/Pengelola.
- Pembayaran tunai dan non-tunai dengan verifikasi manual; tidak ada integrasi payment gateway.
- Struk cetak browser; buka/tutup shift, kas awal, kas sistem, kas fisik, selisih.
- Pembatalan seluruh struk oleh Pengelola dengan alasan dan konfirmasi pengembalian, hanya selama shift asal masih terbuka; stok kembali, riwayat tidak dihapus.
- Laporan harian/bulanan, HPP, laba kotor, persediaan, kartu stok, dan rekap shift.
- Peringatan stok minimum sederhana; bukan prediksi atau pemesanan otomatis.
- UI hijau–putih, menu responsif, navigasi bawah HP, formulir dan keranjang ramah layar kecil.

Laba kotor bukan laba bersih atau SHU koperasi. Ini POS toko koperasi, bukan seluruh sistem administrasi koperasi (simpanan/pinjaman/anggota/SHU).

## Online sepenuhnya
Tidak ada antrean transaksi offline, IndexedDB transaksi, atau sinkronisasi kasir offline aktif. Koneksi ke server diperlukan. Jika respons transaksi terputus setelah server menerima, gunakan periksa/kirim ulang pada halaman yang sama; UUID idempoten mencegah transaksi ganda. Jangan membuat transaksi baru atau menutup halaman sampai status jelas. Service worker lama dilepas; database/antrean offline lama tidak dihapus otomatis dan harus direkonsiliasi terpisah bila sebelumnya digunakan.

## Folder asli tetap ada
Seluruh 2.303 jalur direktori dari ZIP pertama dipertahankan, termasuk `vendor`, `node_modules`, `.git`, `.github`, `.claude`, dan folder modul lama. Daftar ada pada `docs/STRUKTUR_FOLDER_ASLI.txt`. Ukuran ZIP berasal dari dependensi asli, bukan file pengisi.

Folder dipertahankan tidak berarti fitur lama diaktifkan kembali: rute cabang, transfer, reorder otomatis, dan offline dinonaktifkan; file legacy tertentu berupa penanda nonaktif. Workflow deployment lama dibuat manual dan tidak melakukan deployment. `.mcp.json` dinetralkan. Konfigurasi rahasia asli, database pengguna, log, sesi, dan cache runtime lama tidak dibawa sebagai data aktif; struktur foldernya tetap tersedia. `.git` adalah riwayat asli, bukan commit baru untuk edisi ini.

## Pengujian dan dokumentasi
43 pengujian otomatis / 195 assertions lulus. Uji browser mencakup login, transaksi tunai, QRIS manual, retry setelah respons terputus, pembatalan Pengelola, dan tutup shift. 18 halaman Pengelola berhasil dimuat tanpa overflow halaman pada 390px. Form penerimaan juga diperiksa pada 320, 390, 768, dan 1440px. Rincian: `docs/HASIL_PENGUJIAN.md`.

## Pengembangan opsional
Aset sudah dibangun. Bila ingin mengubah CSS, jalankan `npm run build`. Skrip memanggil Vite langsung untuk menghindari masalah shortcut `.bin` setelah ekstraksi ZIP. Native dependency Node bisa berbeda antar OS; jika pengembangan ulang bermasalah, instal ulang dependensi pada komputer tujuan. Ini tidak diperlukan untuk menjalankan aset bawaan.

## Keamanan dan data lama
Gunakan folder/database baru untuk demo. Jangan menimpa database produksi atau menjalankan reset database. Cadangkan dahulu bila ingin menyesuaikan proyek lama. Migrasi penyederhanaan menolak database dengan lebih dari satu cabang.

APP_KEY dan akun bawaan hanya untuk demo paket ini. Untuk produksi: gunakan APP_KEY baru sebelum menyimpan data sensitif, ganti kata sandi, set APP_DEBUG=false, HTTPS, konfigurasi server yang benar, dan document root ke `public`. Jangan unggah ZIP, `.env`, `.git`, `vendor`, atau `node_modules` sebagai berkas publik. Paket ini belum merupakan audit keamanan produksi atau sertifikasi kepatuhan koperasi.
