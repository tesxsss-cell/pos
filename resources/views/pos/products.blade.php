@extends('layouts.app')
@section('title', 'Daftarkan Barcode')
@section('content')
    <div class="cashier-products">
        <section class="page-heading">
            <div><span class="eyebrow">RUANG KASIR</span>
                <h2>Daftarkan barcode barang</h2>
                <p>Pilih barang, buka kamera, lalu simpan kode yang terbaca. Nama, harga, dan stok mengikuti master
                    Pengelola.</p>
            </div><a class="btn-secondary" href="{{ route('pos.index') }}">Ke kasir</a>
        </section>
        <div id="register-feedback" class="pos-feedback" role="status" aria-live="polite" hidden></div>
        <form method="GET" action="{{ route('pos.products') }}" class="panel barcode-filter"><label
                for="barcode-search">Cari nama / SKU / barcode<input id="barcode-search" name="q" class="field"
                    value="{{ $q }}" maxlength="150" autocomplete="off"
                    placeholder="Ketik nama barang, SKU, atau barcode"></label><button class="btn-primary">Cari</button>
            @if ($q !== '')
                <a class="text-link" href="{{ route('pos.products') }}">Reset</a>
            @endif
        </form>
        @php(
    $productData = $products->getCollection()->mapWithKeys(
        fn($p) => [
            $p->id => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'price' => (float) $p->sell_price,
                'stock' => (int) ($p->stocks->first()?->quantity ?? 0),
                'codes' => collect([$p->barcode])->merge($p->barcodes->pluck('barcode'))->filter()->unique()->values()->all()
            ]
        ]
    )
)
        <div class="barcode-mobile-list">
            @forelse($productData as $p)
                <article class="panel barcode-product-card">
                    <div class="barcode-card-heading">
                        <div>
                            <h3>{{ $p['name'] }}</h3>
                            <p>{{ $p['sku'] }} · {{ $p['unit'] }}</p>
                        </div><strong>Rp {{ number_format($p['price'], 0, ',', '.') }}</strong>
                    </div>
                    <div class="barcode-card-details">
                        <div><span>Stok</span>
                            <p>{{ $p['stock'] }} {{ $p['unit'] }}</p>
                        </div>
                        <div><span>Barcode terdaftar</span>
                            <div data-code-list="{{ $p['id'] }}" class="barcode-codes">
                                @forelse($p['codes'] as $code)
                                <span>{{ $code }}</span>@empty<span class="barcode-empty">Belum ada</span>
                                @endforelse
                            </div>
                        </div>
                    </div><button type="button" class="btn-primary barcode-open"
                        data-product="{{ $p['id'] }}">{{ count($p['codes']) ? 'Tambah barcode' : 'Daftarkan barcode' }}</button>
                </article>
            @empty<div class="panel barcode-empty-state">Tidak ada barang aktif yang sesuai pencarian.</div>
            @endforelse
        </div>
        <div class="barcode-desktop-table panel">
            <div class="overflow-x-auto">
                <table data-server-table>
                    <thead>
                        <tr>
                            <th>Nama barang</th>
                            <th>SKU</th>
                            <th class="text-right">Harga jual</th>
                            <th class="text-right">Stok</th>
                            <th>Barcode terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productData as $p)
                            <tr>
                                <td>{{ $p['name'] }}<p class="text-sm text-slate-500">{{ $p['unit'] }}</p>
                                </td>
                                <td>{{ $p['sku'] }}</td>
                                <td class="text-right">Rp {{ number_format($p['price'], 0, ',', '.') }}</td>
                                <td class="text-right">{{ $p['stock'] }} {{ $p['unit'] }}</td>
                                <td>
                                    <div class="barcode-codes" data-code-list="{{ $p['id'] }}">
                                        @forelse($p['codes'] as $code)
                                        <span>{{ $code }}</span>@empty<span class="barcode-empty">Belum
                                                ada</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td><button type="button" class="btn-secondary barcode-open"
                                        data-product="{{ $p['id'] }}">{{ count($p['codes']) ? 'Tambah barcode' : 'Daftarkan barcode' }}</button>
                                </td>
                        </tr>@empty<tr>
                                <td colspan="6">Tidak ada barang aktif yang sesuai pencarian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $products->links() }}
        <p class="barcode-info">Satu barang boleh memiliki beberapa barcode. Pendaftaran menambah kode tanpa menghapus
            barcode lama. Harga jual tidak dapat diubah kasir.</p>
    </div>
    <dialog id="barcode-dialog" class="barcode-dialog" aria-labelledby="barcode-dialog-title"
        aria-describedby="barcode-dialog-help">
        <div class="barcode-dialog-content">
            <header class="barcode-dialog-header">
                <div>
                    <h2 id="barcode-dialog-title">Daftarkan barcode</h2>
                    <p id="barcode-dialog-help">Arahkan barcode ke kamera. Kode akan terisi otomatis, lalu periksa sebelum
                        disimpan.</p>
                </div><button type="button" id="barcode-dialog-close" class="icon-button"
                    aria-label="Tutup pendaftaran barcode">×</button>
            </header>
            <div id="registration-scanner" class="registration-scanner"></div>
            <p id="registration-camera-status" class="barcode-info" role="status" aria-live="polite">Kamera belum aktif.
            </p>
            <p class="barcode-info">Di HP, gunakan HTTPS dan izinkan kamera. Pilih kamera belakang jika tersedia. Jika
                kamera tidak tersedia, ketik atau tembak kode dengan scanner USB.</p>
            <form id="barcode-registration-form"><label for="registration-code">Kode barcode<input id="registration-code"
                        class="field" maxlength="60" autocomplete="off" required pattern="[A-Za-z0-9._:\/\-]+"
                        title="Gunakan huruf, angka, titik, garis bawah, titik dua, garis miring, atau tanda minus."></label>
                <dl class="registration-product-info">
                    <div>
                        <dt>Barang</dt>
                        <dd id="registration-product-name"></dd>
                    </div>
                    <div>
                        <dt>SKU</dt>
                        <dd id="registration-product-sku"></dd>
                    </div>
                    <div>
                        <dt>Harga jual</dt>
                        <dd id="registration-product-price"></dd>
                    </div>
                    <div>
                        <dt>Stok</dt>
                        <dd id="registration-product-stock"></dd>
                    </div>
                </dl>
                <p id="registration-message" role="status" aria-live="polite"></p>
                <div class="registration-actions"><button id="registration-save" class="btn-primary" type="submit">Simpan
                        barcode</button><button id="registration-cancel" class="btn-secondary" type="button">Batal</button>
                </div>
            </form>
        </div>
    </dialog>
@endsection
@push('scripts')
    <script>
        window.BARCODE_PAGE_CONFIG = {
            products: @json($productData),
            saveUrl: @json(route('pos.barcode.store')),
            csrf: @json(csrf_token()),
            initialCode: @json($initialCode)
        };
    </script>
    <script src="{{ asset('js/barcode-decoder.js') }}"></script>
    <script src="{{ asset('js/barcode-scanner.js') }}"></script>
    <script src="{{ asset('js/barcode-register.js') }}"></script>
@endpush
