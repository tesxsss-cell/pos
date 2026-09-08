@extends('layouts.app')

@section('title', 'Mutasi '.$transfer->code)

@section('content')
    @php($me = auth()->user())

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Mutasi Stok {{ $transfer->code }}</h1>
            <p class="text-xs text-slate-500">
                {{ $transfer->fromBranch->name ?? '-' }} &rarr; {{ $transfer->toBranch->name ?? '-' }}
                @if ($transfer->stockRequest)
                    &middot; dasar {{ $transfer->stockRequest->code }}
                @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $transfer->status->label() }}</span>
            <a href="{{ route('transfers.index') }}" class="text-sm text-slate-600 hover:underline">Kembali</a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Dikirim oleh</dt><dd>{{ $transfer->shipper->name ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Waktu kirim</dt><dd>{{ $transfer->shipped_at?->format('d M Y H:i') ?? 'belum' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Diterima oleh</dt><dd>{{ $transfer->receiver->name ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Waktu terima</dt><dd>{{ $transfer->received_at?->format('d M Y H:i') ?? 'belum' }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="text-slate-500">Nilai HPP</dt><dd class="font-semibold">Rp {{ number_format((float) $transfer->total_cost, 0, ',', '.') }}</dd></div>
                </dl>
                @if ($transfer->note)
                    <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $transfer->note }}</p>
                @endif
            </div>

            @if ($transfer->isDraft() && $me->isCentral())
                <form method="POST" action="{{ route('transfers.ship', $transfer) }}" class="rounded-xl bg-white p-4 shadow"
                      onsubmit="return confirm('Kirim dokumen ini? Stok gudang akan berkurang mengikuti FIFO.')">
                    @csrf
                    <h2 class="font-semibold text-slate-900">Kirim barang</h2>
                    <p class="mt-1 text-xs text-slate-500">Stok keluar diambil dari batch termurah/tertua sesuai FIFO.</p>
                    <button class="mt-3 w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white">Kirim sekarang</button>
                </form>
            @endif
        </div>

        <div class="lg:col-span-2 space-y-4">
            <form method="POST" action="{{ route('transfers.receive', $transfer) }}">
                @csrf
                <div class="overflow-x-auto rounded-xl bg-white shadow">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Barang</th>
                                <th class="px-4 py-3 text-right">Dikirim</th>
                                <th class="px-4 py-3 text-right">Diterima</th>
                                <th class="px-4 py-3 text-right">HPP batch</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($transfer->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $item->product->name ?? '-' }}</div>
                                        <div class="text-xs text-slate-400">{{ $item->product->sku ?? '' }}</div>
                                        @if (! empty($item->cost_layers))
                                            <div class="mt-1 text-xs text-slate-500">
                                                @foreach ($item->cost_layers as $layer)
                                                    <div>{{ $layer['quantity'] ?? 0 }} unit @ Rp {{ number_format((float) ($layer['unit_cost'] ?? 0), 0, ',', '.') }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ $item->quantity_sent }} {{ $item->product->unit ?? '' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($transfer->isShipped())
                                            <input name="received[{{ $item->id }}]" type="number" min="0" max="{{ $item->quantity_sent }}"
                                                   value="{{ $item->quantity_sent }}"
                                                   class="w-24 rounded border border-slate-300 px-2 py-1 text-right">
                                        @else
                                            {{ $item->quantity_received ?? '-' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">Rp {{ number_format((float) $item->cost_total, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($transfer->isShipped())
                    <div class="mt-3 rounded-xl bg-white p-4 shadow">
                        <h2 class="font-semibold text-slate-900">Konfirmasi penerimaan di cabang</h2>
                        <p class="mt-1 text-xs text-slate-500">Isi jumlah yang benar-benar diterima. Batch FIFO dibentuk ulang di cabang dengan harga pokok yang sama.</p>
                        <button class="mt-3 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Terima barang</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@endsection
