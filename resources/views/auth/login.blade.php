@extends('layouts.app')
@section('title', 'Masuk ke akun')
@section('content')
    <div class="login-shell">
        <section class="login-story"><a class="brand-lockup" href="{{ route('login') }}"><span>Kopdes<span
                        class="brand-subtitle">Point of Sale</span></span></a>
            <div class="login-story-content"><span class="eyebrow">USAHA DESA, TERKELOLA LEBIH BAIK</span>
                <h1>Toko tertata.<br>Pelayanan lebih mudah.</h1>
                <p>Kelola barang, layani pelanggan, dan pantau usaha koperasi dalam satu tempat.</p>
                <div class="login-feature"><x-icon name="box" />
                    <div><strong>Persediaan lebih jelas</strong><span>Stok dan harga pokok tercatat setiap transaksi.</span>
                    </div>
                </div>
                <div class="login-feature"><x-icon name="shield" />
                    <div><strong>Tanggung jawab terjaga</strong><span>Akses Pengelola dan Kasir sesuai tugasnya.</span>
                    </div>
                </div>
            </div>
            <p class="login-story-footer">Dibangun untuk operasional toko koperasi desa.</p>
        </section>
        <section class="login-form-area">
            <div class="login-card">
                <div class="login-mobile-brand"><strong>Kopdes</strong></div><span class="eyebrow">SELAMAT DATANG
                    KEMBALI</span>
                <h2>Masuk ke akun Anda</h2>
                <p class="login-intro">Gunakan akun yang diberikan Pengelola untuk melanjutkan.</p>
                @if ($errors->any())
                    <div class="notice notice-error" role="alert"><x-icon name="alert" />
                        <div>
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif
                <form method="POST" action="{{ route('login.attempt') }}" class="login-form">@csrf
                    <label for="email">Alamat email</label><input id="email" name="email" type="email"
                        autocomplete="username" value="{{ old('email') }}" placeholder="nama@koperasi.id" required
                        autofocus>
                    <label for="password">Kata sandi</label>
                    <div class="password-field"><input id="password" name="password" type="password"
                            autocomplete="current-password" placeholder="Masukkan kata sandi" required><button
                            type="button" id="toggle-password" aria-controls="password"
                            aria-pressed="false">Tampilkan</button></div>
                    <div class="login-options"><label><input type="checkbox" name="remember" value="1" checked> Tetap
                            masuk</label><span>Akses sesuai peran</span></div>
                    <button class="btn-primary-lg" type="submit">Masuk ke aplikasi <x-icon name="arrow" /></button>
                </form>
                <div class="login-security"><x-icon name="shield" /><span>Aplikasi online · koneksi ke server
                        diperlukan.</span></div>
            </div>
            <p class="login-copyright">Kopdes · Sistem POS Koperasi Desa</p>
        </section>
    </div>
@endsection
@push('scripts')
    <script>
        document.getElementById('toggle-password')?.addEventListener('click', function() {
            const input = document.getElementById('password');
            const shown = input.type === 'password';
            input.type = shown ? 'text' : 'password';
            this.textContent = shown ? 'Sembunyikan' : 'Tampilkan';
            this.setAttribute('aria-pressed', String(shown));
        });
    </script>
@endpush
