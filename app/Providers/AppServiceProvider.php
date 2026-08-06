<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        // @plata(1250000) -> $12.500 · ver App\Support\Plata y R-31
        Blade::directive('plata', fn ($expr) => "<?php echo \App\Support\Plata::format($expr); ?>");
    }
}
