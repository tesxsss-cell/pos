@extends('layouts.app')

@section('title', 'Daftar Produk')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Daftarx2 Produk &middot; {{ $branch?->name ?? 'Cabang Anda' }}</h1>
            <p class="text-xs text-slate-500">Barang yang tersedia di cabang Anda. Daftarkan atau ganti barcode langsung dari sini.</p>
        </div>
        <a href="{{ route('pos.index') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Ke Kasir</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div class="flex-1">
            <label class="block text-xs font-medium text-slate-500">Cari nama / SKU / barcode</label>
            <input name="q" value="{{ $q }}" autocomplete="off"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                   placeholder="Ketik nama barang atau SKU">
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Cari</button>
        @if ($q !== '')
            <a href="{{ route('pos.products') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600">Reset</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama produk</th>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3 text-right">Harga</th>
                    <th class="px-4 py-3 text-right">Stok</th>
                    <th class="px-4 py-3">Barcode terdaftar</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $baris)
                    @php($punya = ! empty($baris['barcodes']))
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $baris['name'] }}</div>
                            <div class="text-xs text-slate-400">{{ $baris['unit'] }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $baris['sku'] }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $baris['base_price'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">{{ $baris['stock'] }} {{ $baris['unit'] }}</td>
                        <td class="px-4 py-3">
                            <div id="kode-{{ $baris['id'] }}" class="flex flex-wrap gap-1">
                                @forelse ($baris['barcodes'] as $bc)
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $bc['code'] }}</span>
                                @empty
                                    <span class="teks-kosong text-xs text-slate-400">Belum ada</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button"
                                    data-id="{{ $baris['id'] }}"
                                    class="tombol-barcode rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $punya ? 'bg-amber-600 hover:bg-amber-500' : 'bg-indigo-600 hover:bg-indigo-500' }}">
                                {{ $punya ? 'Ganti barcode' : 'Daftarkan barcode' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Tidak ada produk untuk cabang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>


    

    <div class="mt-4">{{ $paginator->links() }}</div>

    {{-- ================= Pop up daftarkan / ganti barcode ================= --}}
    <div id="popup-barcode" class="fixed inset-0 z-40 hidden items-center justify-center overflow-y-auto bg-slate-900/60 p-3 sm:p-4">
        <div class="my-auto max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-4 shadow-xl sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="popup-judul" class="text-base font-semibold text-slate-900">Daftarkan barcode</h2>
                    <p class="text-xs text-slate-500">Arahkan barcode ke kamera. Kode akan terisi otomatis lalu masih bisa diedit.</p>
                </div>
                <button type="button" id="popup-tutup" class="text-2xl leading-none text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            {{-- Kotak kamera tanpa animasi (diisi oleh BarcodeScanner.pasang) --}}
            <div id="popup-scanner" class="mt-3"></div>

            {{-- Kode barcode: otomatis terisi setelah scan, dapat diedit --}}
            <div class="mt-3">
                <label class="block text-xs font-medium text-slate-500">Kode barcode</label>
                <input id="popup-kode" type="text" inputmode="text" autocomplete="off"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-lg tracking-wide"
                       placeholder="Hasil scan akan muncul di sini">
            </div>

            {{-- Informasi produk dari data cabang (kasir tidak dapat mengubah) --}}
            <div class="mt-3 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-sm sm:grid-cols-3">
                <div class="col-span-2 sm:col-span-3">
                    <span class="text-xs text-slate-500">Nama produk</span>
                    <div id="popup-nama" class="font-medium text-slate-900">-</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Harga</span>
                    <div id="popup-harga" class="font-medium text-slate-900">-</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Stok</span>
                    <div id="popup-stok" class="font-medium text-slate-900">-</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">SKU</span>
                    <div id="popup-sku" class="font-medium text-slate-900">-</div>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-400">Nama produk, harga, dan stok diambil dari data cabang dan tidak dapat diubah kasir.</p>

            <p id="popup-pesan" class="mt-2 text-xs text-slate-500"></p>

            <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" id="popup-batal" class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 sm:w-auto">Batal</button>
                <button type="button" id="popup-simpan" class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 sm:w-auto">Simpan</button>
            </div>
        </div>
    </div>

    {{-- ================= Konfigurasi + skrip ================= --}}
    <script>
        window.POS_OFFLINE_CONFIG = {
            katalog: '{{ route('pos.catalog') }}',
            sinkronisasi: '{{ route('pos.sync') }}',
            sinkronisasiBarcode: '{{ route('pos.sync-barcode') }}',
            csrf: '{{ csrf_token() }}',
        };
        window.DAFTAR_PRODUK = @json($rows);
        window.RUTE_BARCODE_SIMPAN = '{{ route('pos.barcode.store') }}';
    </script>
    {{-- Pemindai offline: dekoder EAN/UPC + pemindai kamera tanpa animasi (mengganti html5-qrcode) --}}
    <script src="{{ asset('js/offline-barcode.js') }}"></script>
    <script src="{{ asset('js/barcode-scanner.js') }}"></script>
    <script src="{{ asset('js/pos-offline.js') }}"></script>
    <script>
        (function () {
            'use strict';

            var peta = {};
            (window.DAFTAR_PRODUK || []).forEach(function (p) { peta[String(p.id)] = p; });

            var popup = document.getElementById('popup-barcode');
            var elJudul = document.getElementById('popup-judul');
            var elKode = document.getElementById('popup-kode');
            var elNama = document.getElementById('popup-nama');
            var elHarga = document.getElementById('popup-harga');
            var elStok = document.getElementById('popup-stok');
            var elSku = document.getElementById('popup-sku');
            var elPesan = document.getElementById('popup-pesan');
            var tombolSimpan = document.getElementById('popup-simpan');

            var kontrolScanner = null;
            var produkAktif = null;
            var tombolAktif = null;

            function csrf() {
                return (window.POS_OFFLINE_CONFIG && window.POS_OFFLINE_CONFIG.csrf) || '';
            }

            function rupiah(n) {
                return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
            }

            function bersihkanKode(kode) {
                return String(kode == null ? '' : kode).replace(/[\u0000-\u001f\s]+/g, '');
            }

            function pesan(teks, kelas) {
                elPesan.className = 'mt-2 text-xs ' + (kelas || 'text-slate-500');
                elPesan.textContent = teks || '';
            }

            function bukaPopup(produk, tombol) {
                produkAktif = produk;
                tombolAktif = tombol;

                var punya = !! (produk.barcodes && produk.barcodes.length);
                elJudul.textContent = punya ? 'Ganti barcode' : 'Daftarkan barcode';
                elNama.textContent = produk.name || '-';
                elHarga.textContent = rupiah(produk.base_price);
                elStok.textContent = (produk.stock != null ? produk.stock : 0) + ' ' + (produk.unit || '');
                elSku.textContent = produk.sku || '-';
                elKode.value = punya ? (produk.barcodes[0].code || '') : '';
                pesan(punya
                    ? 'Scan barcode baru untuk mengganti, atau edit kode di bawah lalu Simpan.'
                    : 'Scan barcode lalu tekan Simpan.', 'text-slate-500');

                popup.classList.remove('hidden');
                popup.classList.add('flex');

                if (window.BarcodeScanner && window.BarcodeScanner.pasang) {
                    kontrolScanner = window.BarcodeScanner.pasang({
                        kontainer: 'popup-scanner',
                        onDeteksi: function (kode) {
                            elKode.value = kode;
                            pesan('Barcode terbaca: ' + kode + '. Tekan Simpan bila sudah benar.', 'text-emerald-600');
                        },
                    });
                    kontrolScanner.mulai().catch(function () {
                        pesan('Kamera tidak aktif. Anda tetap bisa mengetik kode atau menembak dengan alat pemindai USB.', 'text-amber-600');
                    });
                }

                setTimeout(function () { elKode.focus(); }, 60);
            }

            function tutupPopup() {
                if (kontrolScanner) {
                    try { kontrolScanner.hentikan(); } catch (galat) { /* abaikan */ }
                    kontrolScanner = null;
                }
                popup.classList.add('hidden');
                popup.classList.remove('flex');
                produkAktif = null;
                tombolAktif = null;
            }

            function tandaiTerdaftar(produk, kode) {
                if (tombolAktif) {
                    tombolAktif.textContent = 'Ganti barcode';
                    tombolAktif.classList.remove('bg-indigo-600', 'hover:bg-indigo-500');
                    tombolAktif.classList.add('bg-amber-600', 'hover:bg-amber-500');
                }

                var kotak = document.getElementById('kode-' + produk.id);
                if (kotak) {
                    var kosong = kotak.querySelector('.teks-kosong');
                    if (kosong) { kotak.innerHTML = ''; }

                    var sudahAda = false;
                    Array.prototype.forEach.call(kotak.querySelectorAll('span'), function (s) {
                        if (s.textContent === kode) { sudahAda = true; }
                    });
                    if (! sudahAda) {
                        var chip = document.createElement('span');
                        chip.className = 'rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700';
                        chip.textContent = kode;
                        kotak.appendChild(chip);
                    }
                }

                produk.barcodes = produk.barcodes || [];
                if (! produk.barcodes.some(function (b) { return b.code === kode; })) {
                    produk.barcodes.push({ code: kode });
                }
            }

            function simpan() {
                if (! produkAktif) { return; }

                var kode = bersihkanKode(elKode.value);
                if (! kode) {
                    pesan('Barcode belum terisi. Scan atau ketik kodenya lebih dahulu.', 'text-red-600');
                    return;
                }

                var produk = produkAktif;

                // Offline: simpan ke antrian lokal, sinkron otomatis saat kembali online.
                if (! navigator.onLine) {
                    if (! window.PosOffline) {
                        pesan('Mode offline tidak tersedia di peramban ini.', 'text-red-600');
                        return;
                    }

                    window.PosOffline.antrikanBarcode({
                        barcode: kode,
                        product_id: produk.id,
                        name: produk.name,
                        sell_price: null,
                        base_price: produk.base_price,
                        quantity: produk.default_quantity || 1,
                    }).then(function () {
                        pesan('Tersimpan offline. Akan dikirim otomatis saat jaringan kembali.', 'text-emerald-600');
                        tandaiTerdaftar(produk, kode);
                        setTimeout(tutupPopup, 900);
                    }).catch(function (galat) {
                        pesan((galat && galat.message) ? galat.message : 'Gagal menyimpan ke antrian offline.', 'text-red-600');
                    });

                    return;
                }

                tombolSimpan.disabled = true;
                pesan('Menyimpan\u2026', 'text-slate-500');

                fetch(window.RUTE_BARCODE_SIMPAN, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        barcode: kode,
                        product_id: produk.id,
                        quantity: produk.default_quantity || 1,
                    }),
                })
                    .then(function (respons) {
                        return respons.json().then(function (isi) {
                            return { ok: respons.ok, isi: isi };
                        });
                    })
                    .then(function (hasil) {
                        tombolSimpan.disabled = false;

                        if (! hasil.ok) {
                            pesan(hasil.isi.message || 'Barcode gagal disimpan.', 'text-red-600');
                            return;
                        }

                        pesan(hasil.isi.message || 'Barcode tersimpan.', 'text-emerald-600');

                        if (hasil.isi.product && hasil.isi.product.barcodes) {
                            produk.barcodes = hasil.isi.product.barcodes;
                        }

                        tandaiTerdaftar(produk, kode);

                        if (window.PosOffline) { window.PosOffline.segarkanKatalog(); }

                        setTimeout(tutupPopup, 900);
                    })
                    .catch(function () {
                        tombolSimpan.disabled = false;
                        pesan('Jaringan bermasalah. Coba lagi, atau simpan saat offline (otomatis dikirim nanti).', 'text-red-600');
                    });
            }

            /* ------------------------------------------------------------ */

            Array.prototype.forEach.call(document.querySelectorAll('.tombol-barcode'), function (tombol) {
                tombol.addEventListener('click', function () {
                    var produk = peta[String(tombol.getAttribute('data-id'))];
                    if (produk) { bukaPopup(produk, tombol); }
                });
            });

            document.getElementById('popup-tutup').addEventListener('click', tutupPopup);
            document.getElementById('popup-batal').addEventListener('click', tutupPopup);
            tombolSimpan.addEventListener('click', simpan);

            popup.addEventListener('click', function (event) {
                if (event.target === popup) { tutupPopup(); }
            });

            elKode.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); simpan(); }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && ! popup.classList.contains('hidden')) { tutupPopup(); }
            });
        })();
    </script>
@endsection
