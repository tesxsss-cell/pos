@extends('layouts.app')

@section('title', 'Laporan Laba / Rugi')

@section('content')
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Laporan Laba / Rugi (HPP FIFO)</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Dari tanggal</label>
            <input name="dari" type="date" value="{{ $from }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Sampai tanggal</label>
            <input name="sampai" type="date" value="{{ $to }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Lokasi</label>
            <select name="cabang" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua lokasi</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Terapkan</button>
    </form>

    <div class="mb-4 rounded-xl bg-white p-6 shadow">
        <h2 class="font-semibold text-slate-900">Ringkasan periode {{ $from }} s.d. {{ $to }}</h2>

        @php($marjin = $summary['revenue'] > 0 ? round($summary['gross_profit'] / $summary['revenue'] * 100, 1) : 0)

        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between border-b border-slate-100 pb-2">
                <dt class="text-slate-500">Penjualan bersih ({{ $summary['transactions'] }} transaksi)</dt>
                <dd class="font-medium">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-100 pb-2">
                <dt class="text-slate-500">Harga pokok penjualan (FIFO)</dt>
                <dd class="font-medium text-red-700">(Rp {{ number_format($summary['cogs'], 0, ',', '.') }})</dd>
            </div>
            <div class="flex justify-between border-b border-slate-100 pb-2">
                <dt class="font-medium text-slate-700">Laba kotor</dt>
                <dd class="font-semibold text-emerald-700">Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Marjin laba kotor</dt>
                <dd class="font-medium">{{ $marjin }}%</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Rata-rata nilai transaksi</dt>
                <dd>Rp {{ number_format($summary['average_transaction'], 0, ',', '.') }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl bg-white p-6 shadow">
        <h2 class="font-semibold text-slate-900">Grafik penjualan vs laba kotor</h2>

        @php($puncak = max((float) $series->max('penjualan'), 1))

        <div class="mt-4 flex h-56 items-end gap-2 overflow-x-auto">
            @forelse ($series as $row)
                <div class="flex min-w-12 flex-1 flex-col items-center gap-1">
                    <div class="flex h-44 w-full items-end justify-center gap-1">
                        <div class="w-3 rounded-t bg-slate-800" style="height: {{ max(round(((float) $row->penjualan / $puncak) * 100), 2) }}%"
                             title="Penjualan Rp {{ number_format((float) $row->penjualan, 0, ',', '.') }}"></div>
                        <div class="w-3 rounded-t bg-emerald-500" style="height: {{ max(round(((float) $row->laba_kotor / $puncak) * 100), 2) }}%"
                             title="Laba kotor Rp {{ number_format((float) $row->laba_kotor, 0, ',', '.') }}"></div>
                    </div>
                    <span class="text-[10px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('d/m') }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Belum ada data pada rentang ini.</p>
            @endforelse
        </div>

        <div class="mt-3 flex gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-3 rounded bg-slate-800"></span> Penjualan</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-3 rounded bg-emerald-500"></span> Laba kotor</span>
        </div>
    </div>
@endsection
