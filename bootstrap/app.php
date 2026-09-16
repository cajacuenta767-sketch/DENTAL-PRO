<?php

use App\Http\Middleware\CompartirAjustes;
use App\Http\Middleware\ExigirCambioPassword;
use App\Http\Middleware\SoloPacientes;
use App\Http\Middleware\VerificarLicencia;
use App\Http\Middleware\VerificarUsuarioActivo;
use App\Http\Middleware\VerificarClinicaActiva;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Archivos de la PWA. Viven en public/ y normalmente los sirve el
            // servidor web sin pasar por Laravel; estas rutas (sin sesión ni
            // CSRF) los entregan con el content-type correcto cuando la
            // petición llega a index.php (pruebas, algunos proxies).
            Route::get('/manifest.webmanifest', fn () => response()->file(public_path('manifest.webmanifest'), [
                'Content-Type' => 'application/manifest+json',
                'Cache-Control' => 'public, max-age=3600',
            ]));
            Route::get('/sw.js', fn () => response()->file(public_path('sw.js'), [
                'Content-Type' => 'text/javascript; charset=UTF-8',
                'Service-Worker-Allowed' => '/',
                'Cache-Control' => 'no-cache',
            ]));
            Route::get('/offline.html', fn () => response()->file(public_path('offline.html'), [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CompartirAjustes::class,
            VerificarUsuarioActivo::class,
            ExigirCambioPassword::class,
            VerificarLicencia::class,
        ]);

        // La licencia CONTROL también protege la API (responde 402 en JSON) y
        // se evalúa antes de la autenticación: un sistema bloqueado muestra
        // la pantalla /licencia (o el 402) en lugar del login.
        $middleware->api(append: [VerificarLicencia::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, VerificarLicencia::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'paciente' => SoloPacientes::class,
            'clinica.activa' => VerificarClinicaActiva::class,
        ]);

        // El webhook de la pasarela de pagos llega sin sesión ni token CSRF.
        $middleware->validateCsrfTokens(except: ['pagos-online/webhook/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->destinoInicial());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Una violación de unicidad (dos cajas emitiendo a la vez, un cupo
        // tomado en el mismo instante) vuelve al formulario con un mensaje
        // de negocio en lugar de un error 500.
        $exceptions->render(function (QueryException $e, Request $request) {
            if ((string) $e->getCode() !== '23505' || $request->expectsJson()) {
                return null;
            }

            report($e);

            return back()->withInput()->with('error', 'Otro usuario registró el mismo dato en este instante. Revisa la información e inténtalo de nuevo.');
        });
    })->create();
