@extends('layouts.app')

@section('title', 'Rekap Shift Kasir')

@section('content')
    <h1 class="mb-4 text-lg font-semibold text-slate-900">Rekap Shift Kasir</h1>

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

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Kasir</th>
                    <th class="px-4 py-3">Lokasi</th>
                    <th class="px-4 py-3">Dibuka</th>
                    <th class="px-4 py-3">Ditutup</th>
                    <th class="px-4 py-3 text-right">Kas awal</th>
                    <th class="px-4 py-3 text-right">Perkiraan sistem</th>
                    <th class="px-4 py-3 text-right">Uang fisik</th>
                    <th class="px-4 py-3 text-right">Selisih</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($shifts as $shift)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $shift->user->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $shift->branch->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $shift->opened_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if ($shift->isOpen())
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">masih dibuka</span>
                            @else
                                {{ $shift->closed_at?->format('d M Y H:i') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $shift->opening_cash, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $shift->expected_cash, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">{{ $shift->actual_cash === null ? '-' : 'Rp '.number_format((float) $shift->actual_cash, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ (float) $shift->difference < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                            {{ $shift->actual_cash === null ? '-' : 'Rp '.number_format((float) $shift->difference, 0, ',', '.') }}
                        </td>
                    </tr>
                    @if ($shift->note)
                        <tr class="bg-slate-50">
                            <td colspan="8" class="px-4 py-2 text-xs text-slate-500">Catatan: {{ $shift->note }}</td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">Belum ada shift pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
