<?php

namespace App\Providers;

use App\Services\DeviceDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

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
     * Tidak ada yang menjalankan `php artisan migrate` di dalam ponsel orang, jadi
     * migrasi dipanggil saat aplikasi menyala. Yang menangani kegagalannya ada di
     * DeviceDatabase.
     */
    private function migrateDeviceDatabase(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        app(DeviceDatabase::class)->migrateIfNeeded();
    }
}
