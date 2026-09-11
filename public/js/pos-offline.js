/**
 * pos-offline.js
 * Mode offline halaman kasir (HF-04).
 *
 * Alur kerja:
 *  1. Saat online, katalog barang + stok + daftar barcode diunduh dan disimpan di IndexedDB.
 *  2. Saat jaringan mati, pencarian/pemindaian barang dilayani dari IndexedDB,
 *     transaksi masuk antrian lokal (store "antrian"), dan pendaftaran barcode
 *     baru masuk antrian "antrian_barcode".
 *  3. Begitu jaringan kembali, kedua antrian dikirim ke server. UUID dari peramban
 *     dipakai server sebagai kunci idempoten agar tidak terjadi data dobel.
 *
 * Konfigurasi disuntikkan dari Blade lewat window.POS_OFFLINE_CONFIG.
 */
(function () {
    'use strict';

    var konfig = window.POS_OFFLINE_CONFIG || {};
    var NAMA_DB = 'pos-offline';
    var VERSI_DB = 2; // v2: menambah store "antrian_barcode"
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

                // Antrian pendaftaran barcode yang dibuat kasir saat jaringan mati.
                if (! basis.objectStoreNames.contains('antrian_barcode')) {
                    basis.createObjectStore('antrian_barcode', { keyPath: 'client_uuid' });
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

    /** Membersihkan kode hasil pemindaian (alat pemindai sering menambah spasi/enter). */
    function bersihkanKode(kode) {
        return String(kode || '').replace(/\s+/g, '');
    }

    /** Semua kode barcode milik satu barang (barcode utama + hasil pendaftaran kasir). */
    function kodeBarcode(item) {
        var daftar = (item.barcodes || []).map(function (satu) {
            return bersihkanKode(satu.code).toLowerCase();
        });

        if (item.barcode) {
            daftar.push(bersihkanKode(item.barcode).toLowerCase());
        }

        return daftar;
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

    /** Mencari barang di katalog lokal berdasarkan barcode (semua alias), SKU, atau nama. */
    function cariKatalogLokal(kataKunci) {
        var kunci = String(kataKunci || '').trim().toLowerCase();
        var kode = bersihkanKode(kataKunci).toLowerCase();

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
                        return kodeBarcode(item).indexOf(kode) !== -1
                            || (item.sku || '').toLowerCase().indexOf(kunci) !== -1
                            || (item.name || '').toLowerCase().indexOf(kunci) !== -1;
                    })
                    .slice(0, 10);
            });
    }

    /**
     * Mencari satu barang berdasarkan barcode tepat (dipakai saat memindai offline).
     * Mengembalikan { produk, alias } atau null. alias berisi harga & jumlah tersimpan.
     */
    function cariBarcodeLokal(kode) {
        var kunci = bersihkanKode(kode).toLowerCase();

        if (kunci === '') {
            return Promise.resolve(null);
        }

        return transaksi('produk', 'readonly')
            .then(function (store) {
                return jadikanJanji(store.getAll());
            })
            .then(function (semua) {
                for (var i = 0; i < semua.length; i += 1) {
                    var item = semua[i];
                    var daftar = item.barcodes || [];

                    for (var j = 0; j < daftar.length; j += 1) {
                        if (bersihkanKode(daftar[j].code).toLowerCase() === kunci) {
                            return { produk: item, alias: daftar[j] };
                        }
                    }

                    if (bersihkanKode(item.barcode).toLowerCase() === kunci) {
                        return { produk: item, alias: null };
                    }
                }

                return null;
            });
    }

    /**
     * Menambah barcode baru ke katalog lokal supaya barcode yang baru didaftarkan
     * tetap bisa dipindai walau jaringan masih mati.
     */
    function tambahAliasLokal(idProduk, alias) {
        return bukaDb().then(function (basis) {
            return new Promise(function (selesai, gagal) {
                var trx = basis.transaction('produk', 'readwrite');
                var store = trx.objectStore('produk');
                var permintaan = store.get(idProduk);

                permintaan.onsuccess = function () {
                    var item = permintaan.result;

                    if (! item) {
                        return;
                    }

                    item.barcodes = (item.barcodes || []).filter(function (satu) {
                        return bersihkanKode(satu.code) !== bersihkanKode(alias.code);
                    });
                    item.barcodes.push(alias);

                    if (! item.barcode) {
                        item.barcode = alias.code;
                    }

                    store.put(item);
                };

                trx.oncomplete = function () {
                    selesai(true);
                };
                trx.onerror = function () {
                    gagal(trx.error);
                };
            });
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
    /* Antrian pendaftaran barcode (HF-04)                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Menyimpan pendaftaran barcode ke antrian lokal.
     * Katalog lokal langsung ikut diperbarui agar barcode bisa dipindai saat itu juga.
     *
     * @param {{barcode:string, product_id:number, name?:string, sell_price?:number, quantity?:number, base_price?:number}} muatan
     */
    function antrikanBarcode(muatan) {
        var kode = bersihkanKode(muatan.barcode);

        if (! kode) {
            return Promise.reject(new Error('Barcode belum terbaca.'));
        }

        if (! muatan.product_id) {
            return Promise.reject(new Error('Saat offline, barang harus dipilih dari daftar rekomendasi.'));
        }

        var harga = muatan.sell_price === null || muatan.sell_price === undefined || muatan.sell_price === ''
            ? null
            : Number(muatan.sell_price);
        var jumlah = Math.max(1, Number(muatan.quantity || 1));

        var catatan = {
            client_uuid: muatan.client_uuid || buatUuid(),
            barcode: kode,
            product_id: muatan.product_id,
            name: muatan.name || null,
            sell_price: harga,
            quantity: jumlah,
            dibuat_pada: new Date().toISOString(),
            percobaan: 0,
        };

        return transaksi('antrian_barcode', 'readwrite')
            .then(function (store) {
                return jadikanJanji(store.put(catatan));
            })
            .then(function () {
                // Harga sama dengan harga master -> ikut master (harga tetap sama).
                var dasar = muatan.base_price === null || muatan.base_price === undefined
                    ? harga
                    : Number(muatan.base_price);
                var ikutMaster = harga === null || (dasar !== null && Math.abs(harga - dasar) < 0.005);

                return tambahAliasLokal(catatan.product_id, {
                    code: kode,
                    price: ikutMaster ? dasar : harga,
                    price_source: ikutMaster ? 'master' : 'barcode',
                    quantity: jumlah,
                }).catch(function () {
                    return null;
                });
            })
            .then(function () {
                return hitungAntrianBarcode();
            })
            .then(function (jumlahAntrian) {
                beriTahu('pos-offline:antrian-barcode', { jumlah: jumlahAntrian, terakhir: catatan });

                return catatan;
            });
    }

    function daftarAntrianBarcode() {
        return transaksi('antrian_barcode', 'readonly').then(function (store) {
            return jadikanJanji(store.getAll());
        });
    }

    function hitungAntrianBarcode() {
        return transaksi('antrian_barcode', 'readonly').then(function (store) {
            return jadikanJanji(store.count());
        });
    }

    function hapusAntrianBarcode(uuid) {
        return transaksi('antrian_barcode', 'readwrite').then(function (store) {
            return jadikanJanji(store.delete(uuid));
        });
    }

    function tandaiBarcodeGagal(uuid, pesan) {
        return transaksi('antrian_barcode', 'readwrite').then(function (store) {
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

    var sedangKirimBarcode = false;

    /** Mengirim antrian pendaftaran barcode ke server. */
    function kirimAntrianBarcode() {
        if (sedangKirimBarcode || ! navigator.onLine || ! konfig.sinkronisasiBarcode) {
            return Promise.resolve({ dikirim: 0 });
        }

        sedangKirimBarcode = true;

        return daftarAntrianBarcode()
            .then(function (antrian) {
                if (antrian.length === 0) {
                    return { dikirim: 0 };
                }

                var muatan = antrian.slice(0, 50).map(function (item) {
                    return {
                        client_uuid: item.client_uuid,
                        barcode: item.barcode,
                        product_id: item.product_id,
                        name: item.name,
                        sell_price: item.sell_price,
                        quantity: item.quantity,
                    };
                });

                return fetch(konfig.sinkronisasiBarcode, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': konfig.csrf || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ barcodes: muatan }),
                })
                    .then(function (respons) {
                        return respons.json().then(function (isi) {
                            return { ok: respons.ok, isi: isi };
                        });
                    })
                    .then(function (hasil) {
                        if (! hasil.ok) {
                            throw new Error(hasil.isi.message || 'Sinkronisasi barcode ditolak server.');
                        }

                        var tersimpan = 0;
                        var gagal = 0;
                        var tugas = (hasil.isi.results || []).map(function (baris) {
                            if (baris.status === 'tersimpan') {
                                tersimpan += 1;

                                return hapusAntrianBarcode(baris.client_uuid);
                            }

                            gagal += 1;

                            return tandaiBarcodeGagal(baris.client_uuid, baris.message || 'Gagal diproses.');
                        });

                        return Promise.all(tugas).then(function () {
                            return { dikirim: tersimpan, gagal: gagal };
                        });
                    });
            })
            .then(function (ringkasan) {
                sedangKirimBarcode = false;

                return hitungAntrianBarcode().then(function (sisa) {
                    if (ringkasan.dikirim || ringkasan.gagal) {
                        beriTahu('pos-offline:barcode-selesai', {
                            dikirim: ringkasan.dikirim,
                            gagal: ringkasan.gagal || 0,
                            sisa: sisa,
                        });

                        if (ringkasan.dikirim) {
                            segarkanKatalog();
                        }
                    }

                    beriTahu('pos-offline:antrian-barcode', { jumlah: sisa });

                    return ringkasan;
                });
            })
            .catch(function (error) {
                sedangKirimBarcode = false;
                console.warn('[pos-offline] sinkronisasi barcode gagal:', error.message);
                beriTahu('pos-offline:sinkron-gagal', { pesan: error.message });

                return { dikirim: 0, pesan: error.message };
            });
    }

    /* ------------------------------------------------------------------ */
    /* Pemantauan status jaringan                                          */
    /* ------------------------------------------------------------------ */

    function laporkanStatus() {
        Promise.all([hitungAntrian(), hitungAntrianBarcode()])
            .then(function (hasil) {
                beriTahu('pos-offline:status', {
                    online: navigator.onLine,
                    antrian: hasil[0],
                    barcode: hasil[1],
                });
            })
            .catch(function () {
                beriTahu('pos-offline:status', { online: navigator.onLine, antrian: 0, barcode: 0 });
            });
    }

    window.addEventListener('online', function () {
        laporkanStatus();
        segarkanKatalog();
        kirimAntrian();
        kirimAntrianBarcode();
    });

    window.addEventListener('offline', laporkanStatus);

    document.addEventListener('DOMContentLoaded', function () {
        laporkanStatus();
        segarkanKatalog();
        kirimAntrian();
        kirimAntrianBarcode();

        // Coba kirim ulang tiap 60 detik selama tab terbuka.
        setInterval(function () {
            if (navigator.onLine) {
                kirimAntrian();
                kirimAntrianBarcode();
            }
        }, 60000);
    });

    window.PosOffline = {
        buatUuid: buatUuid,
        simpanKatalog: simpanKatalog,
        segarkanKatalog: segarkanKatalog,
        cariKatalogLokal: cariKatalogLokal,
        cariBarcodeLokal: cariBarcodeLokal,
        tambahAliasLokal: tambahAliasLokal,
        infoKatalog: infoKatalog,
        antrikanTransaksi: antrikanTransaksi,
        daftarAntrian: daftarAntrian,
        hitungAntrian: hitungAntrian,
        hapusAntrian: hapusAntrian,
        kirimAntrian: kirimAntrian,
        antrikanBarcode: antrikanBarcode,
        daftarAntrianBarcode: daftarAntrianBarcode,
        hitungAntrianBarcode: hitungAntrianBarcode,
        hapusAntrianBarcode: hapusAntrianBarcode,
        kirimAntrianBarcode: kirimAntrianBarcode,
        laporkanStatus: laporkanStatus,
    };
})();
