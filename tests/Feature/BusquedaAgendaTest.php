<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Usuario;
use Tests\CasoClinico;

/**
 * Buscador global, agenda del día y tablero. Comprueba que el buscador
 * respeta los permisos de cada rol y que la agenda muestra los turnos
 * del día consultado.
 */
class BusquedaAgendaTest extends CasoClinico
{
    private function agendarCita(string $hora = '08:00'): Cita
    {
        return Cita::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'token' => 'CIT-AGENDA-1',
            'fecha' => $this->proximaFecha(),
            'hora' => $hora,
            'duracion' => 30,
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ]);
    }

    // --- Buscador global ---------------------------------------------------

    public function test_el_buscador_encuentra_al_paciente_por_apellido(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/buscar?q=Pérez')
            ->assertOk()
            ->assertSee('Juan Pérez');
    }

    public function test_el_buscador_ignora_terminos_demasiado_cortos(): void
    {
        $respuesta = $this->actingAs($this->admin)->get('/admin/buscar/sugerencias?q=a');

        $respuesta->assertOk()->assertExactJson(['grupos' => []]);
    }

    public function test_las_sugerencias_agrupan_los_resultados(): void
    {
        $this->agendarCita();

        $datos = $this->actingAs($this->admin)
            ->get('/admin/buscar/sugerencias?q=Pérez')
            ->assertOk()
            ->json('grupos');

        $this->assertNotEmpty($datos);
        $this->assertContains('Pacientes', array_column($datos, 'titulo'));
    }

    public function test_el_buscador_no_devuelve_modulos_sin_permiso(): void
    {
        $this->agendarCita();

        $recepcion = Usuario::create([
            'nombre' => 'Recepción',
            'email' => 'recepcion.busqueda@pruebas.test',
            'password' => 'clave-segura-9',
            'estado' => 'activo',
        ]);
        $recepcion->assignRole('RECEPCION');

        $grupos = $this->actingAs($recepcion)
            ->get('/admin/buscar/sugerencias?q=Pérez')
            ->assertOk()
            ->json('grupos');

        $titulos = array_column($grupos, 'titulo');

        $this->assertContains('Pacientes', $titulos);

        foreach ($grupos as $grupo) {
            $this->assertTrue(
                $recepcion->can(match ($grupo['titulo']) {
                    'Pacientes' => 'pacientes.ver',
                    'Citas' => 'citas.ver',
                    'Doctores' => 'doctores.ver',
                    'Recibos' => 'pagos.ver',
                    default => 'presupuestos.ver',
                }),
                "El buscador devolvió «{$grupo['titulo']}» sin permiso para verlo."
            );
        }
    }

    // --- Agenda y tablero --------------------------------------------------

    public function test_la_agenda_muestra_los_turnos_del_dia_consultado(): void
    {
        $cita = $this->agendarCita();

        $this->actingAs($this->admin)
            ->get('/admin/agenda?fecha='.$cita->fecha->toDateString())
            ->assertOk()
            ->assertSee('Juan Pérez');
    }

    public function test_la_agenda_de_otro_dia_no_muestra_ese_turno(): void
    {
        $cita = $this->agendarCita();

        $this->actingAs($this->admin)
            ->get('/admin/agenda?fecha='.$cita->fecha->copy()->addDay()->toDateString())
            ->assertOk()
            ->assertDontSee('CIT-AGENDA-1');
    }

    public function test_la_agenda_por_doctor_abre_la_agenda_filtrada(): void
    {
        $this->agendarCita();

        $this->actingAs($this->admin)
            ->get("/admin/agenda/doctor/{$this->doctor->id}")
            ->assertRedirect(route('admin.agenda.index', [
                'doctor_id' => $this->doctor->id,
                'fecha' => null,
            ]));

        $this->actingAs($this->admin)
            ->get("/admin/agenda?doctor_id={$this->doctor->id}")
            ->assertOk()
            ->assertSee($this->doctor->apellidos, false);
    }

    public function test_el_tablero_carga_para_el_administrador(): void
    {
        $this->agendarCita();

        $this->actingAs($this->admin)->get('/admin/home')->assertOk();
    }
}
