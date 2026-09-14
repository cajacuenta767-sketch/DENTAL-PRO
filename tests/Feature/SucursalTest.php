<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Support\SucursalActiva;
use Tests\CasoClinico;

class SucursalTest extends CasoClinico
{
    private Sucursal $central;

    private Sucursal $sur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = Sucursal::create(['nombre' => 'Sede Central', 'codigo' => 'CENTRAL', 'principal' => true, 'activo' => true]);
        $this->sur = Sucursal::create(['nombre' => 'Sede Sur', 'codigo' => 'SUR', 'principal' => false, 'activo' => true]);
    }

    private function usuarioCon(string $rol, array $extra = []): Usuario
    {
        $usuario = Usuario::create(array_merge([
            'nombre' => "Usuario {$rol}",
            'email' => str($rol)->slug().'@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ], $extra));
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function datosSede(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Sede Norte',
            'codigo' => 'norte',
            'direccion' => 'Av. Norte 100',
            'telefono' => '70000000',
            'email' => 'norte@pruebas.test',
            'color' => '#123ABC',
            'activo' => 1,
        ], $extra);
    }

    private function datosPago(array $extra = []): array
    {
        return array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'metodo_pago' => 'EFECTIVO',
            'monto_pagado' => 180,
            'fecha_pago' => now()->format('Y-m-d\TH:i'),
            'detalles' => [[
                'tratamiento_id' => $this->tratamiento->id,
                'descripcion' => 'PROFILAXIS DENTAL',
                'cantidad' => 1,
                'precio_unitario' => 180,
            ]],
        ], $extra);
    }

    public function test_recepcion_no_administra_sucursales(): void
    {
        $this->actingAs($this->usuarioCon('RECEPCION'));

        $this->get('/admin/sucursales')->assertForbidden();
        $this->get('/admin/sucursales/create')->assertForbidden();
        $this->post('/admin/sucursales', $this->datosSede())->assertForbidden();
        $this->delete("/admin/sucursales/{$this->sur->id}")->assertForbidden();

        $this->assertSame(2, Sucursal::count());
    }

    public function test_el_administrador_crea_edita_y_elimina_sedes(): void
    {
        $this->actingAs($this->usuarioCon('ADMINISTRADOR'));

        $this->get('/admin/sucursales')->assertOk()->assertSee('Sede Central');

        $this->post('/admin/sucursales', $this->datosSede())->assertRedirect(route('admin.sucursales.index'));

        $norte = Sucursal::where('codigo', 'NORTE')->first();
        $this->assertNotNull($norte, 'El código se guarda en mayúsculas.');
        $this->assertSame('#123abc', $norte->color);
        $this->assertFalse($norte->principal);

        $this->put("/admin/sucursales/{$norte->id}", $this->datosSede(['nombre' => 'Sede Norte Renovada']))
            ->assertRedirect(route('admin.sucursales.index'));
        $this->assertSame('Sede Norte Renovada', $norte->fresh()->nombre);

        $this->delete("/admin/sucursales/{$norte->id}")->assertRedirect(route('admin.sucursales.index'));
        $this->assertNull(Sucursal::find($norte->id));
    }

    public function test_el_codigo_es_unico_y_corto(): void
    {
        $this->actingAs($this->admin);

        $this->from('/admin/sucursales/create')
            ->post('/admin/sucursales', $this->datosSede(['codigo' => 'central']))
            ->assertSessionHasErrors('codigo');

        $this->from('/admin/sucursales/create')
            ->post('/admin/sucursales', $this->datosSede(['codigo' => 'DEMASIADOLARGO']))
            ->assertSessionHasErrors('codigo');

        $this->from('/admin/sucursales/create')
            ->post('/admin/sucursales', $this->datosSede(['color' => 'rojo']))
            ->assertSessionHasErrors('color');

        $this->assertSame(2, Sucursal::count());
    }

    public function test_marcar_una_sede_como_principal_desmarca_las_demas(): void
    {
        $this->actingAs($this->admin);

        $this->put("/admin/sucursales/{$this->sur->id}", [
            'nombre' => 'Sede Sur',
            'codigo' => 'SUR',
            'color' => '#6366f1',
            'principal' => 1,
            'activo' => 1,
        ])->assertRedirect(route('admin.sucursales.index'));

        $this->assertTrue($this->sur->fresh()->principal);
        $this->assertFalse($this->central->fresh()->principal);
        $this->assertSame(1, Sucursal::where('principal', true)->count());
    }

    public function test_cambiar_de_sede_se_guarda_en_sesion(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/sucursal/cambiar', ['sucursal_id' => $this->sur->id])
            ->assertRedirect()
            ->assertSessionHas(SucursalActiva::CLAVE_SESION, $this->sur->id)
            ->assertSessionHas('exito');

        $this->assertSame($this->sur->id, SucursalActiva::id());
        $this->assertTrue($this->sur->is(SucursalActiva::modelo()));

        $this->post('/admin/sucursal/cambiar', ['sucursal_id' => ''])
            ->assertSessionMissing(SucursalActiva::CLAVE_SESION);

        $this->assertNull(SucursalActiva::id());
    }

    public function test_una_sede_inexistente_o_inactiva_no_se_activa(): void
    {
        $this->actingAs($this->admin);
        $this->sur->update(['activo' => false]);

        $this->post('/admin/sucursal/cambiar', ['sucursal_id' => 999])->assertSessionHasErrors('sucursal_id');
        $this->post('/admin/sucursal/cambiar', ['sucursal_id' => $this->sur->id])->assertSessionHasErrors('sucursal_id');

        $this->assertNull(SucursalActiva::id());
    }

    public function test_un_usuario_atado_a_una_sede_no_puede_cambiarla(): void
    {
        $atado = $this->usuarioCon('ADMINISTRADOR', ['sucursal_id' => $this->central->id]);
        $this->actingAs($atado);

        $this->assertFalse(SucursalActiva::puedeCambiar());

        $this->post('/admin/sucursal/cambiar', ['sucursal_id' => $this->sur->id])
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing(SucursalActiva::CLAVE_SESION);

        $this->assertSame($this->central->id, SucursalActiva::id());

        // El navbar muestra su sede como texto, sin selector.
        $this->get('/admin/home')->assertOk()
            ->assertSee('Sede Central')
            ->assertDontSee('Todas las sedes');
    }

    public function test_el_navbar_ofrece_el_selector_a_quien_puede_cambiar(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/home')->assertOk()
            ->assertSee('Todas las sedes')
            ->assertSee(route('admin.sucursal.cambiar'));
    }

    public function test_un_pago_creado_con_una_sede_activa_queda_etiquetado(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/sucursal/cambiar', ['sucursal_id' => $this->sur->id]);

        $this->post('/admin/pagos', $this->datosPago())->assertRedirect();

        $this->assertSame($this->sur->id, Pago::first()->sucursal_id);
    }

    public function test_con_una_sola_sede_el_pago_toma_la_principal(): void
    {
        $this->sur->delete();
        $this->actingAs($this->admin);

        $this->post('/admin/pagos', $this->datosPago())->assertRedirect();

        $this->assertSame($this->central->id, Pago::first()->sucursal_id);
    }

    public function test_el_filtro_de_recibos_por_sede_solo_muestra_los_suyos(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/pagos', $this->datosPago(['sucursal_id' => $this->central->id]));
        $this->post('/admin/pagos', $this->datosPago(['sucursal_id' => $this->sur->id]));

        [$deCentral, $deSur] = Pago::orderBy('id')->pluck('codigo_recibo');

        $this->get('/admin/pagos?sucursal_id='.$this->sur->id)
            ->assertOk()
            ->assertSee($deSur)
            ->assertDontSee($deCentral);

        // Con la sede activa en sesión el listado y la caja se acotan solos.
        $this->post('/admin/sucursal/cambiar', ['sucursal_id' => $this->central->id]);

        $this->get('/admin/pagos')
            ->assertOk()
            ->assertSee($deCentral)
            ->assertDontSee($deSur);
    }

    public function test_no_se_elimina_la_unica_sede_activa(): void
    {
        $this->actingAs($this->admin);
        $this->sur->update(['activo' => false]);

        $this->from('/admin/sucursales')
            ->delete("/admin/sucursales/{$this->central->id}")
            ->assertRedirect('/admin/sucursales')
            ->assertSessionHas('error');

        $this->assertNotNull(Sucursal::find($this->central->id));
    }

    public function test_una_sede_con_cobros_no_se_elimina(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/pagos', $this->datosPago(['sucursal_id' => $this->sur->id]));

        $this->from('/admin/sucursales')
            ->delete("/admin/sucursales/{$this->sur->id}")
            ->assertSessionHas('error');

        $this->assertNotNull(Sucursal::find($this->sur->id));
    }

    public function test_solo_quien_administra_sedes_liga_usuarios_a_una(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/usuarios', [
            'nombre' => 'Cajera Sur',
            'email' => 'cajera.sur@pruebas.test',
            'estado' => 'activo',
            'sucursal_id' => $this->sur->id,
            'roles' => ['RECEPCION'],
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame($this->sur->id, Usuario::where('email', 'cajera.sur@pruebas.test')->value('sucursal_id'));
    }
}
