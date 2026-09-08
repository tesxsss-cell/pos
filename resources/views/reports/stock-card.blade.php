@extends('layouts.app')

@section('title', 'Kartu Stok')

@section('content')
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Kartu Stok &amp; Lapisan FIFO</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Barang</label>
            <select name="barang" required class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">-- pilih barang --</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected($productId === $product->id)>{{ $product->name }} ({{ $product->sku }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Lokasi</label>
            <select name="cabang" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">-- pilih lokasi --</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Dari tanggal</label>
            <input name="dari" type="date" value="{{ $from }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Sampai tanggal</label>
            <input name="sampai" type="date" value="{{ $to }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Tampilkan</button>
    </form>

    @if (! $productId || ! $branchId)
        <div class="rounded-xl bg-white p-6 text-sm text-slate-500 shadow">
            Pilih barang dan lokasi terlebih dahulu untuk melihat pergerakan stok beserta sisa lapisan FIFO.
        </div>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            @php($sisa = (int) collect($layers)->sum('remaining_quantity'))
            @php($nilai = (float) collect($layers)->sum(fn ($l) => (float) $l->remaining_quantity * (float) $l->unit_cost))
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500">Sisa stok (lapisan aktif)</p>
                <p class="mt-1 text-xl font-semibold">{{ number_format($sisa, 0, ',', '.') }} unit</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500">Nilai persediaan</p>
                <p class="mt-1 text-xl font-semibold">Rp {{ number_format($nilai, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500">Jumlah lapisan FIFO</p>
                <p class="mt-1 text-xl font-semibold">{{ count($layers) }} batch</p>
            </div>
        </div>

        <div class="mb-4 overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Jenis pergerakan</th>
                        <th class="px-4 py-3 text-right">Masuk</th>
                        <th class="px-4 py-3 text-right">Keluar</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3 text-right">Nilai HPP</th>
                        <th class="px-4 py-3">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3">{{ $movement->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">
                                {{ $movement->type->label() }}
                                @if ($movement->note)
                                    <div class="text-xs text-slate-400">{{ $movement->note }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-emerald-700">
                                {{ $movement->type->isIncoming() ? number_format(abs((int) $movement->quantity), 0, ',', '.') : '' }}
                            </td>
                            <td class="px-4 py-3 text-right text-red-700">
                                {{ $movement->type->isIncoming() ? '' : number_format(abs((int) $movement->quantity), 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format((int) $movement->balance_after, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">Rp {{ number_format((float) $movement->cost_total, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">{{ $movement->user->name ?? 'sistem' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Tidak ada pergerakan pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <div class="border-b border-slate-100 px-4 py-3">
                <h2 class="font-semibold text-slate-900">Sisa lapisan FIFO (urut tertua)</h2>
                <p class="text-xs text-slate-500">Penjualan berikutnya akan mengambil harga pokok dari baris paling atas.</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Batch</th>
                        <th class="px-4 py-3">Tanggal masuk</th>
                        <th class="px-4 py-3 text-right">Harga beli / unit</th>
                        <th class="px-4 py-3 text-right">Jumlah awal</th>
                        <th class="px-4 py-3 text-right">Sisa</th>
                        <th class="px-4 py-3 text-right">Nilai sisa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($layers as $layer)
                        <tr>
                            <td class="px-4 py-3 font-medium">#{{ $layer->id }}</td>
                            <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($layer->received_at)->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format((float) $layer->unit_cost, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ number_format((int) $layer->quantity, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format((int) $layer->remaining_quantity, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format((float) $layer->remaining_quantity * (float) $layer->unit_cost, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Tidak ada lapisan aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
