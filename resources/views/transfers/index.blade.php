@extends('layouts.app')

@section('title', 'Mutasi Stok')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Mutasi Stok Gudang &rarr; Cabang</h1>
            <p class="text-xs text-slate-500">Harga pokok setiap batch FIFO terbawa saat barang dipindahkan.</p>
        </div>
        @if (auth()->user()->isCentral())
            <a href="{{ route('transfers.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Buat mutasi</a>
        @endif
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Status</label>
            <select name="status" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua status</option>
                @foreach (['draft' => 'Draft', 'dikirim' => 'Dikirim', 'diterima' => 'Diterima', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Dari</th>
                    <th class="px-4 py-3">Ke</th>
                    <th class="px-4 py-3 text-right">Jenis barang</th>
                    <th class="px-4 py-3 text-right">Nilai HPP</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $transfer->code }}</td>
                        <td class="px-4 py-3">{{ $transfer->fromBranch->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $transfer->toBranch->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">{{ $transfer->items->count() }} item</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $transfer->total_cost, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @php($warna = match ($transfer->status->value) {
                                'diterima' => 'bg-emerald-100 text-emerald-700',
                                'dikirim' => 'bg-blue-100 text-blue-700',
                                'draft' => 'bg-amber-100 text-amber-700',
                                default => 'bg-slate-200 text-slate-600',
                            })
                            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $warna }}">{{ $transfer->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('transfers.show', $transfer) }}" class="text-slate-700 hover:underline">Rincian</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada dokumen mutasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transfers->links() }}</div>
@endsection
