@extends('layouts.app')

@section('title', $category->exists ? 'Ubah Kategori' : 'Tambah Kategori')

@section('content')
    <div class="mx-auto max-w-xl rounded-xl bg-white p-6 shadow">
        <h1 class="text-lg font-semibold text-slate-900">{{ $category->exists ? 'Ubah kategori' : 'Tambah kategori baru' }}</h1>

        <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" class="mt-6 space-y-4">
            @csrf
            @if ($category->exists)
                @method('PUT')
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700">Kode kategori</label>
                <input name="code" value="{{ old('code', $category->code) }}" placeholder="KAT-MNM"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                <p class="mt-1 text-xs text-slate-400">Kosongkan untuk dibuat otomatis dari nama kategori.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Nama kategori</label>
                <input name="name" value="{{ old('name', $category->name) }}" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Keterangan</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">{{ old('description', $category->description) }}</textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="rounded border-slate-300">
                Kategori aktif
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button class="rounded-md bg-slate-900 px-4 py-2 font-medium text-white">Simpan</button>
                <a href="{{ route('categories.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </form>
    </div>
@endsection
