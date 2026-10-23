<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#146c43">
    <title>@yield('title', 'POS Koperasi Desa') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/responsive-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cashier-pages.css') }}">
</head>

<body class="kopdes-app">
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    @auth
        @php($me = auth()->user())
        <div class="app-shell">
            <aside id="app-sidebar" class="app-sidebar no-print" aria-label="Menu utama">
                <div class="sidebar-brand"><a href="{{ route('dashboard') }}" class="brand-lockup"><span>Kopdes<span
                                class="brand-subtitle">Point of Sale</span></span></a><button type="button"
                        data-sidebar-close class="icon-button drawer-close" aria-label="Tutup menu"><x-icon
                            name="x" /></button></div>
                <div class="shop-label"><span class="status-dot"></span><span>{{ $me->branch->name }}</span></div>
                <nav class="sidebar-navigation">
                    @if ($me->isKasir())
                        <p class="nav-heading">Ruang kasir</p>
                        @foreach ([['pos.index', 'Kasir & shift', 'calculator'], ['pos.products', 'Daftarkan barcode', 'box'], ['pos.history', 'Riwayat transaksi', 'receipt']] as [$target, $label, $icon])
                            <a class="nav-link {{ request()->routeIs($target) ? 'is-active' : '' }}" href="{{ route($target) }}"
                                @if (request()->routeIs($target)) aria-current="page" @endif><x-icon
                                    name="{{ $icon }}" /><span>{{ $label }}</span></a>
                        @endforeach
                    @else
                        @foreach ([['Ringkasan', [['dashboard', 'Dasbor', 'home'], ['sales.index', 'Penjualan', 'receipt']]], ['Kelola usaha', [['products.index', 'Barang', 'box'], ['categories.index', 'Kategori', 'layers'], ['suppliers.index', 'Pemasok', 'truck'], ['purchases.index', 'Penerimaan barang', 'clipboard'], ['users.index', 'Akun kasir', 'users']]], ['Laporan', [['reports.daily', 'Penjualan harian', 'chart'], ['reports.monthly', 'Penjualan bulanan', 'chart'], ['reports.profit', 'Laba kotor', 'calculator'], ['reports.stock', 'Persediaan', 'box'], ['reports.stock-card', 'Kartu stok', 'clipboard'], ['reports.shift', 'Rekap shift', 'clock']]]] as [$heading, $items])
                            <p class="nav-heading">{{ $heading }}</p>
                            @foreach ($items as [$target, $label, $icon])
                                @php($active = request()->routeIs($target) || ($target === 'products.index' && request()->routeIs('products.*')) || ($target === 'purchases.index' && request()->routeIs('purchases.*')) || ($target === 'users.index' && request()->routeIs('users.*')) || ($target === 'categories.index' && request()->routeIs('categories.*')) || ($target === 'suppliers.index' && request()->routeIs('suppliers.*')))
                                <a class="nav-link {{ $active ? 'is-active' : '' }}" href="{{ route($target) }}"
                                    @if ($active) aria-current="page" @endif><x-icon
                                        name="{{ $icon }}" /><span>{{ $label }}</span></a>
                            @endforeach
                        @endforeach
                    @endif
                </nav>
                <div class="sidebar-account">
                    <div class="avatar">{{ mb_substr($me->name, 0, 1) }}</div>
                    <div class="account-copy"><strong>{{ $me->name }}</strong><span>{{ $me->role->label() }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button"
                            aria-label="Keluar dari akun" title="Keluar"><x-icon name="logout" /></button></form>
                </div>
            </aside>
            <div id="app-sidebar-overlay" class="sidebar-overlay no-print" hidden></div>
            <div id="app-workspace" class="app-workspace">
                <header class="app-topbar no-print">
                    <div class="topbar-title"><button type="button" data-sidebar-open class="icon-button drawer-open"
                            aria-label="Buka menu" aria-controls="app-sidebar" aria-expanded="false"><x-icon
                                name="menu" /></button>
                        <div><span class="topbar-eyebrow">KOPERASI DESA</span>
                            <h1>@yield('title', 'Dasbor')</h1>
                        </div>
                    </div>
                    <div class="topbar-meta"><span class="today-label">{{ now()->translatedFormat('d M Y') }}</span><span
                            id="network-status" class="connection-tag"><span
                                class="status-dot"></span><span>Online</span></span></div>
                </header>
                <main id="main-content" class="app-page" tabindex="-1">
                    @if (session('status'))
                        <div class="notice notice-success" role="status"><x-icon name="check" />
                            <div>{{ session('status') }}</div>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="notice notice-error" role="alert"><x-icon name="alert" />
                            <div><strong>Periksa kembali data Anda.</strong>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                    @yield('content')
                    <footer class="page-footer no-print"><span>Kopdes · Sistem POS satu toko</span><span>Data tersimpan di
                            server, bukan di perangkat kasir.</span></footer>
                </main>
            </div>
            <nav class="mobile-shortcuts no-print" aria-label="Akses cepat">
                @if ($me->isKasir())
                    @foreach ([['pos.index', 'Kasir', 'calculator'], ['pos.products', 'Barcode', 'box'], ['pos.history', 'Riwayat', 'receipt']] as [$target, $label, $icon])
                        <a href="{{ route($target) }}" class="{{ request()->routeIs($target) ? 'is-active' : '' }}"
                            @if (request()->routeIs($target)) aria-current="page" @endif><x-icon
                                name="{{ $icon }}" /><span>{{ $label }}</span></a>
                    @endforeach
                @else
                    @foreach ([['dashboard', 'Beranda', 'home'], ['products.index', 'Barang', 'box'], ['sales.index', 'Penjualan', 'receipt'], ['reports.daily', 'Laporan', 'chart']] as [$target, $label, $icon])
                        <a href="{{ route($target) }}" class="{{ request()->routeIs($target) ? 'is-active' : '' }}"><x-icon
                                name="{{ $icon }}" /><span>{{ $label }}</span></a>
                    @endforeach
                @endif
            </nav>
        </div>
    @else
        <main id="main-content" tabindex="-1">@yield('content')</main>
    @endauth
    <script src="{{ asset('js/app-shell.js') }}"></script>
    <script src="{{ asset('js/table-pagination.js') }}"></script>
    @stack('scripts')
</body>

</html>
