<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1d4ed8">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>@yield('title', 'POS Multi-Cabang') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- SRS Look & Feel: warna dominan biru, aksen putih & abu-abu. --}}
<body class="min-h-screen bg-slate-100 text-slate-800">
@auth
    @php($me = auth()->user())
    @php($nav = fn ($active) => 'app-sidebar-link'.($active ? ' app-sidebar-link-active' : ''))

    <div class="flex min-h-screen">
        {{-- ============ SRS Look & Feel #3: Sidebar kiri ============ --}}
        <aside id="app-sidebar"
               class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-brand-700 text-white transition-transform lg:static lg:translate-x-0">
            <div class="flex items-center justify-between px-4 py-4">
                <a href="{{ route('dashboard') }}" class="text-base font-semibold tracking-tight">POS Multi-Cabang FIFO</a>
                <button type="button" data-sidebar-close class="rounded-md p-1 text-brand-100 hover:bg-brand-800 lg:hidden" aria-label="Tutup menu">&times;</button>
            </div>

            <div class="mx-3 mb-2 rounded-lg bg-brand-800/60 px-3 py-2">
                <p class="text-sm font-medium">{{ $me->name }}</p>
                <p class="text-xs text-brand-200">
                    {{ $me->role->label() }}@if ($me->branch) &middot; {{ $me->branch->name }} @endif
                </p>
            </div>

            {{-- SRS Look & Feel #4: menu ditampilkan sesuai role pengguna. --}}
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                <a class="{{ $nav(request()->routeIs('dashboard')) }}" href="{{ route('dashboard') }}">Dasbor</a>

                @if ($me->hasRole('kasir', 'admin', 'manager_cabang'))
                    <p class="app-sidebar-heading">Penjualan</p>
                    <a class="{{ $nav(request()->routeIs('pos.index')) }}" href="{{ route('pos.index') }}">Kasir</a>
                    <a class="{{ $nav(request()->routeIs('pos.products')) }}" href="{{ route('pos.products') }}">Daftar Produk</a>
                @endif

                @if ($me->isCentral())
                    <p class="app-sidebar-heading">Data Induk</p>
                    <a class="{{ $nav(request()->routeIs('products.*')) }}" href="{{ route('products.index') }}">Barang</a>
                    <a class="{{ $nav(request()->routeIs('categories.*')) }}" href="{{ route('categories.index') }}">Kategori</a>
                    <a class="{{ $nav(request()->routeIs('suppliers.*')) }}" href="{{ route('suppliers.index') }}">Pemasok</a>
                    <a class="{{ $nav(request()->routeIs('branches.*')) }}" href="{{ route('branches.index') }}">Cabang</a>
                    <a class="{{ $nav(request()->routeIs('users.*')) }}" href="{{ route('users.index') }}">Akun</a>
                    <a class="{{ $nav(request()->routeIs('purchases.*')) }}" href="{{ route('purchases.index') }}">Penerimaan</a>
                @endif

                @if ($me->hasRole('manager_cabang', 'admin', 'pemilik'))
                    <p class="app-sidebar-heading">Persediaan &amp; Laporan</p>
                    <a class="{{ $nav(request()->routeIs('stock-requests.*')) }}" href="{{ route('stock-requests.index') }}">Request Stok</a>
                    <a class="{{ $nav(request()->routeIs('transfers.*')) }}" href="{{ route('transfers.index') }}">Mutasi Stok</a>
                    <a class="{{ $nav(request()->routeIs('reports.stock')) }}" href="{{ route('reports.stock') }}">Laporan Stok</a>
                    <a class="{{ $nav(request()->routeIs('reports.daily')) }}" href="{{ route('reports.daily') }}">Laporan Harian</a>
                @endif
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="border-t border-brand-800 p-3">
                @csrf
                <button class="w-full rounded-lg bg-brand-800 px-3 py-2 text-sm font-medium text-white hover:bg-brand-900">Keluar</button>
            </form>
        </aside>

        {{-- Lapisan gelap saat sidebar terbuka di layar kecil. --}}
        <div id="app-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"></div>

        {{-- ============ Area konten kanan ============ --}}
        <div class="flex min-h-screen flex-1 flex-col">
            <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                <div class="flex items-center gap-2">
                    <button type="button" data-sidebar-open class="rounded-md border border-slate-300 p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka menu">
                        <span class="block h-0.5 w-5 bg-current"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-current"></span>
                    </button>
                    <h1 class="text-base font-semibold text-slate-900">@yield('title', 'POS Multi-Cabang')</h1>
                </div>
                <span id="lencana-jaringan" class="hidden rounded-full px-3 py-1 text-xs font-medium"></span>
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
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
        </div>
    </div>
@else
    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @yield('content')
    </main>
@endauth

<script>
    // Buka/tutup sidebar pada layar kecil (SRS Look & Feel #3: tetap sidebar kiri).
    (function () {
        var sidebar = document.getElementById('app-sidebar');
        var overlay = document.getElementById('app-sidebar-overlay');
        if (! sidebar) { return; }

        function buka() { sidebar.classList.remove('-translate-x-full'); if (overlay) overlay.classList.remove('hidden'); }
        function tutup() { sidebar.classList.add('-translate-x-full'); if (overlay) overlay.classList.add('hidden'); }

        document.querySelectorAll('[data-sidebar-open]').forEach(function (b) { b.addEventListener('click', buka); });
        document.querySelectorAll('[data-sidebar-close]').forEach(function (b) { b.addEventListener('click', tutup); });
        if (overlay) { overlay.addEventListener('click', tutup); }
    })();

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
