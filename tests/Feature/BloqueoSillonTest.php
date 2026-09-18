<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\AjusteSeeder;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BloqueoSillonTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AjusteSeeder::class, RolPermisoSeeder::class]);

        $this->usuario = Usuario::create([
            'nombre' => 'Dra. María Elena',
            'email' => 'maria@dentalpro.com',
            'password' => Hash::make('ClaveSecreta123*'),
            'estado' => 'activo',
        ]);
        $this->usuario->assignRole('ADMINISTRADOR');
    }

    public function test_usuario_puede_desbloquear_sillon_con_password_correcto(): void
    {
        $this->actingAs($this->usuario);

        $response = $this->postJson(route('perfil.desbloquear-sillon'), [
            'password' => 'ClaveSecreta123*',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'mensaje' => 'Sillón clínico desbloqueado.',
        ]);
    }

    public function test_password_incorrecto_es_rechazado(): void
    {
        $this->actingAs($this->usuario);

        $response = $this->postJson(route('perfil.desbloquear-sillon'), [
            'password' => 'ClaveErronea999',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
        ]);
    }

    public function test_usuario_no_autenticado_no_puede_desbloquear(): void
    {
        $response = $this->postJson(route('perfil.desbloquear-sillon'), [
            'password' => 'CualquierClave',
        ]);

        $response->assertUnauthorized();
    }
}
