<?php

namespace Tests\Feature;

use App\Models\Clinica;
use App\Models\Invitacion;
use App\Models\Usuario;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MulticlinicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolPermisoSeeder::class);
        Mail::fake();
    }

    public function test_registro_sin_codigo_crea_paciente_sin_clinica(): void
    {
        $this->post('/registro', [
            'nombre' => 'Paciente Libre', 'email' => 'libre@test.dev',
            'password' => 'Seguro12345', 'password_confirmation' => 'Seguro12345',
        ])->assertRedirect(route('portal.inicio'));

        $usuario = Usuario::whereEmail('libre@test.dev')->firstOrFail();
        $this->assertTrue($usuario->hasRole('PACIENTE'));
        $this->assertNull($usuario->clinica_id);
    }

    public function test_codigo_de_activacion_crea_clinica_y_administrador(): void
    {
        $codigo = 'ADMIN-TEST-1234';
        Invitacion::create([
            'rol' => 'ADMINISTRADOR', 'codigo_hash' => Invitacion::hashCodigo($codigo),
            'codigo_visible' => '••••-1234', 'vence_en' => now()->addDay(),
            'datos' => ['plan' => 'PROFESIONAL', 'dias_licencia' => 30],
        ]);

        $this->post('/registro', [
            'nombre' => 'Propietaria', 'email' => 'propietaria@test.dev',
            'password' => 'Seguro12345', 'password_confirmation' => 'Seguro12345',
            'codigo' => $codigo, 'clinica_nombre' => 'Clínica Norte',
        ])->assertRedirect(route('admin.home'));

        $usuario = Usuario::whereEmail('propietaria@test.dev')->firstOrFail();
        $this->assertTrue($usuario->hasRole('ADMINISTRADOR'));
        $this->assertSame('Clínica Norte', $usuario->clinica->nombre);
        $this->assertFalse(Invitacion::first()->activa);
    }

    public function test_codigo_de_equipo_vincula_usuario_a_la_clinica(): void
    {
        $clinica = Clinica::create(['nombre' => 'Clínica Uno', 'slug' => 'clinica-uno']);
        $codigo = 'EQUIPO-ABCD-1234';
        Invitacion::create([
            'clinica_id' => $clinica->id, 'email' => 'doctor@test.dev', 'rol' => 'DOCTOR',
            'codigo_hash' => Invitacion::hashCodigo($codigo), 'codigo_visible' => '••••-1234',
            'vence_en' => now()->addDay(),
        ]);

        $this->post('/registro', [
            'nombre' => 'Doctor Invitado', 'email' => 'doctor@test.dev',
            'password' => 'Seguro12345', 'password_confirmation' => 'Seguro12345', 'codigo' => $codigo,
        ])->assertRedirect(route('admin.home'));

        $usuario = Usuario::whereEmail('doctor@test.dev')->firstOrFail();
        $this->assertTrue($usuario->hasRole('DOCTOR'));
        $this->assertSame($clinica->id, $usuario->clinica_id);
    }

    public function test_administrador_solo_consulta_invitaciones_de_su_clinica(): void
    {
        [$a, $b] = [
            Clinica::create(['nombre' => 'A', 'slug' => 'a']),
            Clinica::create(['nombre' => 'B', 'slug' => 'b']),
        ];
        $admin = Usuario::create(['clinica_id' => $a->id, 'nombre' => 'Admin A', 'email' => 'a@test.dev', 'password' => 'Seguro123', 'estado' => 'activo']);
        $admin->assignRole('ADMINISTRADOR');
        Invitacion::create(['clinica_id' => $a->id, 'rol' => 'PACIENTE', 'codigo_hash' => Invitacion::hashCodigo('PAC-A'), 'codigo_visible' => '••••-AAAA']);
        Invitacion::create(['clinica_id' => $b->id, 'rol' => 'PACIENTE', 'codigo_hash' => Invitacion::hashCodigo('PAC-B'), 'codigo_visible' => '••••-BBBB']);

        $this->actingAs($admin)->get('/admin/invitaciones')
            ->assertOk()->assertSee('••••-AAAA')->assertDontSee('••••-BBBB');
    }

    public function test_super_administrador_abre_panel_de_activaciones(): void
    {
        $super = Usuario::create(['nombre' => 'Super', 'email' => 'super@test.dev', 'password' => 'Seguro123', 'estado' => 'activo']);
        $super->assignRole('SUPER ADMINISTRADOR');

        $this->actingAs($super)->get('/admin/activaciones')
            ->assertOk()->assertSee('Clínicas y activaciones');
    }

    public function test_administrador_abre_panel_de_invitaciones(): void
    {
        $clinica = Clinica::create(['nombre' => 'Clínica Panel', 'slug' => 'clinica-panel']);
        $admin = Usuario::create(['clinica_id' => $clinica->id, 'nombre' => 'Admin', 'email' => 'admin@test.dev', 'password' => 'Seguro123', 'estado' => 'activo']);
        $admin->assignRole('ADMINISTRADOR');

        $this->actingAs($admin)->get('/admin/invitaciones')
            ->assertOk()->assertSee('Invitar personas');
    }
}
