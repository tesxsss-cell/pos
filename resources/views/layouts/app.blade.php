<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>@yield('title', 'POS Multi-Cabang') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
@auth
    @php($me = auth()->user())
    <header class="bg-white shadow">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div>
                <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-slate-900">POS Multi-Cabang FIFO</a>
                <p class="text-xs text-slate-500">
                    {{ $me->name }} &middot; {{ $me->role->label() }}
                    @if ($me->branch) &middot; {{ $me->branch->name }} @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span id="lencana-jaringan" class="hidden rounded-full px-3 py-1 text-xs font-medium"></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Keluar</button>
                </form>
            </div>
        </div>
        <nav class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto flex max-w-7xl flex-wrap gap-1 px-4 py-2 text-sm">
                @php($link = 'rounded-md px-3 py-1.5 text-slate-600 hover:bg-white hover:text-slate-900')
                <a class="{{ $link }}" href="{{ route('dashboard') }}">Dasbor</a>

                @if ($me->hasRole('kasir', 'admin', 'manager_cabang'))
                    <a class="{{ $link }}" href="{{ route('pos.index') }}">Kasir</a>
                @endif

                @if ($me->isCentral())
                    <a class="{{ $link }}" href="{{ route('products.index') }}">Barang</a>
                    <a class="{{ $link }}" href="{{ route('categories.index') }}">Kategori</a>
                    <a class="{{ $link }}" href="{{ route('suppliers.index') }}">Pemasok</a>
                    <a class="{{ $link }}" href="{{ route('branches.index') }}">Cabang</a>
                    <a class="{{ $link }}" href="{{ route('users.index') }}">Akun</a>
                    <a class="{{ $link }}" href="{{ route('purchases.index') }}">Penerimaan</a>
                @endif

                @if ($me->hasRole('manager_cabang', 'admin', 'pemilik'))
                    <a class="{{ $link }}" href="{{ route('stock-requests.index') }}">Request Stok</a>
                    <a class="{{ $link }}" href="{{ route('transfers.index') }}">Mutasi Stok</a>
                    <a class="{{ $link }}" href="{{ route('reports.stock') }}">Laporan Stok</a>
                    <a class="{{ $link }}" href="{{ route('reports.daily') }}">Laporan Harian</a>
                @endif
            </div>
        </nav>
    </header>
@endauth

<main class="mx-auto max-w-7xl px-4 py-6">
    @if (session('status'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<script>
    // Penanda status jaringan pada bilah atas (dipakai semua halaman).
    (function () {
        var lencana = document.getElementById('lencana-jaringan');

        function gambar(online, antrian) {
            if (! lencana) {
                return;
            }

            lencana.classList.remove('hidden');

            if (online) {
                lencana.className = 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700';
                lencana.textContent = antrian > 0 ? 'Online - ' + antrian + ' transaksi menunggu kirim' : 'Online';
            } else {
                lencana.className = 'rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700';
                lencana.textContent = antrian > 0 ? 'Offline - ' + antrian + ' transaksi tersimpan lokal' : 'Mode offline';
            }
        }

        var jumlahAntrian = 0;

        window.addEventListener('pos-offline:status', function (event) {
            jumlahAntrian = event.detail.antrian || 0;
            gambar(event.detail.online, jumlahAntrian);
        });

        window.addEventListener('pos-offline:antrian', function (event) {
            jumlahAntrian = event.detail.jumlah || 0;
            gambar(navigator.onLine, jumlahAntrian);
        });

        window.addEventListener('online', function () {
            gambar(true, jumlahAntrian);
        });
        window.addEventListener('offline', function () {
            gambar(false, jumlahAntrian);
        });

        gambar(navigator.onLine, 0);
    })();

    // Pendaftaran service worker agar halaman tetap terbuka saat jaringan mati.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function (error) {
                console.warn('Service worker gagal didaftarkan:', error.message);
            });
        });
    }
</script>
</body>
</html>
