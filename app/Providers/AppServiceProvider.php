<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
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
        // Los componentes Blade del sistema viven en resources/views/componentes.
        Blade::anonymousComponentPath(resource_path('views/componentes'));

        // Detrás de un proxy que termina el TLS hay que confiar en sus
        // cabeceras X-Forwarded-*; si no, Laravel ve «http» y genera enlaces
        // inseguros. El caso visible es el QR impreso de las reservas, que
        // quedaría apuntando a una dirección http. Se declara con
        // TRUSTED_PROXIES (ver config/odontosuite.php).
        if ($proxies = config('odontosuite.proxies_confiables')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }
}
