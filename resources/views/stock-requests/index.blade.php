@extends('layouts.app')

@section('title', 'Request Stok')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Request Stok Cabang</h1>
            <p class="text-xs text-slate-500">Cabang mengajukan kebutuhan barang, pusat menyetujui lalu membuat dokumen mutasi stok.</p>
        </div>
        @if (auth()->user()->branch_id)
            <a href="{{ route('stock-requests.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Buat request</a>
        @endif
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
        <div>
            <label class="block text-xs font-medium text-slate-500">Status</label>
            <select name="status" class="mt-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Cabang</th>
                    <th class="px-4 py-3">Pengaju</th>
                    <th class="px-4 py-3 text-right">Jenis barang</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($requests as $stockRequest)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $stockRequest->code }}</td>
                        <td class="px-4 py-3">{{ $stockRequest->branch->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $stockRequest->requester->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">{{ $stockRequest->items->count() }} item</td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs font-medium {{ $stockRequest->status->badgeClass() }}">{{ $stockRequest->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $stockRequest->created_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('stock-requests.show', $stockRequest) }}" class="text-slate-700 hover:underline">Rincian</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Belum ada request stok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
@endsection
