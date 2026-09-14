<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Usuario;
use Tests\CasoClinico;

class SelectorPacienteTest extends CasoClinico
{
    public function test_buscar_devuelve_al_paciente_por_nombre_o_documento(): void
    {
        $this->paciente->update(['nombres' => 'María', 'apellidos' => 'Pérez Quispe']);

        $this->actingAs($this->admin)
            ->getJson('/admin/pacientes/buscar?q=Pér')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->paciente->id)
            ->assertJsonPath('0.texto', "Pérez Quispe, María · {$this->paciente->numero_documento}")
            ->assertJsonStructure([['id', 'texto', 'telefono']]);

        $this->actingAs($this->admin)
            ->getJson('/admin/pacientes/buscar?q='.$this->paciente->numero_documento)
            ->assertOk()
            ->assertJsonPath('0.id', $this->paciente->id);
    }

    public function test_buscar_ignora_a_los_pacientes_inactivos_y_limita_a_veinte(): void
    {
        $this->paciente->update(['activo' => false]);

        for ($i = 1; $i <= 25; $i++) {
            Paciente::create([
                'nombres' => "Paciente {$i}",
                'apellidos' => 'Zeta',
                'tipo_documento' => 'CI',
                'numero_documento' => 'Z'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'genero' => 'M',
                'activo' => true,
            ]);
        }

        $this->actingAs($this->admin)
            ->getJson('/admin/pacientes/buscar?q=Zeta')
            ->assertOk()
            ->assertJsonCount(20);

        $this->actingAs($this->admin)
            ->getJson('/admin/pacientes/buscar?q='.$this->paciente->numero_documento)
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_sin_permiso_de_pacientes_la_busqueda_es_rechazada(): void
    {
        $usuario = Usuario::create([
            'nombre' => 'Usuario Paciente',
            'email' => 'paciente@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $usuario->assignRole('PACIENTE');

        $this->actingAs($usuario)
            ->getJson('/admin/pacientes/buscar?q=Pér')
            ->assertForbidden();
    }

    public function test_el_formulario_de_citas_usa_el_selector_remoto(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/citas/create')
            ->assertOk()
            ->assertSee('data-selector-paciente', false)
            ->assertSee(route('admin.pacientes.buscar'), false);
    }

    public function test_el_selector_conserva_el_paciente_elegido_tras_un_error_de_validacion(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/citas/create')
            ->post('/admin/citas', ['paciente_id' => $this->paciente->id])
            ->assertRedirect('/admin/citas/create');

        $this->get('/admin/citas/create')
            ->assertOk()
            ->assertSee('<option value="'.$this->paciente->id.'" selected', false)
            ->assertSee($this->paciente->apellidos.', '.$this->paciente->nombres);
    }
}
