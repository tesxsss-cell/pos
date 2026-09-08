@extends('layouts.app')

@section('title', $branch->exists ? 'Ubah Lokasi' : 'Tambah Lokasi')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
        <h1 class="text-lg font-semibold text-slate-900">{{ $branch->exists ? 'Ubah data lokasi' : 'Tambah gudang / cabang' }}</h1>

        <form method="POST" action="{{ $branch->exists ? route('branches.update', $branch) : route('branches.store') }}" class="mt-6 space-y-4">
            @csrf
            @if ($branch->exists)
                @method('PUT')
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Kode lokasi</label>
                    <input name="code" value="{{ old('code', $branch->code) }}" required placeholder="CB01"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    <p class="mt-1 text-xs text-slate-500">Dipakai sebagai awalan nomor struk, contoh INV-CB01-260908-0001.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Jenis lokasi</label>
                    <select name="type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <option value="cabang" @selected(old('type', $branch->type) === 'cabang')>Cabang / toko</option>
                        <option value="gudang" @selected(old('type', $branch->type) === 'gudang')>Gudang pusat</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Nama lokasi</label>
                <input name="name" value="{{ old('name', $branch->name) }}" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Telepon</label>
                <input name="phone" value="{{ old('phone', $branch->phone) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Alamat</label>
                <textarea name="address" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">{{ old('address', $branch->address) }}</textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active ?? true)) class="rounded border-slate-300">
                Lokasi aktif
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button class="rounded-md bg-slate-900 px-4 py-2 font-medium text-white">Simpan</button>
                <a href="{{ route('branches.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </form>
    </div>
@endsection
