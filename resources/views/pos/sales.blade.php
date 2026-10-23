@extends('layouts.app')
@section('title', 'Riwayat Penjualan & Pembatalan')
@section('content')
    <p class="mb-4 text-sm text-slate-500">
        Pembatalan hanya seluruh struk dan hanya selama shift asal masih terbuka. Pastikan barang serta uang sudah ditangani
        secara fisik. Pengembalian QRIS/transfer/debit dilakukan manual.</p>
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
                @elseif($sale->shift?->isOpen())
                    <form method="POST" action="{{ route('pos.void', $sale) }}" class="mt-4 space-y-3"
                        onsubmit="return confirm('Batalkan seluruh struk? Riwayat tetap disimpan. Pastikan barang kembali dan pengembalian uang diselesaikan.')">
                        @csrf
                        <input name="reason" required maxlength="255" class="field"
                            placeholder="Alasan pembatalan (wajib)">
                        <label class="block text-sm"><input type="checkbox" name="confirm_return" value="1" required>
                            Barang sudah kembali/masih tersedia dan pengembalian uang telah diselesaikan bila sebelumnya
                            diterima.</label>
                        <button class="rounded bg-red-700 px-4 py-2 text-white">Batalkan seluruh transaksi</button>
                    </form>
                @else<p class="mt-3 text-sm text-slate-500">Shift sudah ditutup; pembatalan lintas shift tidak didukung.
                    </p>
                @endif
        </article>@empty<p>Belum ada penjualan pada periode ini.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $sales->links() }}</div>
@endsection
