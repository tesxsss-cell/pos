@extends('layouts.app')
@section('title','Dasbor')
@section('content')
<section class="page-heading"><div><span class="eyebrow">RINGKASAN USAHA</span><h2>Selamat datang, {{ auth()->user()->name }}.</h2><p>Pantau penjualan, persediaan, dan aktivitas toko Anda.</p></div><a class="btn-primary" href="{{ route('purchases.create') }}"><x-icon name="plus"/> Penerimaan barang</a></section>
<form method="GET" class="filter-bar"><div class="filter-title"><x-icon name="chart"/><span>Periode laporan</span></div><label for="dashboard-from">Dari tanggal<input id="dashboard-from" class="field" type="date" name="dari" value="{{ $from }}"></label><label for="dashboard-to">Sampai tanggal<input id="dashboard-to" class="field" type="date" name="sampai" value="{{ $to }}"></label><button class="btn-primary" type="submit">Terapkan</button></form>
<div class="metric-grid">
@foreach([['Penjualan',$summary['revenue'],'receipt','Transaksi selesai pada periode ini'],['Harga pokok penjualan',$summary['cogs'],'box','Menggunakan Moving Average'],['Laba kotor',$summary['gross_profit'],'chart','Penjualan dikurangi HPP'],['Nilai persediaan',$inventoryValue,'layers','Nilai stok toko saat ini']] as [$label,$value,$icon,$note])
<section class="metric-card"><div class="metric-top"><span>{{ $label }}</span><span class="metric-icon"><x-icon name="{{ $icon }}"/></span></div><strong class="metric-value">Rp {{ number_format($value,0,',','.') }}</strong><p>{{ $note }}</p></section>
@endforeach
</div>
<div class="activity-strip"><span><x-icon name="receipt"/><strong>{{ $summary['transactions'] }}</strong> transaksi selesai</span><span><x-icon name="box"/><strong>{{ $summary['items_sold'] }}</strong> unit terjual</span><span>Transaksi dibatalkan tidak masuk perhitungan.</span></div>
<div class="dashboard-grid">
<section class="panel"><div class="panel-heading"><div><h3>Perlu perhatian</h3><p>Barang yang mencapai batas stok minimum.</p></div><span class="count-tag {{ $lowStocks->count()?'is-warning':'' }}">{{ $lowStocks->count() }} barang</span></div>
<div class="stock-attention-list">@forelse($lowStocks->take(6) as $stock)<div class="stock-attention-item"><div class="product-avatar"><x-icon name="box"/></div><div class="item-copy"><strong>{{ $stock->product->name }}</strong><span>Minimum {{ $stock->effectiveMinStock() }} {{ $stock->product->unit }}</span></div><span class="stock-quantity">{{ $stock->quantity }} <small>{{ $stock->product->unit }}</small></span></div>
@empty<div class="empty-state"><span class="empty-icon"><x-icon name="check"/></span><h4>Persediaan masih aman</h4><p>Belum ada barang aktif yang mencapai stok minimum.</p></div>@endforelse</div><a class="panel-link" href="{{ route('reports.stock') }}">Lihat seluruh persediaan <x-icon name="arrow"/></a></section>
<section class="panel"><div class="panel-heading"><div><h3>Akses cepat</h3><p>Pekerjaan harian, lebih ringkas.</p></div></div><div class="quick-actions">
@foreach([['products.create','Tambah barang','Lengkapi katalog toko','box'],['purchases.create','Catat penerimaan','Perbarui stok dan HPP','truck'],['sales.index','Riwayat penjualan','Periksa struk dan pembatalan','receipt'],['reports.daily','Laporan penjualan','Lihat hasil usaha per periode','chart']] as [$route,$title,$desc,$icon])<a href="{{ route($route) }}"><span class="action-icon"><x-icon name="{{ $icon }}"/></span><span><strong>{{ $title }}</strong><small>{{ $desc }}</small></span><x-icon name="arrow"/></a>@endforeach
</div></section>
</div>
<section class="panel mt-6"><div class="panel-heading"><div><h3>Aktivitas shift kasir</h3><p>Rekap sesi kerja dan pertanggungjawaban kas.</p></div><a class="text-link" href="{{ route('reports.shift') }}">Lihat semua <x-icon name="arrow"/></a></div><div class="table-scroll">@php($shiftRows=\App\Support\TablePage::make($shifts, 'shift_page'))<table data-server-table><thead><tr><th>Kasir</th><th>Waktu buka</th><th>Status</th><th>Kas sistem</th><th>Selisih kas</th></tr></thead><tbody>
@forelse($shiftRows as $shift)<tr><td><strong>{{ $shift->user->name }}</strong></td><td>{{ $shift->opened_at->format('d M Y, H:i') }}</td><td><span class="state-tag {{ $shift->isOpen()?'is-open':'' }}">{{ $shift->isOpen()?'Berlangsung':'Ditutup' }}</span></td><td>Rp {{ number_format((float)$shift->expected_cash,0,',','.') }}</td><td>{{ $shift->isOpen()?'Belum dihitung':'Rp '.number_format((float)$shift->difference,0,',','.') }}</td></tr>@empty<tr><td colspan="5"><div class="table-empty"><x-icon name="clock"/><span>Belum ada shift pada periode ini.</span></div></td></tr>@endforelse
</tbody></table>{{ $shiftRows->links() }}</div></section>
<p class="report-disclaimer">Laporan ini menampilkan laba kotor toko, bukan laba bersih atau SHU koperasi.</p>
@endsection
