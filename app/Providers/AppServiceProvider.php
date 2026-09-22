<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;
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

        Number::useLocale(config('app.locale'));

        Model::preventLazyLoading(! $this->app->isProduction());

        ExportColumn::configureUsing(fn (ExportColumn $column) => $column->preventFormulaInjection());
    }
}
