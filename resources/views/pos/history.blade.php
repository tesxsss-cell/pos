@extends('layouts.app')
@section('title', 'Riwayat Transaksi')
@section('content')
    <section class="page-heading">
        <div><span class="eyebrow">RUANG KASIR</span>
            <h2>Riwayat transaksi saya</h2>
            <p>Hanya transaksi milik akun Anda di toko ini. Transaksi tersimpan hanya dapat dilihat dan dicetak, tidak dapat
                dibatalkan melalui aplikasi.</p>
        </div><a class="btn-secondary" href="{{ route('pos.index') }}">Ke kasir</a>
    </section>
    <form method="GET" action="{{ route('pos.history') }}" class="panel cashier-history-filter"><label>Dari tanggal<input
                name="dari" type="date" class="field" value="{{ $from }}"></label><label>Sampai tanggal<input
                name="sampai" type="date" class="field" value="{{ $to }}"></label><label>Status<select
                name="status" class="field">
                <option value="">Semua status</option>
                <option value="selesai" @selected($status === 'selesai')>Selesai</option>
                <option value="dibatalkan" @selected($status === 'dibatalkan')>Dibatalkan (data lama)</option>
            </select></label><button class="btn-primary">Tampilkan</button><a href="{{ route('pos.history') }}"
            class="text-link">Reset</a></form>
    <section class="panel cashier-history-panel">
        <div class="overflow-x-auto">
            <table data-server-table>
                <thead>
                    <tr>
                        <th>No. struk</th>
                        <th>Waktu</th>
                        <th>Pelanggan</th>
                        <th>Pembayaran</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr>
                            <td>{{ $sale->invoice_no }}</td>
                            <td>{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $sale->customer_name ?: 'Umum' }}</td>
                            <td>{{ ucfirst($sale->payment_method->value) }}</td>
                            <td class="text-right">Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</td>
                            <td><span
                                    class="state-tag {{ $sale->status->value === 'selesai' ? 'is-open' : '' }}">{{ $sale->status->label() }}</span>
                            </td>
                            <td><a class="text-link" href="{{ route('pos.receipt', $sale) }}" target="_blank"
                                    rel="noopener">Lihat struk</a></td>
                    </tr>@empty<tr>
                            <td colspan="7">Belum ada transaksi yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>{{ $sales->links() }}
    </section>
@endsection
