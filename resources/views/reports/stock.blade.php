@extends('layouts.app')

@section('title', 'Laporan Stok')

@section('content')
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Peringatan Stok Minimum</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Lokasi</label>
            <select name="cabang" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua lokasi</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Terapkan</button>
        <a href="{{ route('reports.stock-card') }}" class="text-sm text-slate-600 hover:underline">Lihat kartu stok &amp; batch FIFO</a>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Barang</th>
                    <th class="px-4 py-3">Lokasi</th>
                    <th class="px-4 py-3 text-right">Sisa stok</th>
                    <th class="px-4 py-3 text-right">Batas minimum</th>
                    <th class="px-4 py-3 text-right">Kekurangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($lowStocks as $stock)
                    @php($min = $stock->effectiveMinStock())
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $stock->product->name }}</div>
                            <div class="text-xs text-slate-400">{{ $stock->product->sku }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $stock->branch->name }}</td>
                        <td class="px-4 py-3 text-right font-medium text-red-700">{{ $stock->quantity }} {{ $stock->product->unit }}</td>
                        <td class="px-4 py-3 text-right">{{ $min }}</td>
                        <td class="px-4 py-3 text-right">{{ max($min - $stock->quantity, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Semua stok masih aman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
