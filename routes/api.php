<?php

use App\Http\Controllers\Api\V1\AgendaApiController;
use App\Http\Controllers\Api\V1\CatalogoApiController;
use App\Http\Controllers\Api\V1\CitaApiController;
use App\Http\Controllers\Api\V1\PacienteApiController;
use App\Http\Controllers\Api\V1\PagoApiController;
use App\Http\Controllers\Api\V1\PresupuestoApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 · autenticación por token personal (Laravel Sanctum)
|--------------------------------------------------------------------------
|
| Los tokens se crean desde "Mi perfil" por usuarios con el permiso api.usar
| y heredan los permisos de ese usuario. Cabecera: Authorization: Bearer …
|
*/

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('yo', fn (Request $request) => [
        'id' => $request->user()->id,
        'nombre' => $request->user()->nombre,
        'email' => $request->user()->email,
        'roles' => $request->user()->getRoleNames(),
        'permisos' => $request->user()->getAllPermissions()->pluck('name'),
    ])->name('yo');

    Route::get('pacientes', [PacienteApiController::class, 'index'])->name('pacientes.index');
    Route::post('pacientes', [PacienteApiController::class, 'store'])->name('pacientes.store');
    Route::get('pacientes/{paciente}', [PacienteApiController::class, 'show'])->name('pacientes.show');

    Route::get('citas', [CitaApiController::class, 'index'])->name('citas.index');
    Route::post('citas', [CitaApiController::class, 'store'])->name('citas.store');
    Route::get('citas/{cita}', [CitaApiController::class, 'show'])->name('citas.show');
    Route::patch('citas/{cita}/estado', [CitaApiController::class, 'estado'])->name('citas.estado');

    Route::get('agenda/horas', [AgendaApiController::class, 'horas'])->name('agenda.horas');

    Route::get('doctores', [CatalogoApiController::class, 'doctores'])->name('doctores');
    Route::get('especialidades', [CatalogoApiController::class, 'especialidades'])->name('especialidades');
    Route::get('tratamientos', [CatalogoApiController::class, 'tratamientos'])->name('tratamientos');

    Route::get('presupuestos', [PresupuestoApiController::class, 'index'])->name('presupuestos.index');
    Route::get('presupuestos/{presupuesto}', [PresupuestoApiController::class, 'show'])->name('presupuestos.show');

    Route::get('pagos', [PagoApiController::class, 'index'])->name('pagos.index');
    Route::get('pagos/{pago}', [PagoApiController::class, 'show'])->name('pagos.show');
});
