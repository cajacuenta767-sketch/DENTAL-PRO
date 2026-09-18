<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Usuario;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\CasoClinico;

class PrivilegiosTest extends CasoClinico
{
    private const PERMISOS_GESTOR = [
        'roles.ver', 'roles.editar', 'usuarios.ver', 'usuarios.editar', 'usuarios.crear', 'pacientes.ver',
    ];

    private Role $rolGestor;

    private Usuario $gestor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolGestor = Role::create(['name' => 'GESTOR', 'guard_name' => 'web']);
        $this->rolGestor->syncPermissions(self::PERMISOS_GESTOR);

        $this->gestor = $this->usuarioCon('GESTOR', 'gestor@pruebas.test');
    }

    private function usuarioCon(string $rol, string $email, array $extra = []): Usuario
    {
        $usuario = Usuario::create(array_merge([
            'nombre' => "Usuario {$rol}",
            'email' => $email,
            'password' => 'secreto123',
            'estado' => 'activo',
        ], $extra));
        $usuario->forceFill(['email_verified_at' => now()])->save();
        $usuario->assignRole($rol);

        return $usuario;
    }

    public function test_nadie_concede_permisos_que_no_tiene(): void
    {
        $this->actingAs($this->gestor)
            ->from("/admin/roles/{$this->rolGestor->id}/edit")
            ->put("/admin/roles/{$this->rolGestor->id}", [
                'name' => 'GESTOR',
                'permisos' => array_merge(self::PERMISOS_GESTOR, ['pagos.anular']),
            ])
            ->assertSessionHasErrors('permisos');

        $this->assertFalse($this->rolGestor->fresh()->hasPermissionTo('pagos.anular'));
    }

    public function test_el_gestor_no_toca_el_rol_super_administrador(): void
    {
        $superRol = Role::findByName('SUPER ADMINISTRADOR');

        $this->actingAs($this->gestor);

        $this->get("/admin/roles/{$superRol->id}/edit")->assertForbidden();
        $this->put("/admin/roles/{$superRol->id}", ['name' => 'SUPER ADMINISTRADOR', 'permisos' => ['roles.ver']])
            ->assertForbidden();

        $this->assertSame(
            Permission::count(),
            $superRol->fresh()->permissions()->count(),
            'El super administrador conserva todos sus permisos.'
        );
    }

    public function test_el_gestor_no_asigna_el_rol_super_administrador(): void
    {
        $this->actingAs($this->gestor)
            ->from('/admin/usuarios/create')
            ->post('/admin/usuarios', [
                'nombre' => 'Intruso',
                'email' => 'intruso@pruebas.test',
                'estado' => 'activo',
                'roles' => ['SUPER ADMINISTRADOR'],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertNull(Usuario::whereEmail('intruso@pruebas.test')->first());
    }

    public function test_el_gestor_no_edita_al_super_administrador(): void
    {
        $this->actingAs($this->gestor)
            ->get("/admin/usuarios/{$this->admin->id}/edit")
            ->assertForbidden();
    }

    public function test_un_doctor_sin_agenda_todos_solo_ve_su_propia_agenda(): void
    {
        $cuentaDoctor = $this->usuarioCon('DOCTOR', 'doctora@pruebas.test');
        $this->doctor->update(['usuario_id' => $cuentaDoctor->id]);

        $otro = Doctor::create([
            'especialidad_id' => $this->doctor->especialidad_id,
            'nombres' => 'Marcos',
            'apellidos' => 'Quiroga',
            'tipo_documento' => 'CI',
            'numero_documento' => '5555555',
            'genero' => 'M',
            'activo' => true,
        ]);

        Cita::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $otro->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ]);

        $this->actingAs($cuentaDoctor)
            ->get('/admin/agenda?doctor_id='.$otro->id)
            ->assertOk()
            ->assertViewHas('doctor', fn ($doctor) => $doctor->id === $this->doctor->id)
            ->assertViewHas('turnos', fn ($turnos) => $turnos->isEmpty());
    }

    public function test_un_usuario_creado_sin_password_recibe_una_temporal(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/usuarios', [
                'nombre' => 'Nuevo Recepcionista',
                'email' => 'nuevo@pruebas.test',
                'estado' => 'activo',
                'roles' => ['RECEPCION'],
            ])
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHas('aviso');

        $usuario = Usuario::whereEmail('nuevo@pruebas.test')->first();

        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->debe_cambiar_password);
        $this->assertNotEmpty($usuario->password);
    }

    public function test_la_password_temporal_obliga_a_definir_una_propia(): void
    {
        $usuario = $this->usuarioCon('RECEPCION', 'temporal@pruebas.test', [
            'password' => 'Temporal123',
            'debe_cambiar_password' => true,
        ]);

        $this->actingAs($usuario);

        $this->get('/admin/home')->assertRedirect(route('password.obligatoria'));

        $this->from(route('password.obligatoria'))
            ->post(route('password.obligatoria.guardar'), [
                'password_actual' => 'equivocada',
                'password' => 'NuevaClave123',
                'password_confirmation' => 'NuevaClave123',
            ])
            ->assertSessionHasErrors('password_actual');

        $this->assertTrue($usuario->fresh()->debe_cambiar_password);

        $this->post(route('password.obligatoria.guardar'), [
            'password_actual' => 'Temporal123',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ])->assertRedirect(route('admin.home'));

        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
        $this->get('/admin/home')->assertOk();
    }
}
