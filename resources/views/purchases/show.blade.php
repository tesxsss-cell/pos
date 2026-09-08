@extends('layouts.app')

@section('title', 'Penerimaan '.$purchase->code)

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Penerimaan {{ $purchase->code }}</h1>
            <p class="text-xs text-slate-500">Dibuat oleh {{ $purchase->user->name ?? '-' }} &middot; {{ $purchase->created_at?->format('d M Y H:i') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($purchase->isDraft())
                <form method="POST" action="{{ route('purchases.post', $purchase) }}"
                      onsubmit="return confirm('Posting dokumen ini? Stok akan bertambah dan batch FIFO terbentuk.')">
                    @csrf
                    <button class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Posting ke stok</button>
                </form>
            @endif
            <a href="{{ route('purchases.index') }}" class="text-sm text-slate-600 hover:underline">Kembali</a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium">{{ $purchase->status->label() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Pemasok</dt><dd>{{ $purchase->supplier->name ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Masuk ke</dt><dd>{{ $purchase->branch->name ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Tanggal</dt><dd>{{ $purchase->purchase_date?->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Diposting</dt><dd>{{ $purchase->posted_at?->format('d M Y H:i') ?? 'belum' }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2"><dt class="text-slate-500">Total nilai</dt><dd class="font-semibold">Rp {{ number_format((float) $purchase->total_cost, 0, ',', '.') }}</dd></div>
            </dl>
            @if ($purchase->note)
                <p class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $purchase->note }}</p>
            @endif
        </div>

        <div class="lg:col-span-2 overflow-x-auto rounded-xl bg-white shadow">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Barang</th>
                        <th class="px-4 py-3 text-right">Jumlah</th>
                        <th class="px-4 py-3 text-right">Harga beli</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($purchase->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $item->product->name ?? '-' }}</div>
                                <div class="text-xs text-slate-400">{{ $item->product->sku ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right">{{ $item->quantity }} {{ $item->product->unit ?? '' }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
