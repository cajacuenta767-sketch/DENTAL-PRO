<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Paciente;
use App\Models\Usuario;
use Tests\CasoClinico;

class AuditoriaTest extends CasoClinico
{
    private function datosPaciente(array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Ana',
            'apellidos' => 'Auditada',
            'tipo_documento' => 'CI',
            'numero_documento' => '31415926',
            'genero' => 'F',
            'telefono' => '70000001',
            'activo' => 1,
        ], $extra);
    }

    public function test_crear_un_paciente_deja_rastro(): void
    {
        $this->actingAs($this->admin)->post('/admin/pacientes', $this->datosPaciente())->assertRedirect();

        $paciente = Paciente::where('numero_documento', '31415926')->first();

        $rastro = Auditoria::where('accion', 'CREAR')->where('modelo', 'Paciente')->where('modelo_id', $paciente->id)->first();

        $this->assertNotNull($rastro);
        $this->assertSame($this->admin->id, $rastro->usuario_id);
    }

    public function test_actualizar_el_telefono_guarda_el_antes_y_el_despues(): void
    {
        $this->actingAs($this->admin)->post('/admin/pacientes', $this->datosPaciente());
        $paciente = Paciente::where('numero_documento', '31415926')->first();

        $this->put("/admin/pacientes/{$paciente->id}", $this->datosPaciente(['telefono' => '79999999']))->assertRedirect();

        $rastro = Auditoria::where('accion', 'ACTUALIZAR')->where('modelo', 'Paciente')->where('modelo_id', $paciente->id)->latest('id')->first();

        $this->assertNotNull($rastro);
        $this->assertSame('79999999', $rastro->cambios['telefono']['despues']);
        $this->assertSame('70000001', $rastro->cambios['telefono']['antes']);
    }

    public function test_el_inicio_de_sesion_se_registra(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'secreto123']);

        $this->assertTrue(
            Auditoria::where('accion', 'INGRESO')->where('modelo', 'Usuario')->where('modelo_id', $this->admin->id)->exists()
        );
    }

    public function test_un_acceso_fallido_se_registra(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'incorrecta']);

        $this->assertGuest();

        $rastro = Auditoria::where('accion', 'ACCESO_FALLIDO')->latest('id')->first();

        $this->assertNotNull($rastro);
        $this->assertStringContainsString($this->admin->email, (string) $rastro->descripcion);
    }

    public function test_recepcion_no_consulta_la_auditoria(): void
    {
        $recepcion = Usuario::create([
            'nombre' => 'Recepción',
            'email' => 'recepcion@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $recepcion->assignRole('RECEPCION');

        $this->actingAs($recepcion)->get('/admin/auditoria')->assertForbidden();
    }
}
