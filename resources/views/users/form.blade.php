@extends('layouts.app')

@section('title', $user->exists ? 'Ubah Akun' : 'Tambah Akun')

@section('content')
    <div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
        <h1 class="text-lg font-semibold text-slate-900">{{ $user->exists ? 'Ubah akun pengguna' : 'Tambah akun pengguna' }}</h1>

        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="mt-6 space-y-4">
            @csrf
            @if ($user->exists)
                @method('PUT')
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nama lengkap</label>
                    <input name="name" value="{{ old('name', $user->name) }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email (untuk masuk)</label>
                    <input name="email" type="email" value="{{ old('email', $user->email) }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Peran</label>
                    <select name="role" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Penempatan lokasi</label>
                    <select name="branch_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <option value="">Pusat (tanpa cabang)</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $user->branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Wajib dipilih untuk peran manager cabang dan kasir.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Telepon</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        Kata sandi {{ $user->exists ? '(kosongkan bila tidak diubah)' : '' }}
                    </label>
                    <input name="password" type="password" {{ $user->exists ? '' : 'required' }}
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Ulangi kata sandi</label>
                    <input name="password_confirmation" type="password" {{ $user->exists ? '' : 'required' }}
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded border-slate-300">
                Akun aktif dan dapat masuk ke sistem
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button class="rounded-md bg-slate-900 px-4 py-2 font-medium text-white">Simpan</button>
                <a href="{{ route('users.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </form>
    </div>
@endsection
