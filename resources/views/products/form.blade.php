@extends('layouts.app')
@section('title', $product->exists ? 'Ubah Barang' : 'Tambah Barang')
@section('content')
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}"
        class="mx-auto max-w-2xl space-y-4 rounded-xl bg-white p-6 shadow">
        @csrf @if ($product->exists)
            @method('PUT')
        @endif
        <p class="text-sm text-slate-500">Satu barcode hanya untuk satu barang. Barcode tambahan dapat ditautkan melalui
            halaman Kasir. Stok bertambah melalui penerimaan barang, bukan formulir ini. Barang kemasan/jumlah bulat.</p>
        @foreach ([['sku', 'Kode / SKU', 40], ['name', 'Nama barang', 150], ['barcode', 'Barcode (opsional)', 50], ['unit', 'Satuan, misalnya pak/botol/pcs', 20]] as [$name, $label, $max])
            <label class="block">{{ $label }}<input name="{{ $name }}" value="{{ old($name, $product->$name) }}"
                    maxlength="{{ $max }}" class="field" {{ $name === 'barcode' ? '' : 'required' }}></label>
        @endforeach
        <label class="block">Kategori<select name="category_id" class="field" required>
                <option value="">Pilih kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select></label>
        <label class="block">Harga jual (Rp)<input class="field" type="number" name="sell_price" min="0"
                max="999999999" step="0.01" value="{{ old('sell_price', $product->sell_price ?? 0) }}" required></label>
        <label class="block">Stok minimum<input class="field" type="number" name="min_stock" min="0"
                value="{{ old('min_stock', $product->min_stock ?? 0) }}" required></label>
        <label class="block">Keterangan
            <textarea name="description" class="field" maxlength="500">{{ old('description', $product->description) }}</textarea>
        </label>
        <label class="block"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $product->is_active ?? true))> Barang
            aktif/dapat dijual</label>
        <button class="btn-primary">Simpan</button><a class="ml-3 underline" href="{{ route('products.index') }}">Batal</a>
    </form>
@endsection
