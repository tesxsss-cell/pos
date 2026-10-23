@extends('layouts.app')
@section('title', 'Riwayat Penjualan')
@section('content')
    <p class="mb-4 text-sm text-slate-500">
        Halaman ini hanya untuk melihat riwayat dan mencetak struk. Penjualan yang sudah tersimpan tidak dapat dibatalkan
        melalui aplikasi. Periksa barang dan pembayaran sebelum checkout.</p>
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow"><label>Dari<input
                name="dari" type="date" class="field" value="{{ $from }}"></label><label>Sampai<input
                name="sampai" type="date" class="field" value="{{ $to }}"></label><button
            class="btn-primary">Tampilkan</button></form>
    <div class="space-y-4">
        @forelse($sales as $sale)
            <article class="rounded-xl bg-white p-5 shadow">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <p class="font-semibold break-all">{{ $sale->invoice_no }}</p>
                        <p>{{ $sale->cashier->name }} · {{ $sale->sold_at->format('d/m/Y H:i') }} ·
                            {{ $sale->payment_method->value }}</p>
                    </div>
                    <div>Rp {{ number_format((float) $sale->total, 0, ',', '.') }} ·
                        <strong>{{ $sale->status->value }}</strong><br><a target="_blank" rel="noopener"
                            href="{{ route('pos.receipt', $sale) }}" class="text-brand-700 underline">Lihat struk</a></div>
                </div>
                @if (!$sale->isCompleted())
                    <p class="mt-3 text-red-700">Alasan: {{ $sale->void_reason ?? $sale->note }} · Dibatalkan
                        {{ $sale->voided_at?->format('d/m/Y H:i') }} oleh akun #{{ $sale->voided_by }}</p>
                @endif
        </article>@empty<p>Belum ada penjualan pada periode ini.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $sales->links() }}</div>
@endsection
