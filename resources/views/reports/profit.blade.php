@extends('layouts.app')

@section('title', 'Laporan Laba Kotor')

@section('content')
    <button type="button" onclick="window.print()" class="btn-primary mb-4 no-print">Cetak laporan</button>
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Laporan Laba Kotor (HPP rata-rata bergerak)</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500" for="field-1">Dari tanggal</label>
<input id="field-1" name="dari" type="date" value="{{ $from }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500" for="field-2">Sampai tanggal</label>
<input id="field-2" name="sampai" type="date" value="{{ $to }}" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        </div>
        
        <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Terapkan</button>
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
                <dt class="text-slate-500">Harga pokok penjualan (rata-rata bergerak)</dt>
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
        <h2 class="mb-3 font-semibold">Rincian harian</h2>
        <p class="mb-3 text-sm text-slate-500">Laba kotor = penjualan − HPP. Belum dikurangi biaya operasional, penyusutan, dan pajak; bukan SHU.</p>
        <div class="overflow-x-auto">@php($tableRows=\App\Support\TablePage::make($series))
<table data-server-table class="w-full text-left text-sm"><thead><tr><th>Tanggal</th><th>Penjualan</th><th>HPP</th><th>Laba kotor</th></tr></thead><tbody>
        @forelse($tableRows as $row)<tr class="border-t"><td class="py-3">{{ $row->tanggal }}</td><td>Rp {{ number_format((float)$row->penjualan,0,',','.') }}</td><td>Rp {{ number_format((float)$row->hpp,0,',','.') }}</td><td>Rp {{ number_format((float)$row->laba_kotor,0,',','.') }}</td></tr>
        @empty<tr><td colspan="4" class="py-3">Belum ada transaksi.</td></tr>@endforelse
        </tbody></table>
{{ $tableRows->links() }}</div>
    </div>
@endsection
