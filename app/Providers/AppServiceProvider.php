<?php

namespace App\Providers;

use App\Services\Control\Licencia;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Licencia CONTROL: un solo objeto por proceso; la configuración se lee
        // al construirlo (en las pruebas, config() antes de la primera petición).
        $this->app->singleton(Licencia::class, fn () => Licencia::desdeConfig(
            config('control', []) + ['app_url' => config('app.url'), 'version' => config('app.version')],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los componentes Blade del sistema viven en resources/views/componentes.
        Blade::anonymousComponentPath(resource_path('views/componentes'));
    }
}
