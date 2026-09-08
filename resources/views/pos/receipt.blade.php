<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $sale->invoice_no }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; padding: 12px; width: 300px; }
        h1 { font-size: 14px; margin: 0 0 2px; text-align: center; }
        p, td, th { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        .center { text-align: center; }
        .right { text-align: right; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .muted { color: #444; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h1>{{ $sale->branch->name }}</h1>
    <p class="center muted">{{ $sale->branch->address ?? '-' }}<br>{{ $sale->branch->phone }}</p>
    <div class="line"></div>

    <p>
        No: {{ $sale->invoice_no }}<br>
        Tanggal: {{ $sale->sold_at?->format('d/m/Y H:i') }}<br>
        Kasir: {{ $sale->cashier->name }}<br>
        Pelanggan: {{ $sale->customer_name ?? 'Umum' }}
        @if (! $sale->isCompleted())
            <br><strong>** TRANSAKSI DIBATALKAN **</strong>
        @endif
    </p>

    <div class="line"></div>

    <table>
        @foreach ($sale->items as $item)
            <tr>
                <td colspan="2">{{ $item->product_name }}</td>
            </tr>
            <tr>
                <td class="muted">{{ $item->quantity }} x {{ number_format((float) $item->unit_price, 0, ',', '.') }}@if ((float) $item->discount > 0) - {{ number_format((float) $item->discount, 0, ',', '.') }}@endif</td>
                <td class="right">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="line"></div>

    <table>
        <tr><td>Subtotal</td><td class="right">{{ number_format((float) $sale->subtotal, 0, ',', '.') }}</td></tr>
        <tr><td>Diskon</td><td class="right">{{ number_format((float) $sale->discount, 0, ',', '.') }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="right"><strong>{{ number_format((float) $sale->total, 0, ',', '.') }}</strong></td></tr>
        <tr><td>{{ $sale->payment_method->label() }}</td><td class="right">{{ number_format((float) $sale->paid_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Kembali</td><td class="right">{{ number_format((float) $sale->change_amount, 0, ',', '.') }}</td></tr>
    </table>

    <div class="line"></div>
    <p class="center">Terima kasih atas kunjungan Anda</p>

    <p class="center no-print">
        <button onclick="window.print()">Cetak struk</button>
    </p>
</body>
</html>
