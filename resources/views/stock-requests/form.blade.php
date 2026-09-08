@extends('layouts.app')

@section('title', 'Buat Request Stok')

@section('content')
    <form method="POST" action="{{ route('stock-requests.store') }}" class="mx-auto max-w-3xl space-y-4">
        @csrf

        <div class="rounded-xl bg-white p-6 shadow">
            <h1 class="text-lg font-semibold text-slate-900">Request stok ke gudang pusat</h1>
            <p class="text-xs text-slate-500">Cabang: {{ auth()->user()->branch->name ?? '-' }}</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Catatan / alasan permintaan</label>
                <input name="note" value="{{ old('note') }}" placeholder="contoh: persiapan stok akhir pekan"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Barang yang diminta</h2>
                <button type="button" id="tambah-baris" class="rounded-md bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700">+ Tambah baris</button>
            </div>

            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2">Barang</th>
                        <th class="py-2 w-28 text-center">Jumlah</th>
                        <th class="py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="baris" class="divide-y divide-slate-100"></tbody>
            </table>

            <div class="mt-4 flex items-center gap-3">
                <button class="rounded-md bg-slate-900 px-4 py-2 font-medium text-white">Kirim request</button>
                <a href="{{ route('stock-requests.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </div>
    </form>

    <template id="template-baris">
        <tr>
            <td class="py-2">
                <select name="items[__i__][product_id]" required class="w-full rounded border border-slate-300 px-2 py-1.5">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
            </td>
            <td class="py-2 text-center">
                <input name="items[__i__][quantity_requested]" type="number" min="1" value="1" required
                       class="w-24 rounded border border-slate-300 px-2 py-1.5 text-center">
            </td>
            <td class="py-2 text-right"><button type="button" data-hapus class="text-red-600">&times;</button></td>
        </tr>
    </template>

    <script>
        let indeks = 0;
        const tbody = document.getElementById('baris');

        function tambahBaris() {
            tbody.insertAdjacentHTML('beforeend', document.getElementById('template-baris').innerHTML.replaceAll('__i__', indeks++));
        }

        document.getElementById('tambah-baris').addEventListener('click', tambahBaris);
        tbody.addEventListener('click', (event) => {
            if (event.target.dataset.hapus !== undefined) {
                event.target.closest('tr').remove();
            }
        });

        tambahBaris();
    </script>
@endsection
