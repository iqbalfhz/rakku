<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        Date::use(CarbonImmutable::class);

        $this->migrateDeviceDatabase();
    }

    /**
     * Database di dalam ponsel bertahan antar-pembaruan aplikasi, sehingga migrasi
     * baru tidak pernah berjalan kalau tidak dipanggil dari sini. Tanpa ini, versi
     * baru aplikasi akan menabrak tabel lama milik pengguna.
     */
    private function migrateDeviceDatabase(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable) {
            // Gagal migrasi tidak boleh membuat aplikasi mati total: layar tetap
            // terbuka, dan masalahnya terbaca di log perangkat.
        }
    }
}
