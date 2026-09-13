<?php

use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Admin\AjusteController;
use App\Http\Controllers\Admin\CitaController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\EspecialidadController;
use App\Http\Controllers\Admin\HistorialClinicoController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\Admin\OdontogramaController;
use App\Http\Controllers\Admin\PacienteController;
use App\Http\Controllers\Admin\PagoController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\RolController;
use App\Http\Controllers\Admin\TratamientoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\VerificacionEmailController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PublicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio público
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicoController::class, 'inicio'])->name('publico.inicio');

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    Route::get('registro', [RegisterController::class, 'create'])->name('register');
    Route::post('registro', [RegisterController::class, 'store']);

    Route::get('olvide-password', [PasswordResetController::class, 'solicitar'])->name('password.request');
    Route::post('olvide-password', [PasswordResetController::class, 'enviarEnlace'])->name('password.email');
    Route::get('restablecer-password/{token}', [PasswordResetController::class, 'formulario'])->name('password.reset');
    Route::post('restablecer-password', [PasswordResetController::class, 'guardar'])->name('password.store');

    Route::get('auth/{proveedor}/redirect', [SocialiteController::class, 'redirect'])
        ->whereIn('proveedor', ['google', 'github'])->name('social.redirect');
    Route::get('auth/{proveedor}/callback', [SocialiteController::class, 'callback'])
        ->whereIn('proveedor', ['google', 'github'])->name('social.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('verificar-email', [VerificacionEmailController::class, 'aviso'])->name('verification.notice');
    Route::get('verificar-email/{id}/{hash}', [VerificacionEmailController::class, 'verificar'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('verificar-email/reenviar', [VerificacionEmailController::class, 'reenviar'])
        ->middleware('throttle:6,1')->name('verification.send');

    Route::get('perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('perfil/password', [PerfilController::class, 'password'])->name('perfil.password');
});

/*
|--------------------------------------------------------------------------
| Panel administrativo
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {

    Route::get('home', [HomeController::class, 'index'])->name('home');

    // --- Configuración -------------------------------------------------
    Route::get('ajustes', [AjusteController::class, 'edit'])
        ->middleware('permission:ajustes.ver')->name('ajustes.edit');
    Route::put('ajustes', [AjusteController::class, 'update'])
        ->middleware('permission:ajustes.editar')->name('ajustes.update');

    Route::resource('roles', RolController::class)->except('show')
        ->middlewareFor(['index'], 'permission:roles.ver')
        ->middlewareFor(['create', 'store'], 'permission:roles.crear')
        ->middlewareFor(['edit', 'update'], 'permission:roles.editar')
        ->middlewareFor(['destroy'], 'permission:roles.eliminar');

    Route::resource('usuarios', UsuarioController::class)->except('show')
        ->middlewareFor(['index'], 'permission:usuarios.ver')
        ->middlewareFor(['create', 'store'], 'permission:usuarios.crear')
        ->middlewareFor(['edit', 'update'], 'permission:usuarios.editar')
        ->middlewareFor(['destroy'], 'permission:usuarios.eliminar');

    // --- Catálogos -----------------------------------------------------
    Route::resource('especialidades', EspecialidadController::class)->except('show')
        ->parameters(['especialidades' => 'especialidad'])
        ->middlewareFor(['index'], 'permission:especialidades.ver')
        ->middlewareFor(['create', 'store'], 'permission:especialidades.crear')
        ->middlewareFor(['edit', 'update'], 'permission:especialidades.editar')
        ->middlewareFor(['destroy'], 'permission:especialidades.eliminar');

    Route::resource('tratamientos', TratamientoController::class)->except('show')
        ->middlewareFor(['index'], 'permission:tratamientos.ver')
        ->middlewareFor(['create', 'store'], 'permission:tratamientos.crear')
        ->middlewareFor(['edit', 'update'], 'permission:tratamientos.editar')
        ->middlewareFor(['destroy'], 'permission:tratamientos.eliminar');

    // --- Clínica -------------------------------------------------------
    Route::resource('doctores', DoctorController::class)
        ->parameters(['doctores' => 'doctor'])
        ->middlewareFor(['index', 'show'], 'permission:doctores.ver')
        ->middlewareFor(['create', 'store'], 'permission:doctores.crear')
        ->middlewareFor(['edit', 'update'], 'permission:doctores.editar')
        ->middlewareFor(['destroy'], 'permission:doctores.eliminar');

    Route::resource('horarios', HorarioController::class)->except('show')
        ->middlewareFor(['index'], 'permission:horarios.ver')
        ->middlewareFor(['create', 'store'], 'permission:horarios.crear')
        ->middlewareFor(['edit', 'update'], 'permission:horarios.editar')
        ->middlewareFor(['destroy'], 'permission:horarios.eliminar');

    Route::resource('pacientes', PacienteController::class)
        ->middlewareFor(['index', 'show'], 'permission:pacientes.ver')
        ->middlewareFor(['create', 'store'], 'permission:pacientes.crear')
        ->middlewareFor(['edit', 'update'], 'permission:pacientes.editar')
        ->middlewareFor(['destroy'], 'permission:pacientes.eliminar');

    // --- Citas ---------------------------------------------------------
    Route::resource('citas', CitaController::class)
        ->middlewareFor(['index', 'show'], 'permission:citas.ver')
        ->middlewareFor(['create', 'store'], 'permission:citas.crear')
        ->middlewareFor(['edit', 'update'], 'permission:citas.editar')
        ->middlewareFor(['destroy'], 'permission:citas.eliminar');

    Route::patch('citas/{cita}/estado', [CitaController::class, 'cambiarEstado'])
        ->middleware('permission:citas.editar')->name('citas.estado');
    Route::post('citas/{cita}/reenviar-correo', [CitaController::class, 'reenviarCorreo'])
        ->middleware('permission:citas.confirmar')->name('citas.reenviar');
    Route::get('citas/horas-disponibles/consultar', [CitaController::class, 'horasDisponibles'])
        ->middleware('permission:citas.ver')->name('citas.horas');

    Route::get('agenda', [AgendaController::class, 'index'])
        ->middleware('permission:agenda.ver')->name('agenda.index');
    Route::get('agenda/doctor/{doctor}', [AgendaController::class, 'porDoctor'])
        ->middleware('permission:agenda.ver')->name('agenda.doctor');

    // --- Historia clínica ----------------------------------------------
    Route::get('pacientes/{paciente}/historial', [HistorialClinicoController::class, 'index'])
        ->middleware('permission:historiales.ver')->name('historiales.index');
    Route::get('pacientes/{paciente}/historial/nuevo', [HistorialClinicoController::class, 'create'])
        ->middleware('permission:historiales.crear')->name('historiales.create');
    Route::post('pacientes/{paciente}/historial', [HistorialClinicoController::class, 'store'])
        ->middleware('permission:historiales.crear')->name('historiales.store');
    Route::get('historiales/{historial}/editar', [HistorialClinicoController::class, 'edit'])
        ->middleware('permission:historiales.editar')->name('historiales.edit');
    Route::put('historiales/{historial}', [HistorialClinicoController::class, 'update'])
        ->middleware('permission:historiales.editar')->name('historiales.update');
    Route::delete('historiales/{historial}', [HistorialClinicoController::class, 'destroy'])
        ->middleware('permission:historiales.eliminar')->name('historiales.destroy');
    Route::get('historiales/{historial}/pdf', [HistorialClinicoController::class, 'pdf'])
        ->middleware('permission:historiales.ver')->name('historiales.pdf');

    // --- Odontograma ----------------------------------------------------
    Route::get('pacientes/{paciente}/odontograma', [OdontogramaController::class, 'index'])
        ->middleware('permission:odontogramas.ver')->name('odontogramas.index');
    Route::get('pacientes/{paciente}/odontograma/nuevo', [OdontogramaController::class, 'create'])
        ->middleware('permission:odontogramas.crear')->name('odontogramas.create');
    Route::post('pacientes/{paciente}/odontograma', [OdontogramaController::class, 'store'])
        ->middleware('permission:odontogramas.crear')->name('odontogramas.store');
    Route::get('odontogramas/{odontograma}/editar', [OdontogramaController::class, 'edit'])
        ->middleware('permission:odontogramas.editar')->name('odontogramas.edit');
    Route::put('odontogramas/{odontograma}', [OdontogramaController::class, 'update'])
        ->middleware('permission:odontogramas.editar')->name('odontogramas.update');
    Route::delete('odontogramas/{odontograma}', [OdontogramaController::class, 'destroy'])
        ->middleware('permission:odontogramas.eliminar')->name('odontogramas.destroy');

    // --- Caja y pagos ---------------------------------------------------
    Route::resource('pagos', PagoController::class)
        ->middlewareFor(['index', 'show'], 'permission:pagos.ver')
        ->middlewareFor(['create', 'store'], 'permission:pagos.crear')
        ->middlewareFor(['edit', 'update'], 'permission:pagos.editar')
        ->middlewareFor(['destroy'], 'permission:pagos.eliminar');

    Route::patch('pagos/{pago}/anular', [PagoController::class, 'anular'])
        ->middleware('permission:pagos.anular')->name('pagos.anular');
    Route::get('pagos/{pago}/recibo', [PagoController::class, 'recibo'])
        ->middleware('permission:pagos.ver')->name('pagos.recibo');
    Route::post('pagos/{pago}/enviar-recibo', [PagoController::class, 'enviarRecibo'])
        ->middleware('permission:pagos.ver')->name('pagos.enviar');

    // --- Reportes --------------------------------------------------------
    Route::get('reportes', [ReporteController::class, 'index'])
        ->middleware('permission:reportes.ver')->name('reportes.index');
    Route::get('reportes/exportar/{seccion}', [ReporteController::class, 'exportar'])
        ->middleware('permission:reportes.exportar')
        ->whereIn('seccion', ['financiero', 'citas', 'padron', 'tratamientos'])
        ->name('reportes.exportar');
});
