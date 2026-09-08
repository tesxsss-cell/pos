@extends('layouts.app')

@section('title', 'Laporan Penjualan Harian')

@section('content')
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Laporan Penjualan Harian</h1>

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
        <a href="{{ route('reports.monthly') }}" class="text-sm text-slate-600 hover:underline">Lihat laporan bulanan</a>
    </form>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Jumlah transaksi', number_format($summary['transactions'], 0, ',', '.')],
            ['Barang terjual', number_format($summary['items_sold'], 0, ',', '.').' unit'],
            ['Penjualan', 'Rp '.number_format($summary['revenue'], 0, ',', '.')],
            ['Laba kotor', 'Rp '.number_format($summary['gross_profit'], 0, ',', '.')],
        ] as [$label, $nilai])
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $nilai }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Transaksi</th>
                        <th class="px-4 py-3 text-right">Penjualan</th>
                        <th class="px-4 py-3 text-right">HPP (FIFO)</th>
                        <th class="px-4 py-3 text-right">Laba kotor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($series as $row)
                        <tr>
                            <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->tanggal)->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">{{ $row->transaksi }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format((float) $row->penjualan, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">Rp {{ number_format((float) $row->hpp, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-emerald-700">Rp {{ number_format((float) $row->laba_kotor, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Belum ada penjualan pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl bg-white p-4 shadow">
            <h2 class="font-semibold text-slate-900">Produk terlaris</h2>
            @php($maksimum = max((float) $topProducts->max('qty_terjual'), 1))
            <div class="mt-3 space-y-3">
                @forelse ($topProducts as $produk)
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium">{{ $produk->name }}</span>
                            <span class="text-slate-500">{{ $produk->qty_terjual }} unit</span>
                        </div>
                        <div class="mt-1 h-2 rounded bg-slate-100">
                            <div class="h-2 rounded bg-slate-800" style="width: {{ round(($produk->qty_terjual / $maksimum) * 100) }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">
                            Rp {{ number_format((float) $produk->nilai_penjualan, 0, ',', '.') }} &middot;
                            laba Rp {{ number_format((float) $produk->laba_kotor, 0, ',', '.') }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Belum ada data.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
