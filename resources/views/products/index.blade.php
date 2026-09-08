@extends('layouts.app')

@section('title', 'Data Barang')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-slate-900">Data Barang</h1>
        <a href="{{ route('products.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Tambah barang</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Cari nama / SKU / barcode</label>
            <input name="q" value="{{ request('q') }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Kategori</label>
            <select name="kategori" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('kategori') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Tampilkan stok di</label>
            <select name="cabang" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua lokasi</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">SKU / Barcode</th>
                    <th class="px-4 py-3">Nama barang</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-right">Harga jual</th>
                    <th class="px-4 py-3 text-right">Stok</th>
                    <th class="px-4 py-3 text-right">Min</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    @php($stok = (int) $product->stocks->sum('quantity'))
                    <tr class="{{ $product->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $product->sku }}</div>
                            <div class="text-xs text-slate-400">{{ $product->barcode ?? 'tanpa barcode' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $product->name }}</td>
                        <td class="px-4 py-3">{{ $product->category->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $product->sell_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="{{ $stok <= $product->min_stock ? 'rounded bg-red-100 px-2 py-0.5 font-medium text-red-700' : '' }}">
                                {{ $stok }} {{ $product->unit }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ $product->min_stock }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('products.edit', $product) }}" class="text-slate-700 hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('products.destroy', $product) }}"
                                      onsubmit="return confirm('Arsipkan barang ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Arsip</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada data barang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
