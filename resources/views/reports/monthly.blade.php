@extends('layouts.app')

@section('title', 'Laporan Penjualan Bulanan')

@section('content')
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Laporan Penjualan Bulanan {{ $year }}</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Tahun</label>
            <input name="tahun" type="number" min="2020" max="2100" value="{{ $year }}"
                   class="mt-1 w-28 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
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
        <a href="{{ route('reports.daily') }}" class="text-sm text-slate-600 hover:underline">Lihat laporan harian</a>
    </form>

    @php($totalPenjualan = (float) $series->sum('penjualan'))
    @php($totalLaba = (float) $series->sum('laba_kotor'))
    @php($puncak = max((float) $series->max('penjualan'), 1))

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-xs text-slate-500">Total penjualan {{ $year }}</p>
            <p class="mt-1 text-xl font-semibold">Rp {{ number_format($totalPenjualan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-xs text-slate-500">Total HPP (FIFO)</p>
            <p class="mt-1 text-xl font-semibold">Rp {{ number_format((float) $series->sum('hpp'), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-xs text-slate-500">Total laba kotor</p>
            <p class="mt-1 text-xl font-semibold text-emerald-700">Rp {{ number_format($totalLaba, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-4 rounded-xl bg-white p-6 shadow">
        <h2 class="font-semibold text-slate-900">Grafik penjualan per bulan</h2>
        <div class="mt-4 flex h-48 items-end gap-2">
            @forelse ($series as $row)
                <div class="flex flex-1 flex-col items-center gap-1">
                    <div class="flex h-40 w-full items-end justify-center">
                        <div class="w-6 rounded-t bg-slate-800" style="height: {{ max(round(((float) $row['penjualan'] / $puncak) * 100), 2) }}%"
                             title="Rp {{ number_format((float) $row['penjualan'], 0, ',', '.') }}"></div>
                    </div>
                    <span class="text-[10px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($row['bulan'].'-01')->format('M') }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Belum ada penjualan pada tahun ini.</p>
            @endforelse
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Bulan</th>
                    <th class="px-4 py-3 text-right">Transaksi</th>
                    <th class="px-4 py-3 text-right">Penjualan</th>
                    <th class="px-4 py-3 text-right">HPP (FIFO)</th>
                    <th class="px-4 py-3 text-right">Laba kotor</th>
                    <th class="px-4 py-3 text-right">Marjin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($series as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row['nama_bulan'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $row['transaksi'] }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $row['penjualan'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-slate-500">Rp {{ number_format((float) $row['hpp'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-medium text-emerald-700">Rp {{ number_format((float) $row['laba_kotor'], 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            {{ $row['penjualan'] > 0 ? round($row['laba_kotor'] / $row['penjualan'] * 100, 1) : 0 }}%
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
