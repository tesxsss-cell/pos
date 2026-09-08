@extends('layouts.app')

@section('title', 'Buat Mutasi Stok')

@section('content')
    <form method="POST" action="{{ route('transfers.store') }}" class="space-y-4">
        @csrf

        <div class="rounded-xl bg-white p-6 shadow">
            <h1 class="text-lg font-semibold text-slate-900">Dokumen mutasi stok</h1>
            <p class="text-xs text-slate-500">Pilih request stok yang sudah disetujui, atau susun daftar barang secara manual.</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Dasar dokumen</label>
                <select name="stock_request_id" id="request" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    <option value="">Manual (tanpa request stok)</option>
                    @foreach ($requests as $stockRequest)
                        <option value="{{ $stockRequest->id }}">
                            {{ $stockRequest->code }} &middot; {{ $stockRequest->branch->name ?? '-' }} ({{ $stockRequest->items->count() }} item)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Kirim dari</label>
                    <select name="from_branch_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('from_branch_id') == $branch->id || $branch->type === 'gudang')>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Kirim ke</label>
                    <select name="to_branch_id" id="tujuan" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('to_branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Catatan</label>
                <input name="note" value="{{ old('note') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>
        </div>

        <div id="kotak-manual" class="rounded-xl bg-white p-6 shadow">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Barang yang dikirim</h2>
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

            <p id="info-request" class="mt-3 hidden rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600"></p>
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-md bg-slate-900 px-4 py-2 font-medium text-white">Simpan dokumen</button>
            <a href="{{ route('transfers.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
        </div>
    </form>

    <template id="template-baris">
        <tr>
            <td class="py-2">
                <select name="items[__i__][product_id]" class="w-full rounded border border-slate-300 px-2 py-1.5">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
            </td>
            <td class="py-2 text-center">
                <input name="items[__i__][quantity]" type="number" min="1" value="1"
                       class="w-24 rounded border border-slate-300 px-2 py-1.5 text-center">
            </td>
            <td class="py-2 text-right"><button type="button" data-hapus class="text-red-600">&times;</button></td>
        </tr>
    </template>

    <script>
        const daftarRequest = @json($requests->mapWithKeys(fn ($r) => [$r->id => [
            'kode' => $r->code,
            'cabang_id' => $r->branch_id,
            'cabang' => $r->branch->name ?? '-',
            'items' => $r->items->map(fn ($i) => [
                'nama' => $i->product->name ?? '-',
                'jumlah' => $i->quantity_approved ?? $i->quantity_requested,
            ]),
        ]]));

        let indeks = 0;
        const tbody = document.getElementById('baris');
        const info = document.getElementById('info-request');

        function tambahBaris() {
            tbody.insertAdjacentHTML('beforeend', document.getElementById('template-baris').innerHTML.replaceAll('__i__', indeks++));
        }

        function terapkanRequest() {
            const id = document.getElementById('request').value;
            const data = daftarRequest[id];

            if (! data) {
                info.classList.add('hidden');
                tbody.closest('#kotak-manual').querySelector('#tambah-baris').disabled = false;
                tbody.querySelectorAll('select, input').forEach((f) => f.disabled = false);
                return;
            }

            document.getElementById('tujuan').value = data.cabang_id;
            info.textContent = 'Jumlah barang diambil otomatis dari ' + data.kode + ' untuk ' + data.cabang + ': '
                + data.items.map((i) => i.nama + ' (' + i.jumlah + ')').join(', ');
            info.classList.remove('hidden');
            tbody.innerHTML = '';
        }

        document.getElementById('tambah-baris').addEventListener('click', tambahBaris);
        document.getElementById('request').addEventListener('change', terapkanRequest);
        tbody.addEventListener('click', (event) => {
            if (event.target.dataset.hapus !== undefined) {
                event.target.closest('tr').remove();
            }
        });

        tambahBaris();
    </script>
@endsection
