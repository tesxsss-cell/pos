@extends('layouts.app')
@section('title', 'Kasir & Shift')
@section('content')
    <div class="cashier-original">
        <section class="page-heading">
            <div><span class="eyebrow">RUANG KASIR</span>
                <h2>Siap melayani pelanggan.</h2>
                <p>Cari barang, periksa pembayaran, lalu simpan transaksi.</p>
            </div><span class="state-tag {{ $shift ? 'is-open' : '' }}"><x-icon
                    name="clock" />{{ $shift ? 'Shift #' . $shift->id . ' berlangsung' : 'Shift belum dibuka' }}</span>
        </section>
        <div class="online-note">Transaksi tersimpan setelah server mengonfirmasi. Harga ditetapkan Pengelola; aplikasi ini
            tidak memiliki mode offline.</div>
        <div id="pos-message" class="pos-feedback" role="status" aria-live="polite">
            {{ $shift ? 'Shift aktif. Siap melayani.' : 'Buka shift terlebih dahulu.' }}</div>
        @if (!$shift)
            <section class="panel open-shift-card"><span class="empty-icon"><x-icon name="clock" /></span>
                <h2 class="text-lg font-semibold">Mulai shift Anda</h2>
                <p class="my-3 text-sm text-slate-500">Masukkan uang awal yang benar-benar ada di laci. Sistem tidak membuka
                    shift otomatis.</p>
                <form action="{{ route('pos.shift.open') }}" method="POST">@csrf<label>Kas awal (Rp)<input class="field"
                            name="opening_cash" type="number" min="0" max="999999999999" step="0.01"
                            value="0" required></label><button class="btn-primary mt-4">Buka shift</button></form>
            </section>
        @else
            <div class="pos-layout pos-single-column">
                <section class="panel pos-catalog-panel">
                    <div class="scanner-card">
                        <div class="panel-heading">
                            <div>
                                <h3>Scanner barcode</h3>
                                <p id="scanner-status" role="status">Aktifkan kamera atau gunakan scanner USB.</p>
                            </div><span class="state-tag" id="scanner-state">Nonaktif</span>
                        </div>
                        <div id="scanner-kasir" class="camera-container"></div>
                        <div class="scanner-buttons"><button type="button" id="camera-start" class="btn-primary">Aktifkan
                                kamera</button><button type="button" id="camera-stop" class="btn-secondary"
                                disabled>Nonaktifkan</button></div>
                        <p class="scanner-help">Kamera HP memerlukan HTTPS dan izin kamera. Pencarian manual dan scanner USB
                            tetap tersedia.</p>
                    </div>
                    <div class="panel-heading">
                        <div>
                            <h3>Barang &amp; keranjang</h3>
                            <p>Scan barcode USB atau cari nama dan SKU.</p>
                        </div><x-icon name="box" />
                    </div>
                    <form id="search-form" class="pos-search"><label class="sr-only" for="product-search">Cari nama barang,
                            SKU, atau barcode</label><input id="product-search" class="field-search"
                            placeholder="Scan lalu Enter, atau ketik nama/SKU" autocomplete="off" maxlength="150" autofocus
                            required><button class="btn-primary" type="submit"><x-icon
                                name="search" /><span>Cari</span></button></form>
                    <div id="search-results" class="mb-4 space-y-2"></div>
                    <button type="button" id="cart-toggle" class="cart-toggle" aria-expanded="false"
                        aria-controls="cart-detail"><strong>Keranjang <span id="cart-count">0 barang</span></strong><span
                            id="cart-toggle-label">Lihat detail</span></button>
                    <div id="cart-detail" hidden><button type="button" id="clear-cart" class="text-link">Kosongkan
                            keranjang</button>
                        <div class="table-scroll cart-region">
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th>Harga</th>
                                        <th>Jumlah</th>
                                        <th>Subtotal</th>
                                        <th><span class="sr-only">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody id="cart-body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="cart-total-block"><span>Total belanja</span>
                        <p id="cart-total" class="pos-total">Rp 0</p>
                    </div>
                </section>
                <aside class="pos-payment-column">
                    <section class="panel payment-panel" id="payment-panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Pembayaran</h3>
                                <p>Periksa barang dan nominal. Transaksi tersimpan tidak dapat dibatalkan.</p>
                            </div><x-icon name="calculator" />
                        </div>
                        <form id="checkout-form" class="space-y-3">
                            <label class="block">Nama pelanggan (opsional)<input id="customer-name" class="field"
                                    maxlength="100"></label>
                            <label class="block">Metode pembayaran<select id="payment-method" class="field">
                                    <option value="tunai">Tunai</option>
                                    <option value="qris">QRIS (verifikasi manual)</option>
                                    <option value="transfer">Transfer (verifikasi manual)</option>
                                    <option value="debit">Debit (verifikasi manual)</option>
                                </select></label>
                            <label id="cash-field" class="block">Uang diterima (Rp)<input id="paid-amount" class="field"
                                    type="number" min="0" max="999999999999" step="0.01" required></label>
                            <label id="noncash-field" class="hidden text-sm"><input id="payment-confirmed"
                                    type="checkbox"> Pembayaran non-tunai sudah benar-benar diterima dan diperiksa.</label>
                            <label class="block">Catatan (opsional)<input id="sale-note" class="field"
                                    maxlength="255"></label>
                            <div class="payment-change"><span>Perkiraan kembalian</span><strong id="change-preview">Rp
                                    0</strong></div>
                            <button id="checkout-button" type="submit" class="btn-primary-lg">Simpan transaksi <x-icon
                                    name="check" /></button>
                        </form>
                        <button id="retry-button" class="btn-primary mt-3 hidden" type="button">Periksa / kirim ulang
                            permintaan yang sama</button>
                        <p class="mt-3 text-xs text-slate-500">Jika respons terputus, gunakan tombol periksa/kirim ulang.
                            Jangan membuat transaksi baru atau menutup halaman sebelum status jelas.</p>
                    </section>
                    <section class="panel shift-panel" id="shift-panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Kas shift #{{ $shift->id }}</h3>
                                <p>Hitung uang fisik sebelum menutup.</p>
                            </div><x-icon name="clock" />
                        </div>
                        <p class="my-2 text-sm">Kas awal: Rp
                            {{ number_format((float) $shift->opening_cash, 0, ',', '.') }}<br>Kas sistem: <span
                                id="shift-expected">Rp {{ number_format((float) $shift->expected_cash, 0, ',', '.') }}</span>
                        </p>
                        <a href="{{ route('pos.index') }}" class="text-sm text-brand-700 underline"
                            id="refresh-shift">Segarkan data shift</a>
                        <form id="close-shift-form" method="POST" action="{{ route('pos.shift.close') }}"
                            class="mt-3 space-y-3">@csrf
                            <label class="block">Kas fisik saat tutup (Rp)<input name="actual_cash" class="field"
                                    type="number" min="0" max="999999999999" step="0.01" required></label>
                            <label class="block">Catatan selisih<input name="note" class="field"
                                    maxlength="255"></label><button class="btn-secondary w-full"><x-icon
                                    name="clock" /> Tutup shift</button>
                        </form>
                        <p class="mt-3 text-xs text-slate-500">Setiap akun memakai laci kas terpisah. Pastikan seluruh
                            transaksi sudah dikonfirmasi server sebelum shift ditutup.</p>
                    </section>
                </aside>
            </div>
        @endif

    </div>
@endsection
@if ($shift)
    @push('scripts')
        <script>
            window.POS_ONLINE_CONFIG = {
                lookup: @json(route('pos.lookup')),
                registerPage: @json(route('pos.products')),
                history: @json(route('pos.history')),
                checkout: @json(route('pos.checkout')),
                shift: {{ $shift->id }},
                csrf: @json(csrf_token())
            };
        </script>
        <script src="{{ asset('js/barcode-decoder.js') }}"></script>
        <script src="{{ asset('js/barcode-scanner.js') }}"></script>
        <script src="{{ asset('js/pos-online.js') }}"></script>
    @endpush
@endif
