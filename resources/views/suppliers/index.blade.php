@extends('layouts.app')

@section('title', 'Data Pemasok')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-slate-900">Data Pemasok</h1>
        <a href="{{ route('suppliers.create') }}" class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Tambah pemasok</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Cari nama pemasok</label>
            <input name="q" value="{{ request('q') }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Cari</button>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kontak</th>
                    <th class="px-4 py-3">Alamat</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $supplier)
                    <tr class="{{ $supplier->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $supplier->name }}</div>
                            <div class="text-xs text-slate-400">{{ $supplier->code ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ $supplier->phone ?? '-' }}</div>
                            <div class="text-xs text-slate-400">{{ $supplier->email ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $supplier->address ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if ($supplier->is_active)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">aktif</span>
                            @else
                                <span class="rounded bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="text-slate-700 hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}"
                                      onsubmit="return confirm('Hapus pemasok ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada pemasok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $suppliers->links() }}</div>
@endsection
