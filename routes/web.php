<?php

use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Admin\AjusteController;
use App\Http\Controllers\Admin\AseguradoraController;
use App\Http\Controllers\Admin\BusquedaController;
use App\Http\Controllers\Admin\CitaController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\DocumentoClinicoController;
use App\Http\Controllers\Admin\EstudioImagenController;
use App\Http\Controllers\Admin\EspecialidadController;
use App\Http\Controllers\Admin\FacturacionController;
use App\Http\Controllers\Admin\HistorialClinicoController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\LicenciaEmitidaController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\OdontogramaController;
use App\Http\Controllers\Admin\PacienteController;
use App\Http\Controllers\Admin\PagoController;
use App\Http\Controllers\Admin\PresupuestoController;
use App\Http\Controllers\Admin\ReservaOnlineController;
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
use App\Http\Controllers\LicenciaController;
use App\Http\Controllers\PublicoController;
use App\Http\Controllers\ReservaPublicaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio público
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicoController::class, 'inicio'])->name('publico.inicio');

/*
|--------------------------------------------------------------------------
| Reservas en línea (enlace y QR públicos, sin cuenta)
|--------------------------------------------------------------------------
*/

Route::prefix('reservar/{token}')->middleware('licencia')->name('reservas.')->group(function () {
    Route::get('/', [ReservaPublicaController::class, 'formulario'])->name('formulario');
    Route::get('opciones', [ReservaPublicaController::class, 'opciones'])->name('opciones');
    Route::get('horas', [ReservaPublicaController::class, 'horas'])->name('horas');
    Route::post('/', [ReservaPublicaController::class, 'reservar'])
        ->middleware('throttle:10,1')->name('guardar');
    Route::get('confirmacion/{codigo}', [ReservaPublicaController::class, 'confirmacion'])->name('confirmacion');
});

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

    // Estado de la licencia y entrada del PIN. Accesible aunque esté vencida.
    Route::get('licencia', [LicenciaController::class, 'ver'])->name('licencia.ver');
    Route::post('licencia/activar', [LicenciaController::class, 'activar'])
        ->middleware('throttle:10,1')->name('licencia.activar');

    Route::get('perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('perfil/password', [PerfilController::class, 'password'])->name('perfil.password');
});

