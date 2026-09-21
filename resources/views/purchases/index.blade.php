@extends('layouts.app')

@section('title', 'Penerimaan Barang')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Penerimaan Barang dari Pemasok</h1>
            <p class="text-xs text-slate-500">Setiap dokumen yang diposting membentuk batch harga beli baru (lapisan FIFO).</p>
        </div>
        <a href="{{ route('purchases.create') }}" class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Buat penerimaan</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Lokasi tujuan</label>
            <select name="cabang" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua lokasi</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(request('cabang') == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">Status</label>
            <select name="status" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua status</option>
                @foreach (['draft' => 'Draft', 'posted' => 'Sudah diposting', 'cancelled' => 'Dibatalkan'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Pemasok</th>
                    <th class="px-4 py-3">Masuk ke</th>
                    <th class="px-4 py-3 text-right">Nilai</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($purchases as $purchase)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $purchase->code }}</td>
                        <td class="px-4 py-3">{{ $purchase->purchase_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $purchase->supplier->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $purchase->branch->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format((float) $purchase->total_cost, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @php($warna = $purchase->status->value === 'posted' ? 'bg-emerald-100 text-emerald-700' : ($purchase->status->value === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600'))
                            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $warna }}">{{ $purchase->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('purchases.show', $purchase) }}" class="text-slate-700 hover:underline">Rincian</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada dokumen penerimaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $purchases->links() }}</div>
@endsection
