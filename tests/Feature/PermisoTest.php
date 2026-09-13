<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\CasoClinico;

class PermisoTest extends CasoClinico
{
    private function usuarioCon(string $rol): Usuario
    {
        $usuario = Usuario::create([
            'nombre' => "Usuario {$rol}",
            'email' => str($rol)->slug().'@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    public function test_recepcion_entra_a_caja_pero_no_a_roles(): void
    {
        $this->actingAs($this->usuarioCon('RECEPCION'));

        $this->get('/admin/pagos')->assertOk();
        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/usuarios')->assertForbidden();
    }

    public function test_un_doctor_ve_su_agenda_pero_no_la_caja(): void
    {
        $this->actingAs($this->usuarioCon('DOCTOR'));

        $this->get('/admin/agenda')->assertOk();
        $this->get('/admin/pagos')->assertForbidden();
    }

    public function test_el_super_administrador_alcanza_todos_los_modulos(): void
    {
        $this->actingAs($this->admin);

        foreach (['/admin/home', '/admin/roles', '/admin/usuarios', '/admin/pacientes',
            '/admin/citas', '/admin/pagos', '/admin/reportes', '/admin/ajustes'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_el_menu_solo_muestra_los_modulos_permitidos(): void
    {
        $this->actingAs($this->usuarioCon('RECEPCION'))
            ->get('/admin/home')
            ->assertOk()
            ->assertSee('Caja y Pagos')
            ->assertDontSee('>Roles<', false);
    }
}
