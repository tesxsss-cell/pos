/**
 * pos-offline.js
 * Mode offline halaman kasir (HF-04).
 *
 * Alur kerja:
 *  1. Saat online, katalog barang + stok diunduh dan disimpan di IndexedDB.
 *  2. Saat jaringan mati, pencarian barang dilayani dari IndexedDB dan
 *     transaksi disimpan ke antrian lokal (store "antrian").
 *  3. Begitu jaringan kembali, antrian dikirim ke server. UUID dari peramban
 *     dipakai server sebagai kunci idempoten agar tidak terjadi transaksi dobel.
 *
 * Konfigurasi disuntikkan dari Blade lewat window.POS_OFFLINE_CONFIG.
 */
(function () {
    'use strict';

    var konfig = window.POS_OFFLINE_CONFIG || {};
    var NAMA_DB = 'pos-offline';
    var VERSI_DB = 1;
    var db = null;

    /** Membuka (dan bila perlu membuat) basis data IndexedDB. */
    function bukaDb() {
        if (db) {
            return Promise.resolve(db);
        }

        return new Promise(function (selesai, gagal) {
            if (! ('indexedDB' in window)) {
                gagal(new Error('Peramban ini tidak mendukung IndexedDB.'));

                return;
            }

            var permintaan = indexedDB.open(NAMA_DB, VERSI_DB);

            permintaan.onupgradeneeded = function (event) {
                var basis = event.target.result;

                if (! basis.objectStoreNames.contains('produk')) {
                    var produk = basis.createObjectStore('produk', { keyPath: 'id' });
                    produk.createIndex('barcode', 'barcode', { unique: false });
                    produk.createIndex('sku', 'sku', { unique: false });
                }

                if (! basis.objectStoreNames.contains('antrian')) {
                    basis.createObjectStore('antrian', { keyPath: 'client_uuid' });
                }

                if (! basis.objectStoreNames.contains('meta')) {
                    basis.createObjectStore('meta', { keyPath: 'kunci' });
                }
            };

            permintaan.onsuccess = function (event) {
                db = event.target.result;
                selesai(db);
            };

            permintaan.onerror = function () {
                gagal(permintaan.error);
            };
        });
    }

    function transaksi(namaStore, mode) {
        return bukaDb().then(function (basis) {
            return basis.transaction(namaStore, mode).objectStore(namaStore);
        });
    }

    function jadikanJanji(permintaan) {
        return new Promise(function (selesai, gagal) {
            permintaan.onsuccess = function () {
                selesai(permintaan.result);
            };
            permintaan.onerror = function () {
                gagal(permintaan.error);
            };
        });
    }

    /** UUID v4 sederhana, dipakai sebagai kunci idempoten transaksi offline. */
    function buatUuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var acak = (Math.random() * 16) | 0;
            var nilai = c === 'x' ? acak : ((acak & 0x3) | 0x8);

            return nilai.toString(16);
        });
    }

    function beriTahu(nama, rincian) {
        window.dispatchEvent(new CustomEvent(nama, { detail: rincian || {} }));
    }

    /* ------------------------------------------------------------------ */
    /* Katalog produk                                                      */
    /* ------------------------------------------------------------------ */

    /** Menyimpan seluruh katalog ke IndexedDB (menimpa data lama). */
    function simpanKatalog(daftar) {
        return bukaDb().then(function (basis) {
            return new Promise(function (selesai, gagal) {
                var trx = basis.transaction(['produk', 'meta'], 'readwrite');
                var produk = trx.objectStore('produk');

                produk.clear();
                daftar.forEach(function (item) {
                    produk.put(item);
                });

                trx.objectStore('meta').put({
                    kunci: 'katalog_terakhir',
                    nilai: new Date().toISOString(),
                    jumlah: daftar.length,
                });

                trx.oncomplete = function () {
                    selesai(daftar.length);
                };
                trx.onerror = function () {
                    gagal(trx.error);
                };
            });
        });
    }

    /** Mengunduh katalog dari server. Dipanggil otomatis saat halaman dibuka. */
    function segarkanKatalog() {
        if (! konfig.katalog || ! navigator.onLine) {
            return Promise.resolve(0);
        }

        return fetch(konfig.katalog, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (respons) {
                if (! respons.ok) {
                    throw new Error('Gagal mengunduh katalog.');
                }

                return respons.json();
            })
            .then(function (isi) {
                return simpanKatalog(isi.data || []);
            })
            .then(function (jumlah) {
                beriTahu('pos-offline:katalog', { jumlah: jumlah });

                return jumlah;
            })
            .catch(function (error) {
                console.warn('[pos-offline] katalog gagal disegarkan:', error.message);

                return 0;
            });
    }

    /** Mencari barang di katalog lokal berdasarkan barcode, SKU, atau nama. */
    function cariKatalogLokal(kataKunci) {
        var kunci = String(kataKunci || '').trim().toLowerCase();

        if (kunci === '') {
            return Promise.resolve([]);
        }

        return transaksi('produk', 'readonly')
            .then(function (store) {
                return jadikanJanji(store.getAll());
            })
            .then(function (semua) {
                return semua
                    .filter(function (item) {
                        return (item.barcode || '').toLowerCase() === kunci
                            || (item.sku || '').toLowerCase().indexOf(kunci) !== -1
                            || (item.name || '').toLowerCase().indexOf(kunci) !== -1;
                    })
                    .slice(0, 10);
            });
    }

    function infoKatalog() {
        return transaksi('meta', 'readonly').then(function (store) {
            return jadikanJanji(store.get('katalog_terakhir'));
        });
    }

    /* ------------------------------------------------------------------ */
    /* Antrian transaksi                                                   */
    /* ------------------------------------------------------------------ */

    /** Menyimpan satu transaksi ke antrian lokal. */
    function antrikanTransaksi(muatan) {
        var catatan = Object.assign({}, muatan, {
            client_uuid: muatan.client_uuid || buatUuid(),
            sold_at: muatan.sold_at || new Date().toISOString(),
            dibuat_pada: new Date().toISOString(),
            percobaan: 0,
        });

        return transaksi('antrian', 'readwrite')
            .then(function (store) {
                return jadikanJanji(store.put(catatan));
            })
            .then(function () {
                return hitungAntrian();
            })
            .then(function (jumlah) {
                beriTahu('pos-offline:antrian', { jumlah: jumlah, terakhir: catatan });

                return catatan;
            });
    }

    function daftarAntrian() {
        return transaksi('antrian', 'readonly').then(function (store) {
            return jadikanJanji(store.getAll());
        });
    }

    function hitungAntrian() {
        return transaksi('antrian', 'readonly').then(function (store) {
            return jadikanJanji(store.count());
        });
    }

    function hapusAntrian(uuid) {
        return transaksi('antrian', 'readwrite').then(function (store) {
            return jadikanJanji(store.delete(uuid));
        });
    }

    function tandaiGagal(uuid, pesan) {
        return transaksi('antrian', 'readwrite').then(function (store) {
            return jadikanJanji(store.get(uuid)).then(function (catatan) {
                if (! catatan) {
                    return null;
                }

                catatan.percobaan = (catatan.percobaan || 0) + 1;
                catatan.pesan_gagal = pesan;

                return jadikanJanji(store.put(catatan));
            });
        });
    }

    var sedangMengirim = false;

    /** Mengirim seluruh antrian ke server lalu membersihkan yang berhasil. */
    function kirimAntrian() {
        if (sedangMengirim || ! navigator.onLine || ! konfig.sinkronisasi) {
            return Promise.resolve({ dikirim: 0 });
        }

        sedangMengirim = true;

        return daftarAntrian()
            .then(function (antrian) {
                if (antrian.length === 0) {
                    return { dikirim: 0 };
                }

                beriTahu('pos-offline:sinkron-mulai', { jumlah: antrian.length });

                var muatan = antrian.slice(0, 50).map(function (item) {
                    return {
                        client_uuid: item.client_uuid,
                        sold_at: item.sold_at,
                        customer_name: item.customer_name,
                        payment_method: item.payment_method,
                        discount: item.discount,
                        paid_amount: item.paid_amount,
                        note: item.note,
                        items: item.items,
                    };
                });

                return fetch(konfig.sinkronisasi, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': konfig.csrf || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ sales: muatan }),
                })
                    .then(function (respons) {
                        return respons.json().then(function (isi) {
                            return { ok: respons.ok, isi: isi };
                        });
                    })
                    .then(function (hasil) {
                        if (! hasil.ok) {
                            throw new Error(hasil.isi.message || 'Sinkronisasi ditolak server.');
                        }

                        var tersimpan = 0;
                        var gagal = 0;
                        var tugas = (hasil.isi.results || []).map(function (baris) {
                            if (baris.status === 'tersimpan' || baris.status === 'duplikat') {
                                tersimpan += 1;

                                return hapusAntrian(baris.client_uuid);
                            }

                            gagal += 1;

                            return tandaiGagal(baris.client_uuid, baris.message || 'Gagal diproses.');
                        });

                        return Promise.all(tugas).then(function () {
                            return { dikirim: tersimpan, gagal: gagal };
                        });
                    });
            })
            .then(function (ringkasan) {
                sedangMengirim = false;

                return hitungAntrian().then(function (sisa) {
                    if (ringkasan.dikirim || ringkasan.gagal) {
                        beriTahu('pos-offline:sinkron-selesai', {
                            dikirim: ringkasan.dikirim,
                            gagal: ringkasan.gagal || 0,
                            sisa: sisa,
                        });
                    }

                    beriTahu('pos-offline:antrian', { jumlah: sisa });

                    return ringkasan;
                });
            })
            .catch(function (error) {
                sedangMengirim = false;
                console.warn('[pos-offline] sinkronisasi gagal:', error.message);
                beriTahu('pos-offline:sinkron-gagal', { pesan: error.message });

                return { dikirim: 0, pesan: error.message };
            });
    }

    /* ------------------------------------------------------------------ */
    /* Pemantauan status jaringan                                          */
    /* ------------------------------------------------------------------ */

    function laporkanStatus() {
        hitungAntrian().then(function (jumlah) {
            beriTahu('pos-offline:status', { online: navigator.onLine, antrian: jumlah });
        });
    }

    window.addEventListener('online', function () {
        laporkanStatus();
        segarkanKatalog();
        kirimAntrian();
    });

    window.addEventListener('offline', laporkanStatus);

    document.addEventListener('DOMContentLoaded', function () {
        laporkanStatus();
        segarkanKatalog();
        kirimAntrian();

        // Coba kirim ulang tiap 60 detik selama tab terbuka.
        setInterval(function () {
            if (navigator.onLine) {
                kirimAntrian();
            }
        }, 60000);
    });

    window.PosOffline = {
        buatUuid: buatUuid,
        simpanKatalog: simpanKatalog,
        segarkanKatalog: segarkanKatalog,
        cariKatalogLokal: cariKatalogLokal,
        infoKatalog: infoKatalog,
        antrikanTransaksi: antrikanTransaksi,
        daftarAntrian: daftarAntrian,
        hitungAntrian: hitungAntrian,
        hapusAntrian: hapusAntrian,
        kirimAntrian: kirimAntrian,
        laporkanStatus: laporkanStatus,
    };
})();
