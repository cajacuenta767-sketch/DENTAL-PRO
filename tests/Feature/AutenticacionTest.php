<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\CasoClinico;

class AutenticacionTest extends CasoClinico
{
    public function test_la_landing_publica_responde(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Gestiona tu clínica dental', false);
    }

    public function test_el_panel_exige_iniciar_sesion(): void
    {
        $this->get('/admin/home')->assertRedirect(route('login'));
    }

    public function test_un_usuario_valido_entra_al_panel(): void
    {
        $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'secreto123',
        ])->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_credenciales_incorrectas_no_autentican(): void
    {
        $this->from('/login')->post('/login', [
            'email' => $this->admin->email,
            'password' => 'equivocada',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_inactivo_no_puede_entrar(): void
    {
        $this->admin->update(['estado' => 'inactivo']);

        $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'secreto123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_quien_se_registra_queda_como_paciente(): void
    {
        $this->post('/registro', [
            'nombre' => 'Nueva Paciente',
            'email' => 'nueva@pruebas.test',
            'password' => 'clavelarga123',
            'password_confirmation' => 'clavelarga123',
        ])->assertRedirect(route('admin.home'));

        $this->assertTrue(Usuario::whereEmail('nueva@pruebas.test')->first()->hasRole('PACIENTE'));
    }
}
