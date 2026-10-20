@extends('layouts.app')

@section('title', $supplier->exists ? 'Ubah Pemasok' : 'Tambah Pemasok')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
        <h1 class="text-lg font-semibold text-slate-900">{{ $supplier->exists ? 'Ubah data pemasok' : 'Tambah pemasok baru' }}</h1>

        <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="mt-6 space-y-4">
            @csrf
            @if ($supplier->exists)
                @method('PUT')
            @endif

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-1">Kode pemasok</label>
<input id="field-1" name="code" value="{{ old('code', $supplier->code) }}" placeholder="SUP-001"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    <p class="mt-1 text-xs text-slate-400">Kosongkan untuk otomatis.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700" for="field-2">Nama pemasok</label>
<input id="field-2" name="name" value="{{ old('name', $supplier->name) }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-3">Nama kontak</label>
<input id="field-3" name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-4">Telepon</label>
<input id="field-4" name="phone" value="{{ old('phone', $supplier->phone) }}"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="field-5">Email</label>
<input id="field-5" name="email" type="email" value="{{ old('email', $supplier->email) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="field-6">Alamat</label>
<textarea id="field-6" name="address" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">{{ old('address', $supplier->address) }}</textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true)) class="rounded border-slate-300">
                Pemasok aktif
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 font-medium text-white">Simpan</button>
                <a href="{{ route('suppliers.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </form>
    </div>
@endsection
