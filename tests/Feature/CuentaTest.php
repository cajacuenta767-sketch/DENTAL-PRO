<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\CasoClinico;

/**
 * Administración de cuentas: usuarios, roles y perfil propio. Incluye las
 * salvaguardas que evitan que alguien se deje a sí mismo —o a la clínica—
 * sin acceso.
 */
class CuentaTest extends CasoClinico
{
    // --- Usuarios ----------------------------------------------------------

    public function test_un_usuario_nuevo_entra_con_su_rol_y_su_contrasena(): void
    {
        $this->actingAs($this->admin)->post('/admin/usuarios', [
            'nombre' => 'Recepción Tarde',
            'email' => 'recepcion.tarde@pruebas.test',
            'estado' => 'activo',
            'password' => 'clave-segura-9',
            'password_confirmation' => 'clave-segura-9',
            'roles' => ['RECEPCION'],
        ])->assertRedirect('/admin/usuarios');

        $usuario = Usuario::where('email', 'recepcion.tarde@pruebas.test')->sole();

        $this->assertTrue($usuario->hasRole('RECEPCION'));
        $this->assertTrue(Hash::check('clave-segura-9', $usuario->password));

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'recepcion.tarde@pruebas.test',
            'password' => 'clave-segura-9',
        ])->assertRedirect('/admin/home');
    }

    public function test_el_correo_de_un_usuario_no_se_repite(): void
    {
        $this->actingAs($this->admin)->post('/admin/usuarios', [
            'nombre' => 'Clon',
            'email' => $this->admin->email,
            'estado' => 'activo',
            'password' => 'clave-segura-9',
            'password_confirmation' => 'clave-segura-9',
        ])->assertSessionHasErrors('email');
    }

    public function test_nadie_puede_desactivarse_ni_quitarse_los_roles_a_si_mismo(): void
    {
        $this->actingAs($this->admin)->put("/admin/usuarios/{$this->admin->id}", [
            'nombre' => $this->admin->nombre,
            'email' => $this->admin->email,
            'estado' => 'inactivo',
            'roles' => [],
        ])->assertRedirect('/admin/usuarios');

        $this->admin->refresh();

        $this->assertSame('activo', $this->admin->estado);
        $this->assertTrue($this->admin->hasRole('SUPER ADMINISTRADOR'));
    }

    public function test_nadie_puede_eliminar_su_propia_cuenta(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/usuarios')
            ->delete("/admin/usuarios/{$this->admin->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($this->admin);
    }

    public function test_la_clinica_no_se_queda_sin_super_administrador(): void
    {
        $otro = Usuario::create([
            'nombre' => 'Segundo Admin',
            'email' => 'segundo@pruebas.test',
            'password' => 'clave-segura-9',
            'estado' => 'activo',
        ]);
        $otro->assignRole('SUPER ADMINISTRADOR');

        // Con dos, se puede eliminar uno.
        $this->actingAs($this->admin)
            ->from('/admin/usuarios')
            ->delete("/admin/usuarios/{$otro->id}")
            ->assertSessionHas('exito');

        $this->assertModelMissing($otro);

        // Con el último ya no: lo protege quien intente borrarlo.
        $otro = Usuario::create([
            'nombre' => 'Operador',
            'email' => 'operador@pruebas.test',
            'password' => 'clave-segura-9',
            'estado' => 'activo',
        ]);
        $otro->assignRole('RECEPCION');

        $this->actingAs($otro)
            ->from('/admin/usuarios')
            ->delete("/admin/usuarios/{$this->admin->id}")
            ->assertForbidden();

        $this->assertModelExists($this->admin);
    }

    // --- Roles -------------------------------------------------------------

    public function test_un_rol_nuevo_solo_recibe_los_permisos_marcados(): void
    {
        $this->actingAs($this->admin)->post('/admin/roles', [
            'name' => 'AUDITORIA',
            'permisos' => ['pacientes.ver', 'reportes.ver'],
        ])->assertRedirect('/admin/roles');

        $rol = Role::findByName('AUDITORIA', 'web');

        $this->assertTrue($rol->hasPermissionTo('reportes.ver'));
        $this->assertFalse($rol->hasPermissionTo('pagos.crear'));
    }

    public function test_el_super_administrador_no_puede_quedarse_sin_permisos(): void
    {
        $rol = Role::findByName('SUPER ADMINISTRADOR', 'web');

        $this->actingAs($this->admin)->put("/admin/roles/{$rol->id}", [
            'name' => 'SUPER ADMINISTRADOR',
            'permisos' => [],
        ])->assertSessionHas('aviso');

        $this->assertTrue($rol->fresh()->hasPermissionTo('pagos.crear'));
    }

    public function test_el_rol_super_administrador_no_se_elimina(): void
    {
        $rol = Role::findByName('SUPER ADMINISTRADOR', 'web');

        $this->actingAs($this->admin)
            ->from('/admin/roles')
            ->delete("/admin/roles/{$rol->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($rol);
    }

    public function test_no_se_elimina_un_rol_con_usuarios_asignados(): void
    {
        $rol = Role::findByName('RECEPCION', 'web');

        $usuario = Usuario::create([
            'nombre' => 'Recepción',
            'email' => 'recepcion@pruebas.test',
            'password' => 'clave-segura-9',
            'estado' => 'activo',
        ]);
        $usuario->assignRole($rol);

        $this->actingAs($this->admin)
            ->from('/admin/roles')
            ->delete("/admin/roles/{$rol->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($rol);
    }

    // --- Perfil propio -----------------------------------------------------

    public function test_cambiar_el_correo_obliga_a_verificarlo_de_nuevo(): void
    {
        $this->admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($this->admin)->put('/perfil', [
            'nombre' => 'Admin de Pruebas',
            'email' => 'nuevo.correo@pruebas.test',
        ])->assertSessionHas('exito');

        $this->admin->refresh();

        $this->assertSame('nuevo.correo@pruebas.test', $this->admin->email);
        $this->assertNull($this->admin->email_verified_at);
    }

    public function test_la_contrasena_nueva_sirve_para_entrar(): void
    {
        $this->actingAs($this->admin)->put('/perfil/password', [
            'password_actual' => 'secreto123',
            'password' => 'otra-clave-99',
            'password_confirmation' => 'otra-clave-99',
        ])->assertSessionHas('exito');

        $this->post('/logout');

        $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'otra-clave-99',
        ])->assertRedirect('/admin/home');
    }

    public function test_sin_la_contrasena_actual_no_se_cambia_la_clave(): void
    {
        $this->actingAs($this->admin)->put('/perfil/password', [
            'password_actual' => 'equivocada',
            'password' => 'otra-clave-99',
            'password_confirmation' => 'otra-clave-99',
        ])->assertSessionHasErrors('password_actual');

        $this->assertTrue(Hash::check('secreto123', $this->admin->fresh()->password));
    }
}
