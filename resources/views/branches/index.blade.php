@extends('layouts.app')

@section('title', 'Gudang & Cabang')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold text-slate-900">Gudang Pusat &amp; Cabang</h1>
        <a href="{{ route('branches.create') }}" class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Tambah lokasi</a>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama lokasi</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">Kontak</th>
                    <th class="px-4 py-3 text-right">Jumlah akun</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($branches as $branch)
                    <tr class="{{ $branch->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3 font-medium">{{ $branch->code }}</td>
                        <td class="px-4 py-3">
                            <div>{{ $branch->name }}</div>
                            <div class="text-xs text-slate-400">{{ $branch->address ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($branch->isWarehouse())
                                <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">gudang pusat</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">cabang</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $branch->phone ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">{{ $branch->users_count }}</td>
                        <td class="px-4 py-3">
                            @if ($branch->is_active)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">aktif</span>
                            @else
                                <span class="rounded bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('branches.edit', $branch) }}" class="text-slate-700 hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('branches.destroy', $branch) }}"
                                      onsubmit="return confirm('Hapus lokasi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada lokasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $branches->links() }}</div>
@endsection
