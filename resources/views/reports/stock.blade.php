@extends('layouts.app')
@section('title','Laporan Stok')
@section('content')
<div class="mb-4 flex gap-3 no-print"><button onclick="window.print()" class="btn-primary">Cetak laporan</button><a class="btn-primary" href="{{ route('reports.stock-card') }}">Kartu stok</a></div>
<p class="mb-4">Seluruh saldo barang. Peringatan jika stok ≤ stok minimum. Tidak ada pemesanan otomatis.</p>
<div class="overflow-x-auto rounded-xl bg-white p-4 shadow"><table data-server-table class="w-full text-left text-sm"><thead><tr><th>SKU / Barang</th><th>Stok</th><th>Minimum</th><th>HPP rata-rata</th><th>Nilai persediaan</th><th>Status</th></tr></thead><tbody>
@forelse($stocks as $stock)<tr class="border-t"><td class="py-3">{{ $stock->product->sku }} · {{ $stock->product->name }}</td><td>{{ $stock->quantity }} {{ $stock->product->unit }}</td><td>{{ $stock->effectiveMinStock() }}</td><td>Rp {{ number_format((float)$stock->avg_cost,4,',','.') }}</td><td>Rp {{ number_format($stock->quantity*(float)$stock->avg_cost,2,',','.') }}</td><td>{{ !$stock->product->is_active?'Nonaktif':($stock->quantity<=$stock->effectiveMinStock()?'Perlu restock':'Aman') }}</td></tr>
@empty<tr><td colspan="6" class="py-4">Belum ada barang.</td></tr>@endforelse
</tbody></table>{{ $stocks->links() }}</div>
@endsection
