/**
 * sw.js - Service Worker aplikasi POS Multi-Cabang.
 *
 * Tugasnya:
 *  - Menyimpan kerangka aplikasi (app shell) agar halaman kasir tetap terbuka
 *    saat jaringan mati.
 *  - Menyajikan aset statis dari cache lebih dulu (stale-while-revalidate).
 *  - Tidak pernah menyimpan permintaan POST/PUT/DELETE, sehingga transaksi
 *    selalu ditangani antrian IndexedDB di pos-offline.js.
 */
const VERSI = 'pos-v1';
const CACHE_SHELL = `${VERSI}-shell`;
const CACHE_ASET = `${VERSI}-aset`;

const BERKAS_INTI = [
    '/offline.html',
    '/js/pos-offline.js',
    '/js/barcode-scanner.js',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_SHELL)
            .then((cache) => cache.addAll(BERKAS_INTI))
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

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Hanya tangani permintaan GET dari domain yang sama.
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Endpoint sinkronisasi & pencarian produk harus selalu ke jaringan.
    if (url.pathname.includes('/kasir/katalog-offline') || url.pathname.includes('/kasir/cari-produk')) {
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