/*
|--------------------------------------------------------------------------
| Panel administrativo
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'licencia'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('home', [HomeController::class, 'index'])->name('home');

    // Emisión de licencias: solo en la instalación del proveedor (con clave privada).
    Route::middleware('role:SUPER ADMINISTRADOR')->group(function () {
        Route::get('licencias', [LicenciaEmitidaController::class, 'index'])->name('licencias.index');
        Route::post('licencias', [LicenciaEmitidaController::class, 'store'])->name('licencias.store');
        Route::post('licencias/{emitida}/renovar', [LicenciaEmitidaController::class, 'renovar'])->name('licencias.renovar');
        Route::post('licencias/{emitida}/reactivar', [LicenciaEmitidaController::class, 'reactivar'])->name('licencias.reactivar');
        Route::delete('licencias/{emitida}', [LicenciaEmitidaController::class, 'destroy'])->name('licencias.destroy');
    });

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
    // --- Búsqueda global ---------------------------------------------------
    Route::get('buscar', [BusquedaController::class, 'index'])->name('buscar');
    Route::get('buscar/sugerencias', [BusquedaController::class, 'sugerencias'])->name('buscar.sugerencias');

    // --- Reservas en línea -------------------------------------------------
    Route::get('reservas-online', [ReservaOnlineController::class, 'edit'])
        ->middleware('permission:ajustes.reservas')->name('reservas.edit');
    Route::put('reservas-online', [ReservaOnlineController::class, 'update'])
        ->middleware('permission:ajustes.reservas')->name('reservas.update');
    Route::post('reservas-online/regenerar', [ReservaOnlineController::class, 'regenerar'])
        ->middleware('permission:ajustes.reservas')->name('reservas.regenerar');
    Route::get('reservas-online/qr', [ReservaOnlineController::class, 'descargarQr'])
        ->middleware('permission:ajustes.reservas')->name('reservas.qr');

    // --- Aseguradoras ------------------------------------------------------
    Route::resource('aseguradoras', AseguradoraController::class)->except('show')
        ->middlewareFor(['index'], 'permission:aseguradoras.ver')
        ->middlewareFor(['create', 'store'], 'permission:aseguradoras.crear')
        ->middlewareFor(['edit', 'update'], 'permission:aseguradoras.editar')
        ->middlewareFor(['destroy'], 'permission:aseguradoras.eliminar');

    // --- Imagenología ------------------------------------------------------
    Route::get('estudios', [EstudioImagenController::class, 'index'])
        ->middleware('permission:imagenologia.ver')->name('estudios.index');
    Route::get('estudios/nuevo', [EstudioImagenController::class, 'create'])
        ->middleware('permission:imagenologia.crear')->name('estudios.create');
    Route::post('estudios', [EstudioImagenController::class, 'store'])
        ->middleware('permission:imagenologia.crear')->name('estudios.store');
    Route::get('estudios/{estudio}/editar', [EstudioImagenController::class, 'edit'])
        ->middleware('permission:imagenologia.editar')->name('estudios.edit');
    Route::put('estudios/{estudio}', [EstudioImagenController::class, 'update'])
        ->middleware('permission:imagenologia.editar')->name('estudios.update');
    Route::delete('estudios/{estudio}', [EstudioImagenController::class, 'destroy'])
        ->middleware('permission:imagenologia.eliminar')->name('estudios.destroy');
    Route::get('estudios/{estudio}/descargar', [EstudioImagenController::class, 'descargar'])
        ->middleware('permission:imagenologia.descargar')->name('estudios.descargar');
    Route::get('pacientes/{paciente}/panoramicas', [EstudioImagenController::class, 'porPaciente'])
        ->middleware('permission:imagenologia.ver')->name('estudios.paciente');

    // --- Recetas y certificados --------------------------------------------
    Route::get('documentos', [DocumentoClinicoController::class, 'index'])
        ->middleware('permission:documentos.ver')->name('documentos.index');
    Route::get('documentos/nuevo', [DocumentoClinicoController::class, 'create'])
        ->middleware('permission:documentos.crear')->name('documentos.create');
    Route::post('documentos', [DocumentoClinicoController::class, 'store'])
        ->middleware('permission:documentos.crear')->name('documentos.store');
    Route::get('documentos/{documento}/editar', [DocumentoClinicoController::class, 'edit'])
        ->middleware('permission:documentos.editar')->name('documentos.edit');
    Route::put('documentos/{documento}', [DocumentoClinicoController::class, 'update'])
        ->middleware('permission:documentos.editar')->name('documentos.update');
    Route::patch('documentos/{documento}/anular', [DocumentoClinicoController::class, 'anular'])
        ->middleware('permission:documentos.anular')->name('documentos.anular');
    Route::get('documentos/{documento}/pdf', [DocumentoClinicoController::class, 'pdf'])
        ->middleware('permission:documentos.ver')->name('documentos.pdf');
    Route::get('pacientes/{paciente}/documentos', [DocumentoClinicoController::class, 'porPaciente'])
        ->middleware('permission:documentos.ver')->name('documentos.paciente');

    // --- Presupuestos y plan de tratamiento --------------------------------
    Route::resource('presupuestos', PresupuestoController::class)
        ->middlewareFor(['index', 'show'], 'permission:presupuestos.ver')
        ->middlewareFor(['create', 'store'], 'permission:presupuestos.crear')
        ->middlewareFor(['edit', 'update'], 'permission:presupuestos.editar')
        ->middlewareFor(['destroy'], 'permission:presupuestos.eliminar');

    Route::patch('presupuestos/{presupuesto}/estado', [PresupuestoController::class, 'cambiarEstado'])
        ->middleware('permission:presupuestos.aprobar')->name('presupuestos.estado');
    Route::patch('presupuesto-detalles/{detalle}/ejecutar', [PresupuestoController::class, 'ejecutarDetalle'])
        ->middleware('permission:presupuestos.ejecutar')->name('presupuestos.ejecutar');
    Route::post('presupuestos/{presupuesto}/facturar', [PresupuestoController::class, 'facturar'])
        ->middleware('permission:pagos.crear')->name('presupuestos.facturar');
    Route::get('presupuestos/{presupuesto}/pdf', [PresupuestoController::class, 'pdf'])
        ->middleware('permission:presupuestos.ver')->name('presupuestos.pdf');

    // --- Inventario ---------------------------------------------------------
    Route::get('inventario/kardex', [InventarioController::class, 'kardex'])
        ->middleware('permission:inventario.ver')->name('inventario.kardex');
    Route::post('inventario/{insumo}/movimiento', [InventarioController::class, 'movimiento'])
        ->middleware('permission:inventario.movimientos')->name('inventario.movimiento');
    Route::resource('inventario', InventarioController::class)
        ->parameters(['inventario' => 'insumo'])
        ->middlewareFor(['index', 'show'], 'permission:inventario.ver')
        ->middlewareFor(['create', 'store'], 'permission:inventario.crear')
        ->middlewareFor(['edit', 'update'], 'permission:inventario.editar')
        ->middlewareFor(['destroy'], 'permission:inventario.eliminar');

    // --- Facturación electrónica --------------------------------------------
    Route::get('facturacion', [FacturacionController::class, 'index'])
        ->middleware('permission:facturacion.ver')->name('facturacion.index');
    Route::get('facturacion/emitir', [FacturacionController::class, 'create'])
        ->middleware('permission:facturacion.emitir')->name('facturacion.create');
    Route::post('facturacion', [FacturacionController::class, 'store'])
        ->middleware('permission:facturacion.emitir')->name('facturacion.store');
    Route::get('facturacion/{documento}', [FacturacionController::class, 'show'])
        ->middleware('permission:facturacion.ver')->name('facturacion.show');
    Route::patch('facturacion/{documento}/anular', [FacturacionController::class, 'anular'])
        ->middleware('permission:facturacion.anular')->name('facturacion.anular');
    Route::get('facturacion/{documento}/pdf', [FacturacionController::class, 'pdf'])
        ->middleware('permission:facturacion.ver')->name('facturacion.pdf');
});
