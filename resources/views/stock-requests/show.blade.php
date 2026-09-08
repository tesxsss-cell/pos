@extends('layouts.app')

@section('title', 'Request '.$stockRequest->code)

@section('content')
    @php($me = auth()->user())

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Request Stok {{ $stockRequest->code }}</h1>
            <p class="text-xs text-slate-500">
                {{ $stockRequest->branch->name ?? '-' }} &middot; diajukan {{ $stockRequest->requester->name ?? '-' }}
                &middot; {{ $stockRequest->created_at?->format('d M Y H:i') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="rounded px-2 py-1 text-xs font-medium {{ $stockRequest->status->badgeClass() }}">{{ $stockRequest->status->label() }}</span>
            <a href="{{ route('stock-requests.index') }}" class="text-sm text-slate-600 hover:underline">Kembali</a>
        </div>
    </div>

    @if ($stockRequest->note)
        <p class="mb-4 rounded-md bg-white px-4 py-3 text-sm text-slate-600 shadow">Catatan cabang: {{ $stockRequest->note }}</p>
    @endif

    <form method="POST" action="{{ route('stock-requests.approve', $stockRequest) }}">
        @csrf

        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Barang</th>
                        <th class="px-4 py-3 text-right">Diminta</th>
                        <th class="px-4 py-3 text-right">Stok gudang</th>
                        <th class="px-4 py-3 text-right">Disetujui</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($stockRequest->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $item->product->name ?? '-' }}</div>
                                <div class="text-xs text-slate-400">{{ $item->product->sku ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">{{ $item->quantity_requested }} {{ $item->product->unit ?? '' }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">
                                {{ $item->product?->stocks?->sum('quantity') ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($stockRequest->isPending() && $me->isCentral())
                                    <input name="approved[{{ $item->id }}]" type="number" min="0" max="{{ $item->quantity_requested }}"
                                           value="{{ $item->quantity_requested }}"
                                           class="w-24 rounded border border-slate-300 px-2 py-1 text-right">
                                @else
                                    {{ $item->quantity_approved ?? '-' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($stockRequest->isPending() && $me->isCentral())
            <div class="mt-4 rounded-xl bg-white p-4 shadow">
                <label class="block text-sm font-medium text-slate-700">Catatan persetujuan</label>
                <input name="response_note" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Setujui request</button>
                    <button type="submit" formaction="{{ route('stock-requests.reject', $stockRequest) }}"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white">Tolak request</button>
                    <span class="text-xs text-slate-500">Untuk menolak, catatan wajib diisi.</span>
                </div>
            </div>
        @endif
    </form>

    @if ($stockRequest->responded_at)
        <div class="mt-4 rounded-xl bg-white p-4 text-sm shadow">
            <p class="text-slate-600">
                Ditanggapi {{ $stockRequest->responder->name ?? '-' }} pada {{ $stockRequest->responded_at?->format('d M Y H:i') }}
            </p>
            @if ($stockRequest->response_note)
                <p class="mt-1 text-slate-500">Catatan: {{ $stockRequest->response_note }}</p>
            @endif
        </div>
    @endif

    <div class="mt-4 rounded-xl bg-white p-4 shadow">
        <h2 class="font-semibold text-slate-900">Dokumen mutasi terkait</h2>
        @forelse ($stockRequest->transfers as $transfer)
            <p class="mt-2 text-sm">
                <a href="{{ route('transfers.show', $transfer) }}" class="font-medium text-slate-800 hover:underline">{{ $transfer->code }}</a>
                <span class="text-slate-500">&middot; {{ $transfer->status->label() }}</span>
            </p>
        @empty
            <p class="mt-2 text-sm text-slate-400">
                Belum ada dokumen mutasi.
                @if ($stockRequest->status->value === 'disetujui' && $me->isCentral())
                    <a href="{{ route('transfers.create') }}" class="text-slate-700 hover:underline">Buat sekarang</a>
                @endif
            </p>
        @endforelse
    </div>
@endsection
