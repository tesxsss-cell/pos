@extends('layouts.app')

@section('title', 'Buat Penerimaan Barang')

@section('content')
    <form method="POST" action="{{ route('purchases.store') }}" class="space-y-4">
        @csrf

        <div class="rounded-xl bg-white p-6 shadow">
            <h1 class="text-lg font-semibold text-slate-900">Dokumen penerimaan barang</h1>
            <p class="text-xs text-slate-500">Harga beli per baris menjadi harga pokok penerimaan tersebut. Dokumen disimpan
                sebagai draft dahulu.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-1">Pemasok</label>
                    <select id="field-1" name="supplier_id" required
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-2">Tanggal penerimaan</label>
                    <input id="field-2" name="purchase_date" type="date" required
                        value="{{ old('purchase_date', now()->toDateString()) }}"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700" for="field-3">Catatan</label>
                <input id="field-3" name="note" value="{{ old('note') }}"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Rincian barang</h2>
                <button type="button" id="tambah-baris"
                    class="rounded-md bg-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700">+ Tambah baris</button>
            </div>

            <div class="table-scroll purchase-items">
                <table class="mt-3 w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2">Barang</th>
                            <th class="py-2 w-24 text-center">Jumlah</th>
                            <th class="py-2 w-36 text-right">Harga beli / unit</th>
                            <th class="py-2 w-32 text-right">Subtotal</th>
                            <th class="py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody id="baris" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <div class="mt-3 flex justify-end border-t border-slate-200 pt-3 text-sm">
                <span class="mr-3 text-slate-500">Total nilai penerimaan</span>
                <span id="total" class="font-semibold">Rp 0</span>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 font-medium text-white">Simpan sebagai
                    draft</button>
                <a href="{{ route('purchases.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </div>
    </form>

    <template id="template-baris">
        <tr>
            <td data-label="Barang" class="py-2">
                <select aria-label="Barang penerimaan" name="items[__i__][product_id]" required
                    class="w-full rounded border border-slate-300 px-2 py-1.5">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
            </td>
            <td data-label="Jumlah" class="py-2 text-center">
                <input aria-label="Jumlah penerimaan" name="items[__i__][quantity]" type="number" min="1"
                    value="1" required data-qty class="w-20 rounded border border-slate-300 px-2 py-1.5 text-center">
            </td>
            <td data-label="Harga beli / unit" class="py-2 text-right">
                <input aria-label="Harga beli per unit" name="items[__i__][unit_cost]" type="number" min="0"
                    step="0.01" value="0" required data-cost
                    class="w-32 rounded border border-slate-300 px-2 py-1.5 text-right">
            </td>
            <td data-label="Subtotal" class="py-2 text-right" data-subtotal>Rp 0</td>
            <td data-label="Aksi" class="py-2 text-right"><button type="button" data-hapus
                    class="text-red-600">&times;</button></td>
        </tr>
    </template>

    <script>
        let indeks = 0;
        const tbody = document.getElementById('baris');
        const rupiah = (angka) => 'Rp ' + Number(angka || 0).toLocaleString('id-ID');

        function hitungTotal() {
            let total = 0;

            tbody.querySelectorAll('tr').forEach((tr) => {
                const qty = Number(tr.querySelector('[data-qty]').value || 0);
                const cost = Number(tr.querySelector('[data-cost]').value || 0);
                tr.querySelector('[data-subtotal]').textContent = rupiah(qty * cost);
                total += qty * cost;
            });

            document.getElementById('total').textContent = rupiah(total);
        }

        function tambahBaris() {
            const html = document.getElementById('template-baris').innerHTML.replaceAll('__i__', indeks++);
            tbody.insertAdjacentHTML('beforeend', html);
            hitungTotal();
        }

        document.getElementById('tambah-baris').addEventListener('click', tambahBaris);
        tbody.addEventListener('input', hitungTotal);
        tbody.addEventListener('click', (event) => {
            if (event.target.dataset.hapus !== undefined) {
                event.target.closest('tr').remove();
                hitungTotal();
            }
        });

        tambahBaris();
        const produkAwal = @json(request('product_id'));
        if (produkAwal) {
            tbody.querySelector('select').value = produkAwal;
            tbody.querySelector('[data-qty]').value = @json(request('quantity', 1));
            hitungTotal();
        }
    </script>
@endsection
