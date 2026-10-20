@extends('layouts.app')

@section('title', 'Kartu Stok')

@section('content')
    <button type="button" onclick="window.print()" class="btn-primary mb-4 no-print">Cetak laporan</button>
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Kartu Stok dan HPP Rata-rata</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500" for="field-1">Barang</label>
<select id="field-1" name="barang" required class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">-- pilih barang --</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((int) $productId === (int) $product->id)>{{ $product->name }} ({{ $product->sku }})</option>
                @endforeach
            </select>
        </div>
        
        <div>
            <label class="block text-xs font-medium text-slate-500" for="field-2">Dari tanggal</label>
<input id="field-2" name="dari" type="date" value="{{ $from }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500" for="field-3">Sampai tanggal</label>
<input id="field-3" name="sampai" type="date" value="{{ $to }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Tampilkan</button>
    </form>

    @if (! $productId || ! $branchId)
        <div class="rounded-xl bg-white p-6 text-sm text-slate-500 shadow">
            Pilih barang terlebih dahulu untuk melihat pergerakan stok beserta nilai persediaan.
        </div>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            @php($saldo = \App\Models\Stock::where('product_id', $productId)->where('branch_id', $branchId)->first())
            @php($sisa = $saldo?->quantity ?? 0)
            @php($nilai = $sisa * (float) ($saldo?->avg_cost ?? 0))
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500">Saldo stok</p>
                <p class="mt-1 text-xl font-semibold">{{ number_format($sisa, 0, ',', '.') }} unit</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500">Nilai persediaan</p>
                <p class="mt-1 text-xl font-semibold">Rp {{ number_format($nilai, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow">
                <p class="text-xs text-slate-500">HPP rata-rata saldo</p>
                <p class="mt-1 text-xl font-semibold">Rp {{ number_format((float) ($saldo?->avg_cost ?? 0), 4, ',', '.') }}</p>
            </div>
        </div>

        <div class="mb-4 overflow-x-auto rounded-xl bg-white shadow">
            @php($tableRows=\App\Support\TablePage::make($movements))
<table data-server-table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Jenis pergerakan</th>
                        <th class="px-4 py-3 text-right">Masuk</th>
                        <th class="px-4 py-3 text-right">Keluar</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3 text-right">Nilai HPP</th>
                        <th class="px-4 py-3 text-right">HPP saldo</th><th class="px-4 py-3">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tableRows as $movement)
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
                            <td class="px-4 py-3 text-right">{{ $movement->avg_cost_after === null ? 'Belum tercatat' : number_format((float) $movement->avg_cost_after, 4, ',', '.') }}</td><td class="px-4 py-3">{{ $movement->user->name ?? 'sistem' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">Tidak ada pergerakan pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
{{ $tableRows->links() }}
        </div>

        <p class="rounded-xl bg-white p-4 text-sm">HPP menggunakan rata-rata bergerak di toko ini. Pengeluaran tidak mengubah rata-rata.</p>
    @endif
@endsection
