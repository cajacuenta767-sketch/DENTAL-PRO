<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicoController extends Controller
{
    public function inicio(): View
    {
        return view('publico.inicio', [
            'totalModulos' => collect(config('odontosuite.modulos'))
                ->reject(fn ($m) => ($m['oculto_en_menu'] ?? false) || $m['ruta'] === 'admin.home')
                ->count(),

            'modulosPublicos' => [
                ['icono' => 'ti ti-user-heart', 'etiqueta' => 'Pacientes', 'texto' => 'Ficha completa con antecedentes, alergias y contacto de emergencia.'],
                ['icono' => 'ti ti-calendar-event', 'etiqueta' => 'Citas', 'texto' => 'Agenda por doctor con token de confirmación y estados en vivo.'],
                ['icono' => 'ti ti-notes-medical', 'etiqueta' => 'Historia clínica', 'texto' => 'Diagnóstico, tratamiento y receta guardados por consulta.'],
                ['icono' => 'ti ti-dental', 'etiqueta' => 'Odontograma', 'texto' => 'Registro visual por pieza y cara, adulto e infantil.'],
                ['icono' => 'ti ti-user-check', 'etiqueta' => 'Doctores', 'texto' => 'Especialidad, colegiatura y disponibilidad semanal.'],
                ['icono' => 'ti ti-cash-register', 'etiqueta' => 'Caja y pagos', 'texto' => 'Recibos, métodos de cobro, saldos y comprobante en PDF.'],
                ['icono' => 'ti ti-chart-histogram', 'etiqueta' => 'Reportes', 'texto' => 'Financiero, productividad, padrón y rentabilidad por tratamiento.'],
                ['icono' => 'ti ti-shield-lock', 'etiqueta' => 'Roles y permisos', 'texto' => 'Cada persona ve exactamente lo que le corresponde.'],
            ],

            'pasos' => [
                ['titulo' => 'El paciente reserva', 'texto' => 'Desde recepción o en línea, eligiendo doctor, tratamiento y horario disponible.'],
                ['titulo' => 'Se confirma la cita', 'texto' => 'El sistema envía el correo con el token y programa el recordatorio automático.'],
                ['titulo' => 'El doctor atiende', 'texto' => 'Registra historia clínica y actualiza el odontograma desde su agenda.'],
                ['titulo' => 'Caja cobra', 'texto' => 'Se emite el recibo con detalle de tratamientos y comprobante en PDF.'],
            ],

            'beneficios' => [
                ['icono' => 'ti ti-clock-bolt', 'titulo' => 'Agenda sin choques', 'texto' => 'El horario del doctor bloquea los cupos ya ocupados.'],
                ['icono' => 'ti ti-shield-check', 'titulo' => 'Permisos granulares', 'texto' => 'Cinco roles predefinidos y permisos por acción.'],
                ['icono' => 'ti ti-file-type-pdf', 'titulo' => 'Documentos oficiales', 'texto' => 'Recibos y reportes exportables en PDF.'],
                ['icono' => 'ti ti-device-mobile', 'titulo' => 'Responsive real', 'texto' => 'Funciona igual en el consultorio y en el celular.'],
            ],

            'agendaDemo' => [
                ['paciente' => 'María Pérez', 'hora' => '10:30', 'tratamiento' => 'Limpieza dental', 'estado' => 'Confirmada', 'color' => 'green'],
                ['paciente' => 'Carlos Rojas', 'hora' => '11:15', 'tratamiento' => 'Endodoncia', 'estado' => 'Pendiente', 'color' => 'yellow'],
                ['paciente' => 'Lucía Gómez', 'hora' => '12:00', 'tratamiento' => 'Brackets', 'estado' => 'Dra. Torres', 'color' => 'azure'],
                ['paciente' => 'Andrés Vega', 'hora' => '16:45', 'tratamiento' => 'Ortodoncia', 'estado' => 'Completada', 'color' => 'green'],
            ],
        ]);
    }
}
