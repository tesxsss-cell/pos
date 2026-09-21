@extends('layouts.app')

@section('title', 'Kategori Barang')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-slate-900">Kategori Barang</h1>
        <a href="{{ route('categories.create') }}" class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Tambah kategori</a>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama kategori</th>
                    <th class="px-4 py-3">Keterangan</th>
                    <th class="px-4 py-3 text-right">Jumlah barang</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr class="{{ $category->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $category->code }}</td>
                        <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $category->description ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">{{ $category->products_count }}</td>
                        <td class="px-4 py-3">
                            @if ($category->is_active)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">aktif</span>
                            @else
                                <span class="rounded bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('categories.edit', $category) }}" class="text-slate-700 hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('categories.destroy', $category) }}"
                                      onsubmit="return confirm('Hapus kategori ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada kategori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $categories->links() }}</div>
@endsection
