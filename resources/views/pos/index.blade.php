@extends('layouts.app')

@section('title', 'Kasir')

@section('content')
    <div class="grid gap-4 lg:grid-cols-3">
        {{-- =========== Kolom kiri: pencarian, pemindaian, pendaftaran barcode, keranjang =========== --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl bg-white p-4 shadow">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">Kasir &middot; {{ $branch->name }}</h1>
                        <p class="text-xs text-slate-500">Cari barang, atau langsung pindai barcodenya.</p>
                    </div>
                    @if ($shift)
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
                            Shift dibuka {{ $shift->opened_at?->format('d/m/Y H:i') }}
                        </span>
                    @else
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">
                            Shift belum dibuka
                        </span>
                    @endif
                </div>






                
                
                <div class="mt-3 flex flex-wrap gap-2">
                    <input id="cari" type="text" autocomplete="off"
                           placeholder="Nama barang, SKU, atau tembak barcode ke sini"
                           class="min-w-[220px] flex-1 rounded-md border border-slate-300 px-3 py-2">

                    {{-- Tombol pindai: cukup diklik, tanpa jalan pintas papan tombol apa pun. --}}
                    <button type="button" id="tombol-pindai"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        Pindai barcode
                    </button>

                    {{-- Tombol pendaftaran barcode barang baru. --}}
                    <button type="button" id="tombol-daftar"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        Daftarkan barcode
                    </button>
                </div>

                <p class="mt-2 text-xs text-slate-400">
                    Pemindai kamera tanpa animasi (ringan) dan bisa dipakai offline. Semua tombol bisa langsung diklik &mdash; tanpa perlu menekan F2/F3.
                    Alat pemindai USB juga tetap terbaca walau kolom pencarian belum diklik.
                    (Jalan pintas F2 &amp; F3 tetap tersedia bila Anda terbiasa memakainya.)
                </p>

                {{-- Informasi barang hasil pemindaian --}}
                <div id="info-pindai" class="mt-3 hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"></div>

                <div id="hasil" class="mt-3 space-y-2"></div>
            </div>

            {{-- =========== Panel pendaftaran barcode barang =========== --}}
            <div id="panel-barcode" class="hidden rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 shadow">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Daftarkan barcode barang</h2>
                        <p class="text-xs text-slate-600">
                            Pindai barcode &rarr; ketik nama barang (rekomendasi muncul otomatis) &rarr; isi harga &amp; jumlah &rarr; simpan.
                            Setelah tersimpan, barcode cukup dipindai saat ada pembeli.
                        </p>
                    </div>
                    <button type="button" id="bc-batal" class="rounded-md px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-white">Tutup</button>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Barcode</label>
                        <div class="mt-1 flex gap-2">
                            <input id="bc-kode" type="text" autocomplete="off" placeholder="Hasil pindai / ketik manual"
                                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <button type="button" id="bc-pindai"
                                    class="whitespace-nowrap rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                                Pindai
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <label class="block text-xs font-medium text-slate-600">Nama barang</label>
                        <input id="bc-nama" type="text" autocomplete="off" placeholder="Ketik nama barang, mis. Minyak goreng"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <div id="bc-saran" class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-auto rounded-md border border-slate-200 bg-white shadow-lg"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600">Harga jual (Rp)</label>
                        <input id="bc-harga" type="number" min="0" step="0.01"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-[11px] text-slate-500">
                            Biarkan sama dengan harga barang bila harga tidak berubah &mdash; harga otomatis mengikuti harga master.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600">Jumlah saat dipindai</label>
                        <input id="bc-jumlah" type="number" min="1" step="1" value="1"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-[11px] text-slate-500">Jumlah ini tetap bisa diubah di keranjang saat penjualan.</p>
                    </div>
                </div>

                <div id="bc-terpilih" class="mt-3 hidden rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></div>
                <div id="bc-daftar" class="mt-2 hidden text-xs text-slate-600"></div>

                <div class="mt-3 space-y-1">
                    <label class="flex items-center gap-2 text-xs text-slate-700">
                        <input type="checkbox" id="bc-ke-keranjang" class="rounded border-slate-300">
                        Langsung masukkan ke keranjang setelah disimpan
                    </label>
                    @if ($canUpdatePrice)
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" id="bc-master" class="rounded border-slate-300">
                            Perbarui juga harga master barang ini
                        </label>
                    @endif
                    <label class="flex items-center gap-2 text-xs text-slate-700">
                        <input type="checkbox" id="bc-pindah" class="rounded border-slate-300">
                        Pindahkan barcode bila sudah dipakai barang lain
                    </label>
                </div>

                <p id="bc-pesan" class="mt-2 text-xs"></p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="bc-simpan"
                            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Simpan barcode
                    </button>
                    <button type="button" id="bc-bersih"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        Kosongkan
                    </button>
                </div>
            </div>

            {{-- =========== Keranjang =========== --}}
            <div class="rounded-xl bg-white p-4 shadow">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900">Keranjang</h2>
                    <button type="button" id="kosongkan" class="text-xs font-medium text-slate-500 hover:text-red-600">Kosongkan keranjang</button>
                </div>

                <p id="kosong" class="mt-3 rounded-lg border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-400">
                    Belum ada barang. Pindai barcode atau cari nama barang di atas.
                </p>

                <div id="keranjang" class="mt-3 space-y-2"></div>
            </div>
        </div>

        {{-- =========== Kolom kanan: pembayaran, shift, offline, riwayat =========== --}}
        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="text-base font-semibold text-slate-900">Pembayaran</h2>

                <div class="mt-3 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Nama pelanggan</label>
                        <input id="pelanggan" type="text" placeholder="Umum"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600">Metode bayar</label>
                        <select id="metode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($paymentMethods as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Diskon (Rp)</label>
                            <input id="diskon" type="number" min="0" step="0.01" value="0"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Uang dibayar (Rp)</label>
                            <input id="bayar" type="number" min="0" step="0.01" value="0"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600">Catatan</label>
                        <input id="catatan" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <dl class="space-y-1 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd id="v-subtotal" class="font-medium">Rp 0</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Total</dt><dd id="v-total" class="text-base font-semibold text-slate-900">Rp 0</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Kembali</dt><dd id="v-kembali" class="font-medium">Rp 0</dd></div>
                    </dl>

                    <button type="button" id="simpan"
                            class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Simpan transaksi
                    </button>

                    <p id="pesan" class="text-xs"></p>
                </div>
            </div>

            {{-- Shift kasir (HF-06) --}}
            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="text-base font-semibold text-slate-900">Shift kasir</h2>

                @if ($shift)
                    <dl class="mt-2 space-y-1 text-sm text-slate-600">
                        <div class="flex justify-between"><dt>Dibuka</dt><dd>{{ $shift->opened_at?->format('d/m/Y H:i') }}</dd></div>
                        <div class="flex justify-between"><dt>Kas awal</dt><dd>Rp {{ number_format((float) $shift->opening_cash, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between"><dt>Kas sistem</dt><dd>Rp {{ number_format((float) $shift->expected_cash, 0, ',', '.') }}</dd></div>
                    </dl>

                    <form method="POST" action="{{ route('pos.shift.close') }}" class="mt-3 space-y-2">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Uang fisik di laci (Rp)</label>
                            <input name="actual_cash" type="number" min="0" step="0.01" required
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Catatan</label>
                            <input name="note" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Tutup shift
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('pos.shift.open') }}" class="mt-3 space-y-2">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Kas awal (Rp)</label>
                            <input name="opening_cash" type="number" min="0" step="0.01" value="0" required
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Buka shift
                        </button>
                    </form>
                @endif
            </div>

            {{-- Mode offline --}}
            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="text-base font-semibold text-slate-900">Mode offline</h2>

                <dl class="mt-2 space-y-1 text-sm text-slate-600">
                    <div class="flex justify-between"><dt>Status jaringan</dt><dd id="status-jaringan" class="font-medium">-</dd></div>
                    <div class="flex justify-between"><dt>Transaksi menunggu</dt><dd id="jumlah-antrian" class="font-medium">0</dd></div>
                    <div class="flex justify-between"><dt>Barcode menunggu</dt><dd id="jumlah-antrian-barcode" class="font-medium">0</dd></div>
                </dl>

                <p id="info-katalog" class="mt-2 text-xs text-slate-500">Katalog offline belum diunduh.</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="kirim-antrian"
                            class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                        Kirim transaksi
                    </button>
                    <button type="button" id="kirim-antrian-barcode"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
                        Kirim barcode
                    </button>
                    <button type="button" id="segarkan-katalog"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                        Segarkan katalog
                    </button>
                </div>
            </div>

            {{-- Transaksi terakhir --}}
            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="text-base font-semibold text-slate-900">Transaksi terakhir</h2>

                <div class="mt-2 space-y-2">
                    @forelse ($recentSales as $sale)
                        <div class="rounded-lg border border-slate-200 p-2 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $sale->invoice_no }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $sale->sold_at?->format('d/m H:i') }} &middot;
                                        Rp {{ number_format((float) $sale->total, 0, ',', '.') }}
                                        @if (! $sale->isCompleted()) &middot; <span class="text-red-600">dibatalkan</span> @endif
                                    </p>
                                </div>
                                <a href="{{ route('pos.receipt', $sale) }}" target="_blank"
                                   class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                    Struk
                                </a>
                            </div>

                            @if ($sale->isCompleted())
                                <form method="POST" action="{{ route('pos.void', $sale) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input name="reason" type="text" required placeholder="Alasan pembatalan"
                                           class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                    <button class="whitespace-nowrap rounded-md bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-500">
                                        Batalkan
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Belum ada transaksi pada shift ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        window.POS_OFFLINE_CONFIG = {
            katalog: '{{ route('pos.catalog') }}',
            sinkronisasi: '{{ route('pos.sync') }}',
            sinkronisasiBarcode: '{{ route('pos.sync-barcode') }}',
            csrf: '{{ csrf_token() }}',
        };
    </script>
    {{-- Mesin pemindai: dekoder barcode offline (EAN/UPC) + BarcodeDetector bawaan browser,
         tanpa pustaka html5-qrcode. Semua berkas lokal sehingga tetap jalan offline. --}}
    <script src="{{ asset('js/offline-barcode.js') }}"></script>
    <script src="{{ asset('js/barcode-scanner.js') }}"></script>
    <script src="{{ asset('js/pos-offline.js') }}"></script>

    <script>
        (function () {
            'use strict';

            var rute = {
                cari: '{{ route('pos.lookup') }}',
                checkout: '{{ route('pos.checkout') }}',
                barcodeCek: '{{ route('pos.barcode.resolve') }}',
                barcodeSaran: '{{ route('pos.barcode.suggest') }}',
                barcodeSimpan: '{{ route('pos.barcode.store') }}',
                barcodeHapus: '{{ url('kasir/barcode') }}',
            };
            var csrf = '{{ csrf_token() }}';
            var bolehUbahMaster = @json($canUpdatePrice);

            var keranjang = [];
            var produkBarcode = null;   // barang terpilih pada panel pendaftaran
            var jedaSaran = null;

            function el(id) {
                return document.getElementById(id);
            }

            function rupiah(nilai) {
                return 'Rp ' + Number(nilai || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            }

            function amanTeks(teks) {
                var kotak = document.createElement('span');
                kotak.textContent = teks === null || teks === undefined ? '' : String(teks);

                return kotak.innerHTML;
            }

            function bersihkanKode(kode) {
                return String(kode || '').replace(/\s+/g, '');
            }

            /** Teks yang mirip barcode: cukup panjang, tanpa spasi. */
            function sepertiBarcode(teks) {
                var kode = String(teks || '');

                return /^[0-9A-Za-z\-\._]{6,}$/.test(kode) && ! /\s/.test(kode);
            }

            /* ------------------------------------------------------------ */
            /* Keranjang                                                     */
            /* ------------------------------------------------------------ */

            function hitung() {
                var subtotal = keranjang.reduce(function (jumlah, item) {
                    return jumlah + (Number(item.unit_price) * Number(item.quantity));
                }, 0);

                var diskon = Number(el('diskon').value || 0);
                var total = Math.max(0, subtotal - diskon);
                var bayar = Number(el('bayar').value || 0);

                el('v-subtotal').textContent = rupiah(subtotal);
                el('v-total').textContent = rupiah(total);
                el('v-kembali').textContent = rupiah(Math.max(0, bayar - total));

                return { subtotal: subtotal, total: total };
            }

            function gambarKeranjang() {
                var wadah = el('keranjang');
                wadah.innerHTML = '';

                if (keranjang.length === 0) {
                    el('kosong').classList.remove('hidden');
                    hitung();

                    return;
                }

                el('kosong').classList.add('hidden');

                keranjang.forEach(function (item, indeks) {
                    var hargaBerubah = Number(item.unit_price) !== Number(item.base_price);

                    var baris = document.createElement('div');
                    baris.className = 'rounded-lg border border-slate-200 p-3';
                    baris.innerHTML = ''
                        + '<div class="flex items-start justify-between gap-2">'
                        + '<div>'
                        + '<p class="text-sm font-semibold text-slate-900">' + amanTeks(item.name) + '</p>'
                        + '<p class="text-xs text-slate-500">' + amanTeks(item.sku)
                        + (item.barcode ? ' &middot; ' + amanTeks(item.barcode) : '')
                        + ' &middot; stok ' + amanTeks(item.stock) + ' ' + amanTeks(item.unit || '')
                        + '</p>'
                        + '</div>'
                        + '<button type="button" data-hapus="' + indeks + '" class="text-xs font-semibold text-red-600 hover:underline">Hapus</button>'
                        + '</div>'
                        + '<div class="mt-2 grid grid-cols-3 items-end gap-2">'
                        + '<label class="text-[11px] text-slate-500">Jumlah'
                        + '<input type="number" min="1" step="1" value="' + Number(item.quantity) + '" data-qty="' + indeks + '"'
                        + ' class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></label>'
                        + '<label class="text-[11px] text-slate-500">Harga satuan'
                        + '<input type="number" min="0" step="0.01" value="' + Number(item.unit_price) + '" data-harga="' + indeks + '"'
                        + ' class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></label>'
                        + '<div class="text-right text-[11px] text-slate-500">Subtotal'
                        + '<p class="mt-1 text-sm font-semibold text-slate-900">' + rupiah(Number(item.unit_price) * Number(item.quantity)) + '</p>'
                        + '</div>'
                        + '</div>'
                        + '<p class="mt-1 text-[11px] ' + (hargaBerubah ? 'text-amber-600' : 'text-emerald-600') + '">'
                        + (hargaBerubah
                            ? 'Harga diubah (harga tersimpan ' + rupiah(item.base_price) + ')'
                            : 'Harga tetap sama seperti yang tersimpan')
                        + '</p>';

                    wadah.appendChild(baris);
                });

                wadah.querySelectorAll('[data-qty]').forEach(function (isian) {
                    isian.addEventListener('input', function () {
                        var indeks = Number(isian.getAttribute('data-qty'));
                        keranjang[indeks].quantity = Math.max(1, Number(isian.value || 1));
                        hitung();
                        gambarKeranjang();
                    });
                });

                wadah.querySelectorAll('[data-harga]').forEach(function (isian) {
                    isian.addEventListener('input', function () {
                        var indeks = Number(isian.getAttribute('data-harga'));
                        keranjang[indeks].unit_price = Math.max(0, Number(isian.value || 0));
                        keranjang[indeks].harga_diubah = true;
                        hitung();
                    });
                });

                wadah.querySelectorAll('[data-hapus]').forEach(function (tombol) {
                    tombol.addEventListener('click', function () {
                        keranjang.splice(Number(tombol.getAttribute('data-hapus')), 1);
                        gambarKeranjang();
                    });
                });

                hitung();
            }

            /**
             * Menambah barang ke keranjang.
             * Harga & jumlah memakai nilai tersimpan pada barcode, dan tetap bisa
             * diubah kasir. Bila kasir sudah mengubah harga, harga itu dipertahankan.
             */
            function tambah(produk, jumlah) {
                var tambahan = Math.max(1, Number(jumlah || produk.default_quantity || 1));
                var hargaTersimpan = Number(produk.sell_price || 0);
                var ada = null;

                keranjang.forEach(function (item) {
                    if (item.id === produk.id) {
                        ada = item;
                    }
                });

                if (ada) {
                    ada.quantity += tambahan;

                    if (! ada.harga_diubah) {
                        ada.unit_price = hargaTersimpan;
                        ada.base_price = Number(produk.base_price !== undefined && produk.base_price !== null
                            ? produk.base_price
                            : hargaTersimpan);
                    }
                } else {
                    keranjang.push({
                        id: produk.id,
                        sku: produk.sku,
                        name: produk.name,
                        unit: produk.unit,
                        stock: produk.stock,
                        barcode: produk.matched_barcode || produk.barcode || null,
                        base_price: Number(produk.base_price !== undefined && produk.base_price !== null
                            ? produk.base_price
                            : hargaTersimpan),
                        unit_price: hargaTersimpan,
                        quantity: tambahan,
                        harga_diubah: false,
                    });
                }

                gambarKeranjang();
                tampilkanInfoPindai(produk, tambahan);
            }

            /** Banner informasi barang hasil pemindaian. */
            function tampilkanInfoPindai(produk, jumlah) {
                var kotak = el('info-pindai');
                var sumber = produk.price_source === 'barcode'
                    ? 'harga khusus barcode'
                    : 'harga master barang';

                kotak.classList.remove('hidden');
                kotak.innerHTML = '<strong>' + amanTeks(produk.name) + '</strong> masuk keranjang &middot; '
                    + rupiah(produk.sell_price) + ' (' + sumber + ') &middot; ' + jumlah + ' ' + amanTeks(produk.unit || '')
                    + ' &middot; stok ' + amanTeks(produk.stock)
                    + '<br><span class="text-[11px]">Harga &amp; jumlah masih bisa diubah pada baris keranjang.</span>';
            }

            /* ------------------------------------------------------------ */
            /* Pencarian & pemindaian untuk penjualan                        */
            /* ------------------------------------------------------------ */

            function tampilkanHasil(daftar) {
                var wadah = el('hasil');
                wadah.innerHTML = '';

                if (! daftar || daftar.length === 0) {
                    wadah.innerHTML = '<p class="text-xs text-slate-400">Barang tidak ditemukan.</p>';

                    return;
                }

                daftar.forEach(function (produk) {
                    var tombol = document.createElement('button');
                    tombol.type = 'button';
                    tombol.className = 'flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left hover:bg-slate-50';
                    tombol.innerHTML = '<span>'
                        + '<span class="block text-sm font-medium text-slate-900">' + amanTeks(produk.name) + '</span>'
                        + '<span class="block text-xs text-slate-500">' + amanTeks(produk.sku)
                        + (produk.barcode ? ' &middot; ' + amanTeks(produk.barcode) : '')
                        + ' &middot; stok ' + amanTeks(produk.stock) + '</span>'
                        + '</span>'
                        + '<span class="text-sm font-semibold text-slate-900">' + rupiah(produk.sell_price) + '</span>';

                    tombol.addEventListener('click', function () {
                        tambah(produk, produk.default_quantity);
                    });

                    wadah.appendChild(tombol);
                });
            }

            /** Mengubah hasil katalog lokal menjadi bentuk yang sama dengan server. */
            function dariKatalogLokal(hasil) {
                var produk = hasil.produk;
                var alias = hasil.alias;

                return {
                    id: produk.id,
                    sku: produk.sku,
                    name: produk.name,
                    unit: produk.unit,
                    stock: produk.stock,
                    barcode: alias ? alias.code : produk.barcode,
                    matched_barcode: alias ? alias.code : produk.barcode,
                    base_price: produk.base_price !== undefined && produk.base_price !== null
                        ? produk.base_price
                        : produk.sell_price,
                    sell_price: alias && alias.price !== null && alias.price !== undefined
                        ? alias.price
                        : produk.sell_price,
                    price_source: alias ? (alias.price_source || 'barcode') : 'master',
                    default_quantity: alias && alias.quantity ? alias.quantity : (produk.default_quantity || 1),
                    barcodes: produk.barcodes || [],
                };
            }

            function cariLokal(kataKunci) {
                if (! window.PosOffline) {
                    tampilkanHasil([]);

                    return;
                }

                window.PosOffline.cariKatalogLokal(kataKunci).then(function (hasil) {
                    tampilkanHasil(hasil.map(function (produk) {
                        return dariKatalogLokal({ produk: produk, alias: null });
                    }));
                });
            }

            /** Pencarian manual maupun hasil pemindaian. */
            function cari(kataKunci) {
                var kata = String(kataKunci || '').trim();

                if (kata === '') {
                    el('hasil').innerHTML = '';

                    return;
                }

                if (! navigator.onLine) {
                    pindaiOffline(kata);

                    return;
                }

                fetch(rute.cari + '?q=' + encodeURIComponent(kata), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (respons) {
                        return respons.json();
                    })
                    .then(function (isi) {
                        var daftar = isi.data || [];
                        var kode = isi.scanned_barcode;

                        // Barcode terdaftar: barang langsung masuk keranjang.
                        if (kode && isi.barcode_registered && daftar.length > 0) {
                            var cocok = null;

                            daftar.forEach(function (produk) {
                                if (! cocok && (bersihkanKode(produk.matched_barcode) === kode || bersihkanKode(produk.barcode) === kode)) {
                                    cocok = produk;
                                }
                            });

                            if (cocok) {
                                tambah(cocok, cocok.default_quantity);
                                el('cari').value = '';
                                el('hasil').innerHTML = '';

                                return;
                            }
                        }

                        // Barcode belum terdaftar: panel pendaftaran dibuka otomatis.
                        if (kode && ! isi.barcode_registered && daftar.length === 0 && sepertiBarcode(kata)) {
                            bukaPanelBarcode(kode);
                            tampilkanPesan('Barcode ' + kode + ' belum terdaftar. Ketik nama barangnya untuk dicocokkan.', 'text-amber-600');
                            el('cari').value = '';

                            return;
                        }

                        tampilkanHasil(daftar);
                    })
                    .catch(function () {
                        pindaiOffline(kata);
                    });
            }

            /** Pemindaian/pencarian ketika jaringan mati (katalog IndexedDB). */
            function pindaiOffline(kata) {
                if (! window.PosOffline) {
                    tampilkanHasil([]);

                    return;
                }

                window.PosOffline.cariBarcodeLokal(kata).then(function (hasil) {
                    if (hasil) {
                        var produk = dariKatalogLokal(hasil);
                        tambah(produk, produk.default_quantity);
                        el('cari').value = '';
                        el('hasil').innerHTML = '';

                        return;
                    }

                    if (sepertiBarcode(kata)) {
                        bukaPanelBarcode(bersihkanKode(kata));
                        tampilkanPesan('Barcode belum dikenal katalog offline. Pilih barang dari rekomendasi lalu simpan.', 'text-amber-600');
                        el('cari').value = '';

                        return;
                    }

                    cariLokal(kata);
                });
            }

            /** Satu pintu untuk semua hasil pemindaian (kamera, alat USB, ketik manual). */
            function tanganiPindai(kode) {
                var bersih = bersihkanKode(kode);

                if (! bersih) {
                    return;
                }

                // Panel pendaftaran terbuka -> kode masuk ke kolom barcode.
                if (! el('panel-barcode').classList.contains('hidden')) {
                    el('bc-kode').value = bersih;
                    pesanBarcode('Barcode ' + bersih + ' terbaca. Ketik nama barangnya.', 'text-slate-600');
                    el('bc-nama').focus();

                    return;
                }

                el('cari').value = bersih;
                cari(bersih);
            }

            function bukaPemindai(sekaliPakai) {
                if (! window.BarcodeScanner) {
                    tampilkanPesan('Modul pemindai belum siap. Muat ulang halaman.', 'text-red-600');

                    return;
                }

                window.BarcodeScanner.mulai({
                    sekaliPakai: sekaliPakai === true,
                    onDeteksi: tanganiPindai,
                    onGagal: function (galat) {
                        var pesan = (galat && galat.message)
                            ? galat.message
                            : (typeof galat === 'string' && galat ? galat : 'kamera tidak aktif');
                        tampilkanPesan('Pemindai kamera tidak aktif: ' + pesan + ' Silakan pakai kotak ketik/tembak kode yang muncul, atau alat pemindai USB.', 'text-amber-600');
                    },
                });
            }

            /* ------------------------------------------------------------ */
            /* Panel pendaftaran barcode                                     */
            /* ------------------------------------------------------------ */

            function pesanBarcode(teks, kelas) {
                var kotak = el('bc-pesan');
                kotak.className = 'mt-2 text-xs ' + (kelas || 'text-slate-600');
                kotak.textContent = teks || '';
            }

            function bukaPanelBarcode(kode) {
                el('panel-barcode').classList.remove('hidden');
                el('bc-kode').value = kode ? bersihkanKode(kode) : '';

                if (kode) {
                    el('bc-nama').focus();
                } else {
                    el('bc-kode').focus();
                }

                pesanBarcode(kode
                    ? 'Barcode ' + bersihkanKode(kode) + ' siap dipasangkan ke barang.'
                    : 'Pindai atau ketik barcodenya lebih dahulu.', 'text-slate-600');
            }

            function tutupPanelBarcode() {
                el('panel-barcode').classList.add('hidden');
                el('bc-saran').classList.add('hidden');
            }

            function bersihkanPanelBarcode() {
                produkBarcode = null;
                el('bc-kode').value = '';
                el('bc-nama').value = '';
                el('bc-harga').value = '';
                el('bc-jumlah').value = 1;
                el('bc-terpilih').classList.add('hidden');
                el('bc-daftar').classList.add('hidden');
                el('bc-saran').classList.add('hidden');
                pesanBarcode('');
            }

            function gambarSaran(daftar) {
                var kotak = el('bc-saran');
                kotak.innerHTML = '';

                if (! daftar || daftar.length === 0) {
                    kotak.classList.add('hidden');

                    return;
                }

                daftar.forEach(function (produk) {
                    var pilihan = document.createElement('button');
                    pilihan.type = 'button';
                    pilihan.className = 'flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-indigo-50';
                    pilihan.innerHTML = '<span>'
                        + '<span class="block font-medium text-slate-900">' + amanTeks(produk.name) + '</span>'
                        + '<span class="block text-xs text-slate-500">' + amanTeks(produk.sku)
                        + ' &middot; stok ' + amanTeks(produk.stock) + '</span>'
                        + '</span>'
                        + '<span class="text-xs font-semibold text-slate-700">' + rupiah(produk.sell_price) + '</span>';

                    pilihan.addEventListener('click', function () {
                        pilihProdukBarcode(produk);
                    });

                    kotak.appendChild(pilihan);
                });

                kotak.classList.remove('hidden');
            }

            /** Rekomendasi barang muncul otomatis sambil kasir mengetik nama. */
            function cariSaran(nama) {
                var kata = String(nama || '').trim();

                if (kata.length < 2) {
                    el('bc-saran').classList.add('hidden');

                    return;
                }

                if (! navigator.onLine) {
                    if (window.PosOffline) {
                        window.PosOffline.cariKatalogLokal(kata).then(function (hasil) {
                            gambarSaran(hasil.map(function (produk) {
                                return dariKatalogLokal({ produk: produk, alias: null });
                            }));
                        });
                    }

                    return;
                }

                fetch(rute.barcodeSaran + '?q=' + encodeURIComponent(kata), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (respons) {
                        return respons.json();
                    })
                    .then(function (isi) {
                        gambarSaran(isi.data || []);
                    })
                    .catch(function () {
                        el('bc-saran').classList.add('hidden');
                    });
            }

            function gambarBarcodeTerdaftar(produk) {
                var kotak = el('bc-daftar');
                var daftar = produk.barcodes || [];

                if (daftar.length === 0) {
                    kotak.classList.add('hidden');
                    kotak.innerHTML = '';

                    return;
                }

                kotak.innerHTML = '<p class="mb-1 font-medium text-slate-700">Barcode terdaftar untuk barang ini:</p>';

                daftar.forEach(function (satu) {
                    var baris = document.createElement('div');
                    baris.className = 'flex items-center justify-between gap-2 rounded-md bg-white px-2 py-1';
                    baris.innerHTML = '<span>' + amanTeks(satu.code) + ' &middot; ' + rupiah(satu.price)
                        + ' &middot; ' + amanTeks(satu.quantity) + ' pcs'
                        + (satu.price_source === 'master' ? ' &middot; ikut harga master' : '')
                        + '</span>';

                    if (satu.id) {
                        var hapus = document.createElement('button');
                        hapus.type = 'button';
                        hapus.className = 'text-[11px] font-semibold text-red-600 hover:underline';
                        hapus.textContent = 'Lepas';
                        hapus.addEventListener('click', function () {
                            hapusBarcodeTerdaftar(satu.id);
                        });
                        baris.appendChild(hapus);
                    }

                    kotak.appendChild(baris);
                });

                kotak.classList.remove('hidden');
            }

            function pilihProdukBarcode(produk) {
                produkBarcode = produk;

                el('bc-nama').value = produk.name;
                el('bc-harga').value = Number(produk.sell_price || 0);
                el('bc-jumlah').value = Number(produk.default_quantity || 1);
                el('bc-saran').classList.add('hidden');

                var kotak = el('bc-terpilih');
                kotak.classList.remove('hidden');
                kotak.innerHTML = '<p class="font-semibold text-slate-900">' + amanTeks(produk.name) + '</p>'
                    + '<p class="text-xs text-slate-500">' + amanTeks(produk.sku)
                    + ' &middot; harga master ' + rupiah(produk.base_price)
                    + ' &middot; stok ' + amanTeks(produk.stock) + ' ' + amanTeks(produk.unit || '') + '</p>';

                gambarBarcodeTerdaftar(produk);
                pesanBarcode('Barang dipilih. Sesuaikan harga & jumlah lalu simpan.', 'text-slate-600');
            }

            function simpanBarcode() {
                var kode = bersihkanKode(el('bc-kode').value);
                var nama = el('bc-nama').value.trim();
                var harga = el('bc-harga').value;
                var jumlah = Math.max(1, Number(el('bc-jumlah').value || 1));
                var keKeranjang = el('bc-ke-keranjang').checked;

                if (! kode) {
                    pesanBarcode('Barcode belum terbaca. Pindai atau ketik kodenya.', 'text-red-600');

                    return;
                }

                if (! produkBarcode && ! nama) {
                    pesanBarcode('Ketik nama barang lalu pilih rekomendasinya.', 'text-red-600');

                    return;
                }

                // Offline: simpan ke antrian lokal, barang wajib dipilih dari rekomendasi.
                if (! navigator.onLine) {
                    if (! window.PosOffline || ! produkBarcode) {
                        pesanBarcode('Sedang offline: pilih barang dari daftar rekomendasi lebih dahulu.', 'text-red-600');

                        return;
                    }

                    window.PosOffline.antrikanBarcode({
                        barcode: kode,
                        product_id: produkBarcode.id,
                        name: produkBarcode.name,
                        sell_price: harga === '' ? null : Number(harga),
                        base_price: produkBarcode.base_price,
                        quantity: jumlah,
                    }).then(function () {
                        pesanBarcode('Barcode disimpan lokal dan akan dikirim saat jaringan kembali.', 'text-emerald-600');

                        if (keKeranjang) {
                            tambah(Object.assign({}, produkBarcode, {
                                sell_price: harga === '' ? produkBarcode.sell_price : Number(harga),
                                matched_barcode: kode,
                                price_source: 'barcode',
                            }), jumlah);
                        }

                        bersihkanPanelBarcode();
                    }).catch(function (galat) {
                        pesanBarcode((galat && galat.message) ? galat.message : 'Gagal menyimpan barcode ke antrian.', 'text-red-600');
                    });

                    return;
                }

                var muatan = {
                    barcode: kode,
                    product_id: produkBarcode ? produkBarcode.id : null,
                    name: nama || null,
                    sell_price: harga === '' ? null : Number(harga),
                    quantity: jumlah,
                    update_product_price: bolehUbahMaster && el('bc-master') ? el('bc-master').checked : false,
                    replace: el('bc-pindah').checked,
                };

                pesanBarcode('Menyimpan...', 'text-slate-500');

                fetch(rute.barcodeSimpan, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(muatan),
                })
                    .then(function (respons) {
                        return respons.json().then(function (isi) {
                            return { ok: respons.ok, isi: isi };
                        });
                    })
                    .then(function (hasil) {
                        if (! hasil.ok) {
                            pesanBarcode(hasil.isi.message || 'Barcode gagal disimpan.', 'text-red-600');
                            gambarSaran(hasil.isi.suggestions || []);

                            return;
                        }

                        pesanBarcode(hasil.isi.message, 'text-emerald-600');

                        if (keKeranjang && hasil.isi.product) {
                            tambah(hasil.isi.product, hasil.isi.product.default_quantity);
                        }

                        bersihkanPanelBarcode();

                        if (window.PosOffline) {
                            window.PosOffline.segarkanKatalog();
                        }
                    })
                    .catch(function () {
                        pesanBarcode('Jaringan bermasalah. Coba lagi atau simpan saat offline.', 'text-red-600');
                    });
            }

            function hapusBarcodeTerdaftar(id) {
                fetch(rute.barcodeHapus + '/' + id, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                })
                    .then(function (respons) {
                        return respons.json();
                    })
                    .then(function (isi) {
                        pesanBarcode(isi.message || 'Barcode dilepas.', 'text-emerald-600');

                        if (produkBarcode) {
                            produkBarcode.barcodes = (produkBarcode.barcodes || []).filter(function (satu) {
                                return satu.id !== id;
                            });
                            gambarBarcodeTerdaftar(produkBarcode);
                        }

                        if (window.PosOffline) {
                            window.PosOffline.segarkanKatalog();
                        }
                    })
                    .catch(function () {
                        pesanBarcode('Barcode gagal dilepas.', 'text-red-600');
                    });
            }

            /* ------------------------------------------------------------ */
            /* Simpan transaksi                                              */
            /* ------------------------------------------------------------ */

            function tampilkanPesan(teks, kelas) {
                var kotak = el('pesan');
                kotak.className = 'text-xs ' + (kelas || 'text-slate-600');
                kotak.innerHTML = teks || '';
            }

            function muatanTransaksi() {
                return {
                    customer_name: el('pelanggan').value || null,
                    payment_method: el('metode').value,
                    discount: Number(el('diskon').value || 0),
                    paid_amount: Number(el('bayar').value || 0),
                    note: el('catatan').value || null,
                    items: keranjang.map(function (item) {
                        return {
                            product_id: item.id,
                            quantity: Number(item.quantity),
                            unit_price: Number(item.unit_price),
                            discount: 0,
                        };
                    }),
                };
            }

            function bersihkanForm() {
                keranjang = [];
                el('pelanggan').value = '';
                el('diskon').value = 0;
                el('bayar').value = 0;
                el('catatan').value = '';
                el('cari').value = '';
                el('hasil').innerHTML = '';
                el('info-pindai').classList.add('hidden');
                gambarKeranjang();
            }

            function simpanOffline() {
                if (! window.PosOffline) {
                    tampilkanPesan('Mode offline belum siap.', 'text-red-600');

                    return;
                }

                window.PosOffline.antrikanTransaksi(muatanTransaksi()).then(function () {
                    tampilkanPesan('Transaksi disimpan lokal dan akan dikirim otomatis saat jaringan kembali.', 'text-amber-600');
                    bersihkanForm();
                });
            }

            function simpan() {
                if (keranjang.length === 0) {
                    tampilkanPesan('Keranjang masih kosong.', 'text-red-600');

                    return;
                }

                if (! navigator.onLine) {
                    simpanOffline();

                    return;
                }

                tampilkanPesan('Menyimpan transaksi...', 'text-slate-500');

                fetch(rute.checkout, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(muatanTransaksi()),
                })
                    .then(function (respons) {
                        return respons.json().then(function (isi) {
                            return { ok: respons.ok, isi: isi };
                        });
                    })
                    .then(function (hasil) {
                        if (! hasil.ok) {
                            tampilkanPesan(hasil.isi.message || 'Transaksi gagal disimpan.', 'text-red-600');

                            return;
                        }

                        tampilkanPesan('Transaksi ' + amanTeks(hasil.isi.invoice_no) + ' tersimpan. Kembali '
                            + rupiah(hasil.isi.change_amount)
                            + ' &middot; <a class="font-semibold underline" target="_blank" href="'
                            + hasil.isi.receipt_url + '">buka struk</a>', 'text-emerald-600');

                        bersihkanForm();
                    })
                    .catch(function () {
                        simpanOffline();
                    });
            }

            /* ------------------------------------------------------------ */
            /* Panel offline                                                 */
            /* ------------------------------------------------------------ */

            function gambarStatus(online, antrian, barcode) {
                el('status-jaringan').textContent = online ? 'Online' : 'Offline';
                el('status-jaringan').className = 'font-medium ' + (online ? 'text-emerald-600' : 'text-amber-600');

                if (antrian !== undefined && antrian !== null) {
                    el('jumlah-antrian').textContent = antrian;
                }

                if (barcode !== undefined && barcode !== null) {
                    el('jumlah-antrian-barcode').textContent = barcode;
                }
            }

            function perbaruiInfoKatalog() {
                if (! window.PosOffline) {
                    return;
                }

                window.PosOffline.infoKatalog().then(function (info) {
                    if (! info) {
                        el('info-katalog').textContent = 'Katalog offline belum diunduh.';

                        return;
                    }

                    var waktu = new Date(info.nilai);
                    el('info-katalog').textContent = info.jumlah + ' barang tersimpan untuk mode offline ('
                        + waktu.toLocaleString('id-ID') + ').';
                });
            }

            /* ------------------------------------------------------------ */
            /* Pemasangan tombol & pintasan                                  */
            /* ------------------------------------------------------------ */

            el('tombol-pindai').addEventListener('click', function () {
                bukaPemindai(false);
            });

            el('tombol-daftar').addEventListener('click', function () {
                bukaPanelBarcode(bersihkanKode(el('cari').value));
            });

            el('bc-pindai').addEventListener('click', function () {
                bukaPemindai(true);
            });

            el('bc-simpan').addEventListener('click', simpanBarcode);
            el('bc-batal').addEventListener('click', tutupPanelBarcode);
            el('bc-bersih').addEventListener('click', bersihkanPanelBarcode);

            el('bc-nama').addEventListener('input', function () {
                produkBarcode = null;
                el('bc-terpilih').classList.add('hidden');

                if (jedaSaran) {
                    clearTimeout(jedaSaran);
                }

                var nama = el('bc-nama').value;
                jedaSaran = setTimeout(function () {
                    cariSaran(nama);
                }, 250);
            });

            el('bc-kode').addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    el('bc-nama').focus();
                }
            });

            el('cari').addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    cari(el('cari').value);
                }
            });

            var jedaCari = null;
            el('cari').addEventListener('input', function () {
                var kata = el('cari').value;

                if (jedaCari) {
                    clearTimeout(jedaCari);
                }

                jedaCari = setTimeout(function () {
                    if (kata.trim().length >= 2 && ! sepertiBarcode(kata.trim())) {
                        cari(kata);
                    }
                }, 300);
            });

            el('diskon').addEventListener('input', hitung);
            el('bayar').addEventListener('input', hitung);
            el('simpan').addEventListener('click', simpan);
            el('kosongkan').addEventListener('click', bersihkanForm);

            el('kirim-antrian').addEventListener('click', function () {
                if (window.PosOffline) {
                    window.PosOffline.kirimAntrian();
                }
            });

            el('kirim-antrian-barcode').addEventListener('click', function () {
                if (window.PosOffline) {
                    window.PosOffline.kirimAntrianBarcode();
                }
            });

            el('segarkan-katalog').addEventListener('click', function () {
                if (window.PosOffline) {
                    window.PosOffline.segarkanKatalog().then(perbaruiInfoKatalog);
                }
            });

            // Alat pemindai USB: ketikan cepat + Enter langsung diproses,
            // walau kursor tidak berada di kolom pencarian.
            if (window.BarcodeScanner) {
                window.BarcodeScanner.pantauAlatPindai({ onDeteksi: tanganiPindai });
            }

            // Jalan pintas papan tombol tetap ada sebagai pelengkap (opsional).
            document.addEventListener('keydown', function (event) {
                if (event.key === 'F3') {
                    event.preventDefault();
                    bukaPemindai(false);
                }

                if (event.key === 'F2') {
                    event.preventDefault();
                    simpan();
                }

                if (event.key === 'F4') {
                    event.preventDefault();
                    bukaPanelBarcode('');
                }

                if (event.key === 'Escape') {
                    tutupPanelBarcode();
                }
            });

            window.addEventListener('pos-offline:status', function (event) {
                gambarStatus(event.detail.online, event.detail.antrian, event.detail.barcode);
            });

            window.addEventListener('pos-offline:antrian', function (event) {
                gambarStatus(navigator.onLine, event.detail.jumlah, null);
            });

            window.addEventListener('pos-offline:antrian-barcode', function (event) {
                gambarStatus(navigator.onLine, null, event.detail.jumlah);
            });

            window.addEventListener('pos-offline:katalog', perbaruiInfoKatalog);

            window.addEventListener('pos-offline:sinkron-selesai', function (event) {
                tampilkanPesan(event.detail.dikirim + ' transaksi terkirim, ' + event.detail.gagal + ' perlu ditinjau.',
                    event.detail.gagal ? 'text-amber-600' : 'text-emerald-600');
            });

            window.addEventListener('pos-offline:barcode-selesai', function (event) {
                pesanBarcode(event.detail.dikirim + ' barcode terkirim ke server, ' + event.detail.gagal + ' gagal.',
                    event.detail.gagal ? 'text-amber-600' : 'text-emerald-600');
            });

            gambarStatus(navigator.onLine, 0, 0);
            gambarKeranjang();
            perbaruiInfoKatalog();
            el('cari').focus();
        })();
    </script>
@endsection
