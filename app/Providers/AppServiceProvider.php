<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema; // 1. Import facade Schema

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 2. Batasi panjang string index MySQL (solusi error 1071 di Namecheap)
        Schema::defaultStringLength(191);

        // 3. Konfigurasi pagination milik Anda tetap dipertahankan
        \Illuminate\Pagination\Paginator::defaultView('components.pagination');
    }
}