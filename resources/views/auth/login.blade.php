@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    <div class="mx-auto max-w-md rounded-xl bg-white p-6 shadow">
        <h1 class="text-xl font-semibold text-slate-900">Masuk ke Sistem POS</h1>
        <p class="mt-1 text-sm text-slate-500">Gunakan akun yang diberikan admin.</p>

        <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Kata sandi</label>
                <input id="password" name="password" type="password" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                Ingat saya
            </label>

            <button class="w-full rounded-md bg-brand-700 px-4 py-2 font-medium text-white hover:bg-brand-800">Masuk</button>
        </form>
    </div>
@endsection
