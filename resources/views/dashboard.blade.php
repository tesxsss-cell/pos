@extends('layouts.app')

@section('title', 'Dasbor')

@section('content')
    @php($maxSeries = max(1, (float) $dailySeries->max('penjualan')))

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Darikan tanggalx2</label>
            <input type="date" name="dari" value="{{ $from }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Sampai tanggal</label>
            <input type="date" name="sampai" value="{{ $to }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        @if ($branches->isNotEmpty())
            <div>
                <label class="block text-xs font-medium text-slate-500">Cabang</label>
                <select name="cabang" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                    <option value="">Semua cabang</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Terapkan filter</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Penjualan', $summary['revenue'], 'text-slate-900'],
            ['HPP (FIFO)', $summary['cogs'], 'text-amber-700'],
            ['Laba kotor', $summary['gross_profit'], $summary['gross_profit'] >= 0 ? 'text-emerald-700' : 'text-red-700'],
            ['Nilai persediaan', $inventoryValue, 'text-slate-900'],
        ] as [$label, $value, $tone])
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold {{ $tone }}">Rp {{ number_format((float) $value, 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-xs uppercase tracking-wide text-slate-500">Transaksi</p>
            <p class="mt-1 text-xl font-semibold">{{ number_format($summary['transactions'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-xs uppercase tracking-wide text-slate-500">Barang terjual</p>
            <p class="mt-1 text-xl font-semibold">{{ number_format($summary['items_sold'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow">
            <p class="text-xs uppercase tracking-wide text-slate-500">Rata-rata per transaksi</p>
            <p class="mt-1 text-xl font-semibold">Rp {{ number_format($summary['average_transaction'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl bg-white p-4 shadow">
            <h2 class="font-semibold text-slate-900">Grafik penjualan &amp; laba harian</h2>
            @if ($dailySeries->isEmpty())
                <p class="mt-4 text-sm text-slate-500">Belum ada transaksi pada periode ini.</p>
            @else
                <div class="mt-4 space-y-2">
                    @foreach ($dailySeries as $row)
                        <div>
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>{{ \Illuminate\Support\Carbon::parse($row->tanggal)->format('d M Y') }}</span>
                                <span>Rp {{ number_format((float) $row->penjualan, 0, ',', '.') }} &middot; laba Rp {{ number_format((float) $row->laba_kotor, 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-1 h-3 w-full overflow-hidden rounded bg-slate-100">
                                <div class="h-3 rounded bg-slate-800" style="width: {{ round(((float) $row->penjualan / $maxSeries) * 100, 2) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-xl bg-white p-4 shadow">
            <h2 class="font-semibold text-slate-900">Produk terlaris</h2>
            @if ($topProducts->isEmpty())
                <p class="mt-4 text-sm text-slate-500">Belum ada data penjualan.</p>
            @else
                <table class="mt-3 w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr><th class="py-2">Barang</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Laba kotor</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($topProducts as $product)
                            <tr>
                                <td class="py-2">{{ $product->name }} <span class="text-xs text-slate-400">{{ $product->sku }}</span></td>
                                <td class="py-2 text-right">{{ number_format((float) $product->qty_terjual, 0, ',', '.') }}</td>
                                <td class="py-2 text-right">Rp {{ number_format((float) $product->laba_kotor, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="rounded-xl bg-white p-4 shadow">
            <h2 class="font-semibold text-slate-900">Peringatan stok minimum</h2>
            @if ($lowStocks->isEmpty())
                <p class="mt-4 text-sm text-slate-500">Semua stok masih di atas batas minimum.</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @foreach ($lowStocks as $stock)
                        <li class="flex items-center justify-between py-2">
                            <span>{{ $stock->product->name }} <span class="text-xs text-slate-400">{{ $stock->branch->name }}</span></span>
                            <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                sisa {{ $stock->quantity }} {{ $stock->product->unit }} (min {{ $stock->effectiveMinStock() }})
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-xl bg-white p-4 shadow">
            <h2 class="font-semibold text-slate-900">Rekap shift kasir</h2>
            @if ($shifts->isEmpty())
                <p class="mt-4 text-sm text-slate-500">Belum ada shift pada periode ini.</p>
            @else
                <table class="mt-3 w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr><th class="py-2">Kasir</th><th class="py-2">Status</th><th class="py-2 text-right">Selisih kas</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($shifts as $shift)
                            <tr>
                                <td class="py-2">{{ $shift->user->name }}<div class="text-xs text-slate-400">{{ $shift->opened_at?->format('d M Y H:i') }}</div></td>
                                <td class="py-2">{{ $shift->status }}</td>
                                <td class="py-2 text-right">Rp {{ number_format((float) $shift->difference, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
@endsection
