<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\DocumentoClinico;
use App\Models\Paciente;
use App\Models\Usuario;
use Tests\CasoClinico;

class PortalPacienteTest extends CasoClinico
{
    private Usuario $cuentaPaciente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cuentaPaciente = $this->cuentaConRol('PACIENTE', 'paciente@pruebas.test');
        $this->paciente->update(['usuario_id' => $this->cuentaPaciente->id]);
    }

    private function cuentaConRol(string $rol, string $email, bool $verificada = true): Usuario
    {
        $usuario = Usuario::create([
            'nombre' => "Cuenta {$rol}",
            'email' => $email,
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $usuario->assignRole($rol);

        if ($verificada) {
            $usuario->forceFill(['email_verified_at' => now()])->save();
        }

        return $usuario;
    }

    private function citaFutura(Paciente $paciente): Cita
    {
        return Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ]);
    }

    public function test_un_paciente_vinculado_entra_a_su_portal(): void
    {
        $this->actingAs($this->cuentaPaciente)->get('/portal')->assertOk();
    }

    public function test_el_paciente_cancela_una_cita_propia_futura(): void
    {
        $cita = $this->citaFutura($this->paciente);

        $this->actingAs($this->cuentaPaciente)
            ->patch("/portal/citas/{$cita->id}/cancelar")
            ->assertRedirect();

        $this->assertSame('CANCELADA', $cita->fresh()->estado);
    }

    public function test_la_cita_de_otro_paciente_no_existe_para_el_portal(): void
    {
        $otro = Paciente::create([
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'tipo_documento' => 'CI',
            'numero_documento' => '99998888',
            'genero' => 'F',
            'activo' => true,
        ]);
        $cita = $this->citaFutura($otro);

        $this->actingAs($this->cuentaPaciente)
            ->patch("/portal/citas/{$cita->id}/cancelar")
            ->assertNotFound();

        $this->assertSame('CONFIRMADA', $cita->fresh()->estado);
    }

    public function test_un_paciente_no_entra_al_panel(): void
    {
        $this->actingAs($this->cuentaPaciente);

        foreach (['/admin/home', '/admin/citas', '/admin/pacientes', '/admin/buscar/sugerencias?q=ana'] as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
    }

    public function test_el_personal_no_entra_al_portal(): void
    {
        $recepcion = $this->cuentaConRol('RECEPCION', 'recepcion@pruebas.test');

        $this->actingAs($recepcion)->get('/portal')->assertForbidden();
    }

    public function test_un_paciente_sin_correo_verificado_debe_verificarlo(): void
    {
        $sinVerificar = $this->cuentaConRol('PACIENTE', 'sinverificar@pruebas.test', verificada: false);

        $this->actingAs($sinVerificar)->get('/portal')->assertRedirect(route('verification.notice'));
    }

    public function test_el_pdf_de_un_documento_ajeno_no_existe_y_el_propio_se_descarga(): void
    {
        $otro = Paciente::create([
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'tipo_documento' => 'CI',
            'numero_documento' => '99998888',
            'genero' => 'F',
            'activo' => true,
        ]);

        $crear = fn (Paciente $paciente) => DocumentoClinico::create([
            'folio' => DocumentoClinico::siguienteFolio('RECETA'),
            'paciente_id' => $paciente->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'tipo' => 'RECETA',
            'titulo' => 'Receta médica',
            'contenido' => 'Ibuprofeno 400 mg cada 8 horas.',
            'fecha_emision' => now()->toDateString(),
            'estado' => 'EMITIDO',
        ]);

        $ajeno = $crear($otro);
        $propio = $crear($this->paciente);

        $this->actingAs($this->cuentaPaciente);

        $this->get(route('portal.documentos.pdf', $ajeno))->assertNotFound();

        $respuesta = $this->get(route('portal.documentos.pdf', $propio))->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $respuesta->headers->get('content-type'));
    }

    public function test_el_login_lleva_a_cada_uno_a_su_casa(): void
    {
        $this->post('/login', [
            'email' => $this->cuentaPaciente->email,
            'password' => 'secreto123',
        ])->assertRedirect(route('portal.inicio'));

        $this->assertAuthenticatedAs($this->cuentaPaciente);

        $this->post('/logout');

        $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'secreto123',
        ])->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($this->admin);
    }
}
