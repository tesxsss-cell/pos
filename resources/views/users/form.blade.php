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
                    <label class="block text-sm font-medium text-slate-700" for="field-1">Nama lengkap</label>
<input id="field-1" name="name" value="{{ old('name', $user->name) }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-2">Email (untuk masuk)</label>
<input id="field-2" name="email" type="email" value="{{ old('email', $user->email) }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-3">Peran</label>
<select id="field-3" name="role" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="field-4">Telepon</label>
<input id="field-4" name="phone" value="{{ old('phone', $user->phone) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-5">
                        Kata sandi {{ $user->exists ? '(kosongkan bila tidak diubah)' : '' }}
                    </label>
<input id="field-5" name="password" type="password" {{ $user->exists ? '' : 'required' }}
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="field-6">Ulangi kata sandi</label>
<input id="field-6" name="password_confirmation" type="password" {{ $user->exists ? '' : 'required' }}
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded border-slate-300">
                Akun aktif dan dapat masuk ke sistem
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button class="rounded-md bg-brand-700 hover:bg-brand-800 px-4 py-2 font-medium text-white">Simpan</button>
                <a href="{{ route('users.index') }}" class="text-sm text-slate-600 hover:underline">Batal</a>
            </div>
        </form>
    </div>
@endsection
