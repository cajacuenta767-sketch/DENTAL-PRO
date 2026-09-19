<?php

namespace App\Providers;

use App\Services\Control\Licencia;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        $this->configurarCompatibilidadSqlite();
    }

    /**
     * Registra compatibilidad para funciones y operadores de PostgreSQL al usar SQLite.
     */
    private function configurarCompatibilidadSqlite(): void
    {
        $configurar = function ($connection) {
            if (! ($connection instanceof SQLiteConnection)) {
                return;
            }

            // Emular TO_CHAR de PostgreSQL en SQLite
            $connection->getPdo()->sqliteCreateFunction('to_char', function ($date, $format) {
                if (! $date) {
                    return null;
                }
                $timestamp = strtotime((string) $date);
                if ($timestamp === false) {
                    return null;
                }
                $formatMap = [
                    'YYYY' => 'Y',
                    'YY' => 'y',
                    'MM' => 'm',
                    'DD' => 'd',
                    'HH24' => 'H',
                    'MI' => 'i',
                    'SS' => 's',
                ];
                $phpFormat = str_replace(array_keys($formatMap), array_values($formatMap), $format);

                return date($phpFormat, $timestamp);
            }, 2);

            // Mapear operador ilike a like en consultas SQLite
            $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
            {
                protected function whereBasic(Builder $query, $where): string
                {
                    if (isset($where['operator']) && strtolower($where['operator']) === 'ilike') {
                        $where['operator'] = 'like';
                    }

                    return parent::whereBasic($query, $where);
                }
            });
        };

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) use ($configurar) {
            $configurar($event->connection);
        });

        if (app()->resolved('db')) {
            try {
                $configurar(DB::connection());
            } catch (\Throwable) {
                // Se configurará en ConnectionEstablished cuando se abra
            }
        }
    }
}
