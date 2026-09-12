/**
 * sw.js - Service Worker aplikasi POS Multi-Cabang.
 *
 * Tugasnya:
 *  - Menyimpan kerangka aplikasi (app shell) agar halaman kasir tetap terbuka
 *    saat jaringan mati.
 *  - Menyajikan aset statis dari cache lebih dulu (stale-while-revalidate).
 *  - Menyimpan dekoder barcode offline (EAN/UPC) supaya scan tetap jalan offline.
 *  - Tidak pernah menyimpan permintaan POST/PUT/DELETE, sehingga transaksi
 *    selalu ditangani antrian IndexedDB di pos-offline.js.
 *
 * Catatan versi:
 *  pos-v10 = scanner kasir tertanam tanpa pop-up; kamera dapat diaktifkan/nonaktifkan.
 *  pos-v9 = daftar produk responsif; popup scanner dan tombol barcode tampil di HP.
 *  pos-v8 = scanner halaman daftar/ubah produk juga sudah inline di Blade.
 *  pos-v7 = scanner kasir sudah inline di Blade; cache halaman membawa mesin scan offline.
 *  pos-v6 = pemindai kamera selalu minta izin kamera + fallback kamera + pop up scan responsif mobile.
 *  pos-v5 = pemindai kamera TANPA html5-qrcode (dekoder offline EAN/UPC + BarcodeDetector).
 *  Nomor versi WAJIB dinaikkan setiap kali berkas js/ di atas berubah, supaya
 *  kasir tidak memakai berkas lama yang masih tersimpan di cache peramban.
 */
const VERSI = 'pos-v10';
const CACHE_SHELL = `${VERSI}-shell`;
const CACHE_ASET = `${VERSI}-aset`;

const BERKAS_INTI = [
    '/offline.html',
    '/js/offline-barcode.js',
    '/js/pos-offline.js',
    '/js/barcode-scanner.js',
    '/manifest.webmanifest',
];

// Berkas tambahan yang boleh gagal saat dipasang ke cache.
const BERKAS_OPSIONAL = [];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_SHELL)
            .then((cache) =>
                cache.addAll(BERKAS_INTI).then(() =>
                    Promise.all(
                        BERKAS_OPSIONAL.map((berkas) =>
                            cache.add(berkas).catch(() => null)
                        )
                    )
                )
            )
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((kunci) =>
                Promise.all(
                    kunci
                        .filter((nama) => ! nama.startsWith(VERSI))
                        .map((nama) => caches.delete(nama))
                )
            )
            .then(() => self.clients.claim())
    );
});

/** Aset statis: pakai cache dulu, perbarui di belakang layar. */
function layaniAset(request) {
    return caches.open(CACHE_ASET).then((cache) =>
        cache.match(request).then((tersimpan) => {
            const jaringan = fetch(request)
                .then((respons) => {
                    if (respons && respons.ok) {
                        cache.put(request, respons.clone());
                    }

                    return respons;
                })
                .catch(() => tersimpan);

            return tersimpan || jaringan;
        })
    );
}

/** Halaman: utamakan jaringan, jatuh ke cache lalu ke halaman offline. */
function layaniHalaman(request) {
    return fetch(request)
        .then((respons) => {
            const salinan = respons.clone();
            caches.open(CACHE_SHELL).then((cache) => cache.put(request, salinan));

            return respons;
        })
        .catch(() =>
            caches
                .match(request)
                .then((tersimpan) => tersimpan || caches.match('/offline.html'))
        );
}

/** Endpoint data kasir yang jawabannya harus selalu segar (tidak boleh dari cache). */
const JALUR_LANGSUNG = [
    '/kasir/katalog-offline',
    '/kasir/cari-produk',
    '/kasir/barcode',           // cek barcode & rekomendasi nama barang
];

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Hanya tangani permintaan GET dari domain yang sama.
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Endpoint sinkronisasi, pencarian produk, & pemindaian barcode selalu ke jaringan.
    if (JALUR_LANGSUNG.some((jalur) => url.pathname.includes(jalur))) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(layaniHalaman(request));

        return;
    }

    if (/\.(?:js|css|png|jpg|jpeg|svg|webp|ico|woff2?)$/.test(url.pathname) || url.pathname.startsWith('/build/')) {
        event.respondWith(layaniAset(request));
    }
});

/** Halaman kasir dapat meminta service worker membangunkan proses sinkronisasi. */
self.addEventListener('message', (event) => {
    if (event.data === 'sinkronkan-sekarang') {
        self.clients.matchAll().then((klien) => {
            klien.forEach((satu) => satu.postMessage('jalankan-sinkronisasi'));
        });
    }
});
