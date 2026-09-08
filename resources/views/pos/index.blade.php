@extends('layouts.app')

@section('title', 'Kasir')

@section('content')
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="lg:col-span-2 space-y-4">
            <div class="rounded-xl bg-white p-4 shadow">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 class="font-semibold text-slate-900">Transaksi Penjualan &middot; {{ $branch->name }}</h1>
                        <p class="text-xs text-slate-500">Pindai barcode/QR dengan alat pemindai (mode keyboard), kamera perangkat, atau ketik nama/SKU lalu tekan Enter.</p>
                    </div>
                    @if ($shift)
                        <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Shift dibuka {{ $shift->opened_at?->format('H:i') }}</span>
                    @else
                        <span class="rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">Shift belum dibuka</span>
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <input id="cari" type="text" autofocus autocomplete="off" placeholder="Pindai barcode atau cari barang..."
                           class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
                    <button type="button" id="tombol-pindai"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        Pindai kamera (F3)
                    </button>
                </div>
                <div id="hasil" class="mt-2 hidden max-h-56 overflow-y-auto rounded-md border border-slate-200 text-sm"></div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2">Barang</th>
                            <th class="py-2 w-24 text-center">Qty</th>
                            <th class="py-2 w-32 text-right">Harga</th>
                            <th class="py-2 w-32 text-right">Subtotal</th>
                            <th class="py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody id="keranjang" class="divide-y divide-slate-100"></tbody>
                </table>
                <p id="kosong" class="py-6 text-center text-sm text-slate-400">Keranjang masih kosong.</p>
            </div>

            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="font-semibold text-slate-900">Transaksi terakhir saya</h2>
                <table class="mt-2 w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentSales as $sale)
                            <tr>
                                <td class="py-2">
                                    <a class="font-medium text-slate-800 hover:underline" href="{{ route('pos.receipt', $sale) }}" target="_blank">{{ $sale->invoice_no }}</a>
                                    <div class="text-xs text-slate-400">{{ $sale->sold_at?->format('d M Y H:i') }} &middot; {{ $sale->payment_method->label() }}</div>
                                </td>
                                <td class="py-2 text-right">Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</td>
                                <td class="py-2 text-right">
                                    @if ($sale->isCompleted())
                                        <form method="POST" action="{{ route('pos.void', $sale) }}" class="flex items-center justify-end gap-1"
                                              onsubmit="return confirm('Batalkan transaksi ini? Stok akan dikembalikan.')">
                                            @csrf
                                            <input name="reason" required placeholder="alasan" class="w-28 rounded border border-slate-300 px-2 py-1 text-xs">
                                            <button class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white">Batal</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-red-600">dibatalkan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-4 text-center text-sm text-slate-400">Belum ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-slate-900">Status jaringan</h2>
                    <span id="status-jaringan" class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">memeriksa...</span>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    Transaksi yang dibuat saat jaringan mati disimpan di perangkat, lalu terkirim otomatis ketika koneksi pulih.
                </p>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Antrian belum terkirim</dt>
                        <dd id="jumlah-antrian" class="font-semibold">0</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Katalog offline</dt>
                        <dd id="info-katalog" class="text-slate-600">-</dd>
                    </div>
                </dl>
                <div class="mt-3 flex gap-2">
                    <button type="button" id="kirim-antrian" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white">Kirim antrian sekarang</button>
                    <button type="button" id="segarkan-katalog" class="flex-1 rounded-md bg-slate-200 px-3 py-2 text-xs font-medium text-slate-700">Perbarui katalog</button>
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="font-semibold text-slate-900">Pembayaran</h2>

                <label class="mt-3 block text-xs font-medium text-slate-500">Nama pelanggan (opsional)</label>
                <input id="pelanggan" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">

                <label class="mt-3 block text-xs font-medium text-slate-500">Metode pembayaran</label>
                <select id="metode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <label class="mt-3 block text-xs font-medium text-slate-500">Diskon transaksi (Rp)</label>
                <input id="diskon" type="number" min="0" value="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">

                <label class="mt-3 block text-xs font-medium text-slate-500">Uang dibayar (Rp)</label>
                <input id="bayar" type="number" min="0" value="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">

                <dl class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd id="v-subtotal">Rp 0</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Total</dt><dd id="v-total" class="font-semibold">Rp 0</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Kembalian</dt><dd id="v-kembali">Rp 0</dd></div>
                </dl>

                <p id="pesan" class="mt-3 hidden rounded-md px-3 py-2 text-sm"></p>

                <button id="simpan" class="mt-3 w-full rounded-md bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500 disabled:opacity-50">
                    Simpan transaksi (F2)
                </button>
            </div>

            <div class="rounded-xl bg-white p-4 shadow">
                <h2 class="font-semibold text-slate-900">Shift kasir</h2>
                @if ($shift)
                    <p class="mt-2 text-sm text-slate-500">
                        Kas awal Rp {{ number_format((float) $shift->opening_cash, 0, ',', '.') }} &middot;
                        perkiraan kas Rp {{ number_format((float) $shift->expected_cash, 0, ',', '.') }}
                    </p>
                    <form method="POST" action="{{ route('pos.shift.close') }}" class="mt-3 space-y-2">
                        @csrf
                        <input name="actual_cash" type="number" min="0" step="0.01" required placeholder="Uang fisik di laci"
                               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <input name="note" placeholder="Catatan (opsional)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Tutup shift</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('pos.shift.open') }}" class="mt-3 space-y-2">
                        @csrf
                        <input name="opening_cash" type="number" min="0" step="0.01" required placeholder="Kas awal (Rp)"
                               class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Buka shift</button>
                    </form>
                @endif
            </div>
        </section>
    </div>

    <script>
        // Konfigurasi mode offline dibaca oleh public/js/pos-offline.js
        window.POS_OFFLINE_CONFIG = {
            katalog: '{{ route('pos.catalog') }}',
            sinkronisasi: '{{ route('pos.sync') }}',
            csrf: '{{ csrf_token() }}',
        };
    </script>
    <script src="{{ asset('js/pos-offline.js') }}"></script>
    <script src="{{ asset('js/barcode-scanner.js') }}"></script>

    <script>
        const rute = {
            cari: '{{ route('pos.lookup') }}',
            checkout: '{{ route('pos.checkout') }}',
        };
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const keranjang = [];

        const rupiah = (angka) => 'Rp ' + Number(angka || 0).toLocaleString('id-ID');
        const el = (id) => document.getElementById(id);

        function hitung() {
            const subtotal = keranjang.reduce((total, item) => total + item.quantity * item.unit_price, 0);
            const diskon = Number(el('diskon').value || 0);
            const total = Math.max(subtotal - diskon, 0);
            const bayar = Number(el('bayar').value || 0);

            el('v-subtotal').textContent = rupiah(subtotal);
            el('v-total').textContent = rupiah(total);
            el('v-kembali').textContent = rupiah(Math.max(bayar - total, 0));

            return { subtotal, total };
        }

        function gambarKeranjang() {
            const tbody = el('keranjang');
            tbody.innerHTML = '';
            el('kosong').classList.toggle('hidden', keranjang.length > 0);

            keranjang.forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td class="py-2"><div class="font-medium">' + item.name + '</div><div class="text-xs text-slate-400">' + item.sku + ' &middot; stok ' + item.stock + '</div></td>'
                    + '<td class="py-2 text-center"><input type="number" min="1" value="' + item.quantity + '" data-qty="' + index + '" class="w-16 rounded border border-slate-300 px-2 py-1 text-center"></td>'
                    + '<td class="py-2 text-right"><input type="number" min="0" value="' + item.unit_price + '" data-harga="' + index + '" class="w-28 rounded border border-slate-300 px-2 py-1 text-right"></td>'
                    + '<td class="py-2 text-right">' + rupiah(item.quantity * item.unit_price) + '</td>'
                    + '<td class="py-2 text-right"><button data-hapus="' + index + '" class="text-red-600">&times;</button></td>';
                tbody.appendChild(tr);
            });

            hitung();
        }

        function tambah(produk) {
            const ada = keranjang.find((item) => item.id === produk.id);

            if (ada) {
                ada.quantity += 1;
            } else {
                keranjang.push({ ...produk, quantity: 1 });
            }

            el('hasil').classList.add('hidden');
            el('cari').value = '';
            gambarKeranjang();
        }

        function tampilkanHasil(daftar, kataKunci, dariLokal) {
            const kotak = el('hasil');

            if (! daftar || daftar.length === 0) {
                kotak.innerHTML = '<p class="px-3 py-2 text-slate-500">Barang tidak ditemukan'
                    + (dariLokal ? ' pada katalog offline.' : '.') + '</p>';
                kotak.classList.remove('hidden');
                return;
            }

            // Barcode unik -> langsung masuk keranjang.
            if (daftar.length === 1 && daftar[0].barcode === kataKunci) {
                tambah(daftar[0]);
                return;
            }

            kotak.innerHTML = '';
            daftar.forEach((produk) => {
                const baris = document.createElement('button');
                baris.type = 'button';
                baris.className = 'flex w-full items-center justify-between px-3 py-2 text-left hover:bg-slate-50';
                baris.innerHTML = '<span>' + produk.name + '<span class="ml-2 text-xs text-slate-400">' + produk.sku + ' &middot; stok ' + produk.stock
                    + (dariLokal ? ' &middot; katalog offline' : '') + '</span></span><span>' + rupiah(produk.sell_price) + '</span>';
                baris.addEventListener('click', () => tambah(produk));
                kotak.appendChild(baris);
            });
            kotak.classList.remove('hidden');
        }

        async function cariLokal(kataKunci) {
            const daftar = await window.PosOffline.cariKatalogLokal(kataKunci);
            tampilkanHasil(daftar, kataKunci, true);
        }

        async function cari(kataKunci) {
            if (! navigator.onLine) {
                return cariLokal(kataKunci);
            }

            try {
                const respons = await fetch(rute.cari + '?q=' + encodeURIComponent(kataKunci), {
                    headers: { 'Accept': 'application/json' },
                });
                const isi = await respons.json();
                tampilkanHasil(isi.data, kataKunci, false);
            } catch (error) {
                // Jaringan putus di tengah jalan -> pakai katalog yang tersimpan.
                await cariLokal(kataKunci);
            }
        }

        function muatanTransaksi(total) {
            return {
                customer_name: el('pelanggan').value || null,
                payment_method: el('metode').value,
                discount: Number(el('diskon').value || 0),
                paid_amount: Number(el('bayar').value || 0) || total,
                items: keranjang.map((item) => ({
                    product_id: item.id,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                })),
            };
        }

        function bersihkanForm() {
            keranjang.length = 0;
            el('pelanggan').value = '';
            el('diskon').value = 0;
            el('bayar').value = 0;
            gambarKeranjang();
        }

        async function simpanOffline(muatan, alasan) {
            const catatan = await window.PosOffline.antrikanTransaksi(muatan);
            tampilkanPesan(alasan + ' Transaksi disimpan di perangkat (kode ' + catatan.client_uuid.slice(0, 8)
                + ') dan akan dikirim otomatis saat jaringan kembali.', true);
            bersihkanForm();
        }

        async function simpan() {
            const { total } = hitung();

            if (keranjang.length === 0) {
                return tampilkanPesan('Keranjang masih kosong.', false);
            }

            const muatan = muatanTransaksi(total);

            if (! navigator.onLine) {
                return simpanOffline(muatan, 'Jaringan sedang mati.');
            }

            el('simpan').disabled = true;

            try {
                const respons = await fetch(rute.checkout, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(muatan),
                });

                const isi = await respons.json();
                el('simpan').disabled = false;

                if (! respons.ok) {
                    return tampilkanPesan(isi.message || 'Transaksi gagal disimpan.', false);
                }

                tampilkanPesan(isi.invoice_no + ' tersimpan. Kembalian ' + rupiah(isi.change_amount), true);
                window.open(isi.receipt_url, '_blank');
                bersihkanForm();
            } catch (error) {
                el('simpan').disabled = false;
                await simpanOffline(muatan, 'Server tidak dapat dihubungi.');
            }
        }

        function tampilkanPesan(teks, sukses) {
            const kotak = el('pesan');
            kotak.textContent = teks;
            kotak.className = 'mt-3 rounded-md px-3 py-2 text-sm ' + (sukses ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800');
        }

        el('cari').addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target.value.trim() !== '') {
                event.preventDefault();
                cari(event.target.value.trim());
            }
        });

        el('keranjang').addEventListener('input', (event) => {
            const qty = event.target.dataset.qty;
            const harga = event.target.dataset.harga;

            if (qty !== undefined) {
                keranjang[qty].quantity = Math.max(1, Number(event.target.value || 1));
            }

            if (harga !== undefined) {
                keranjang[harga].unit_price = Math.max(0, Number(event.target.value || 0));
            }

            if (qty !== undefined || harga !== undefined) {
                gambarKeranjang();
            }
        });

        el('keranjang').addEventListener('click', (event) => {
            const hapus = event.target.dataset.hapus;

            if (hapus !== undefined) {
                keranjang.splice(hapus, 1);
                gambarKeranjang();
            }
        });

        el('diskon').addEventListener('input', hitung);
        el('bayar').addEventListener('input', hitung);
        el('simpan').addEventListener('click', simpan);

        /* ---------------- Pemindaian barcode lewat kamera ---------------- */

        function bukaPemindai() {
            window.BarcodeScanner.mulai({
                onDeteksi: (kode) => {
                    el('cari').value = kode;
                    cari(kode);
                },
                onGagal: (pesan) => tampilkanPesan(pesan, false),
            });
        }

        el('tombol-pindai').addEventListener('click', bukaPemindai);

        if (! window.BarcodeScanner.didukung) {
            el('tombol-pindai').title = 'Peramban ini belum mendukung kamera pemindai. Gunakan alat pemindai mode keyboard.';
            el('tombol-pindai').classList.add('opacity-60');
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'F2') {
                event.preventDefault();
                simpan();
            }

            if (event.key === 'F3') {
                event.preventDefault();
                bukaPemindai();
            }
        });

        /* ---------------- Panel status offline ---------------- */

        function gambarStatus(online, antrian) {
            const lencana = el('status-jaringan');
            lencana.textContent = online ? 'Online' : 'Offline';
            lencana.className = 'rounded px-2 py-1 text-xs font-medium '
                + (online ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700');

            if (antrian !== undefined) {
                el('jumlah-antrian').textContent = antrian;
            }
        }

        function perbaruiInfoKatalog() {
            window.PosOffline.infoKatalog().then((info) => {
                el('info-katalog').textContent = info
                    ? info.jumlah + ' barang (' + new Date(info.nilai).toLocaleString('id-ID') + ')'
                    : 'belum tersimpan';
            });
        }

        window.addEventListener('pos-offline:status', (event) => gambarStatus(event.detail.online, event.detail.antrian));
        window.addEventListener('pos-offline:antrian', (event) => gambarStatus(navigator.onLine, event.detail.jumlah));
        window.addEventListener('pos-offline:katalog', perbaruiInfoKatalog);
        window.addEventListener('online', () => gambarStatus(true));
        window.addEventListener('offline', () => gambarStatus(false));

        window.addEventListener('pos-offline:sinkron-selesai', (event) => {
            const bagian = [];

            if (event.detail.dikirim) {
                bagian.push(event.detail.dikirim + ' transaksi offline berhasil dikirim ke server');
            }

            if (event.detail.gagal) {
                bagian.push(event.detail.gagal + ' transaksi gagal (cek stok / periksa manual)');
            }

            if (bagian.length > 0) {
                tampilkanPesan(bagian.join(' & ') + '.', event.detail.gagal === 0);
            }
        });

        el('kirim-antrian').addEventListener('click', () => {
            if (! navigator.onLine) {
                return tampilkanPesan('Masih offline. Antrian akan dikirim otomatis setelah jaringan pulih.', false);
            }

            window.PosOffline.kirimAntrian();
        });

        el('segarkan-katalog').addEventListener('click', () => {
            window.PosOffline.segarkanKatalog().then((jumlah) => {
                tampilkanPesan(jumlah > 0 ? 'Katalog offline diperbarui (' + jumlah + ' barang).' : 'Katalog gagal diperbarui.', jumlah > 0);
                perbaruiInfoKatalog();
            });
        });

        gambarStatus(navigator.onLine);
        perbaruiInfoKatalog();
        gambarKeranjang();
    </script>
@endsection
