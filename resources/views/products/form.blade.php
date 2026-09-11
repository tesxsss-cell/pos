@extends('layouts.app')

@section('title', $product->exists ? 'Ubah Barang' : 'Tambah Barang')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
        <h1 class="text-lg font-semibold text-slate-900">{{ $product->exists ? 'Ubah data barang' : 'Tambah barang baru' }}</h1>

        <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="mt-6 space-y-4">
            @csrf
            @if ($product->exists)
                @method('PUT')
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">SKU</label>
                    <input name="sku" value="{{ old('sku', $product->sku) }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Barcode / QR</label>
                    {{-- Tombol pindai langsung bisa diklik (tanpa jalan pintas papan tombol). --}}
                    <div class="mt-1 flex gap-2">
                        <input id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}"
                               class="w-full rounded-md border border-slate-300 px-3 py-2">
                        <button type="button" data-pindai data-pindai-target="#barcode" data-pindai-sekali
                                class="whitespace-nowrap rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Pindai
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Barcode tambahan (kemasan lain) bisa didaftarkan kasir langsung dari halaman kasir.
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Nama barang</label>
                <input name="name" value="{{ old('name', $product->name) }}" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Kategori</label>
                    <select name="category_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <option value="">Tanpa kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Satuan</label>
                    <input name="unit" value="{{ old('unit', $product->unit ?? 'pcs') }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Stok minimum</label>
                    <input name="min_stock" type="number" min="0" value="{{ old('min_stock', $product->min_stock ?? 0) }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Harga jual (Rp)</label>
                <input name="sell_price" type="number" min="0" step="0.01" value="{{ old('sell_price', $product->sell_price ?? 0) }}" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                <p class="mt-1 text-xs text-slate-500">Harga beli tidak diisi di sini &mdash; harga beli mengikuti tiap batch penerimaan barang (FIFO).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Keterangan</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">{{ old('description', $product->description) }}</textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="rounded border-slate-300">
                Barang aktif dan dapat dijual
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button class="rounded-md bg-slate-900 px-4 py-2 font-medium text-white">Simpan</button>
                <a href="{{ route('products.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </form>

        @if ($product->exists && $product->barcodes->isNotEmpty())
            <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <h2 class="text-sm font-semibold text-slate-800">Barcode terdaftar</h2>
                <ul class="mt-2 space-y-1 text-xs text-slate-600">
                    @foreach ($product->barcodes as $barcode)
                        <li>
                            {{ $barcode->barcode }} &middot;
                            @if ($barcode->sell_price === null)
                                ikut harga master
                            @else
                                Rp {{ number_format((float) $barcode->sell_price, 0, ',', '.') }}
                            @endif
                            &middot; {{ $barcode->default_quantity }} {{ $product->unit }} per pindai
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Modul pemindai kamera offline (tanpa html5-qrcode) dipakai tombol "Pindai" di atas. --}}
    <script src="{{ asset('js/offline-barcode.js') }}"></script>
    <script src="{{ asset('js/barcode-scanner.js') }}"></script>
@endsection
