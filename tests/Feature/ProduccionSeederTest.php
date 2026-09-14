<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Paciente;
use App\Models\Sucursal;
use App\Models\Tratamiento;
use App\Models\Usuario;
use Database\Seeders\ProduccionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El seeder de producción (usado por el instalador de escritorio) debe dejar
 * una instalación limpia: sin pacientes ni citas, con una sede, el catálogo y
 * un único SUPER ADMINISTRADOR.
 */
class ProduccionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Dentro de la transacción de la prueba: no deja datos a las demás.
        $this->seed(ProduccionSeeder::class);
    }

    public function test_no_crea_datos_de_demostracion(): void
    {
        $this->assertSame(0, Paciente::count());
        $this->assertSame(0, Cita::count());
        $this->assertSame(1, Usuario::count());
        $this->assertSame(1, Sucursal::count());
        $this->assertTrue(Sucursal::principal()->principal);
    }

    public function test_crea_el_administrador_que_debe_cambiar_su_clave(): void
    {
        $admin = Usuario::where('email', ProduccionSeeder::ADMIN_EMAIL)->firstOrFail();

        $this->assertTrue(Hash::check(ProduccionSeeder::ADMIN_PASSWORD, $admin->password));
        $this->assertTrue($admin->debe_cambiar_password);
        $this->assertSame('activo', $admin->estado);
        $this->assertTrue($admin->hasRole('SUPER ADMINISTRADOR'));
        $this->assertTrue($admin->can('respaldos.ver'));
    }

    public function test_siembra_el_catalogo_base(): void
    {
        $this->assertGreaterThan(0, Especialidad::count());
        $this->assertGreaterThan(0, Tratamiento::count());
    }

    public function test_es_idempotente_y_conserva_la_clave_cambiada(): void
    {
        $admin = Usuario::where('email', ProduccionSeeder::ADMIN_EMAIL)->firstOrFail();
        $admin->forceFill(['password' => 'nueva-clave-segura', 'debe_cambiar_password' => false])->save();
        Sucursal::principal()->update(['nombre' => 'Consultorio Norte']);

        $this->seed(ProduccionSeeder::class);

        $this->assertSame(1, Usuario::count());
        $this->assertSame(1, Sucursal::count());
        $this->assertSame('Consultorio Norte', Sucursal::principal()->nombre);
        $this->assertTrue(Hash::check('nueva-clave-segura', $admin->fresh()->password));
        $this->assertSame(0, Paciente::count());
    }
}
