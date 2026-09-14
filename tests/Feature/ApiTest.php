<?php

namespace Tests\Feature;

use App\Mail\CitaConfirmacionMail;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\CasoClinico;

/**
 * API REST v1 con tokens personales de Sanctum: autenticación, permisos
 * heredados del usuario, filtros y las mismas reglas de agenda del panel.
 */
class ApiTest extends CasoClinico
{
    /** Token con todos los permisos del usuario, como lo crea el perfil. */
    private function tokenDe(Usuario $usuario, ?array $capacidades = null): string
    {
        return $usuario->createToken(
            'pruebas',
            $capacidades ?? $usuario->getAllPermissions()->pluck('name')->all(),
        )->plainTextToken;
    }

    private function comoAdmin(): static
    {
        return $this->withToken($this->tokenDe($this->admin));
    }

    /** Olvida la sesión web de actingAs() para que solo cuente el token Bearer. */
    private function soloConToken(string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->withToken($token);
    }

    private function datosCita(array $extra = []): array
    {
        return array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'motivo' => 'Control semestral',
        ], $extra);
    }

    public function test_sin_token_la_api_responde_401(): void
    {
        $this->getJson('/api/v1/yo')->assertStatus(401)->assertJsonStructure(['message']);
        $this->getJson('/api/v1/pacientes')->assertStatus(401);
        $this->postJson('/api/v1/citas', $this->datosCita())->assertStatus(401);
    }

    public function test_un_token_invalido_o_expirado_responde_401(): void
    {
        $this->withToken('1|token-que-no-existe')->getJson('/api/v1/yo')->assertStatus(401);

        $expirado = $this->admin->createToken('viejo', ['*'], now()->subDay())->plainTextToken;

        $this->withToken($expirado)->getJson('/api/v1/yo')->assertStatus(401);
    }

    public function test_yo_devuelve_la_identidad_y_los_permisos_del_token(): void
    {
        $this->comoAdmin()->getJson('/api/v1/yo')
            ->assertOk()
            ->assertJsonPath('id', $this->admin->id)
            ->assertJsonPath('email', 'admin@pruebas.test')
            ->assertJsonFragment(['SUPER ADMINISTRADOR'])
            ->assertJsonFragment(['pacientes.ver']);
    }

    public function test_los_pacientes_se_listan_y_se_buscan_por_q(): void
    {
        Paciente::create([
            'nombres' => 'María', 'apellidos' => 'Quispe', 'tipo_documento' => 'CI',
            'numero_documento' => '9988776', 'genero' => 'F', 'activo' => true,
        ]);

        $this->comoAdmin()->getJson('/api/v1/pacientes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']]);

        $this->comoAdmin()->getJson('/api/v1/pacientes?q=quispe')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.apellidos', 'Quispe');

        $this->comoAdmin()->getJson('/api/v1/pacientes?q=7654321')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->paciente->id);

        $this->comoAdmin()->getJson('/api/v1/pacientes?q=nadie')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_per_page_se_limita_a_100(): void
    {
        $this->comoAdmin()->getJson('/api/v1/pacientes?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_un_paciente_inexistente_responde_404_en_json(): void
    {
        $this->comoAdmin()->getJson('/api/v1/pacientes/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_post_pacientes_crea_la_ficha(): void
    {
        $this->comoAdmin()->postJson('/api/v1/pacientes', [
            'nombres' => 'Lucía',
            'apellidos' => 'Mamani',
            'tipo_documento' => 'CI',
            'numero_documento' => '5551234',
            'genero' => 'F',
            'telefono' => '70011223',
            'email' => 'lucia@pruebas.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nombre_completo', 'Lucía Mamani')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('pacientes', ['numero_documento' => '5551234', 'activo' => true]);
    }

    public function test_post_pacientes_valida_igual_que_el_panel(): void
    {
        $this->comoAdmin()->postJson('/api/v1/pacientes', [
            'nombres' => 'Otro',
            'apellidos' => 'Pérez',
            'tipo_documento' => 'CI',
            'numero_documento' => '7654321', // ya existe
            'genero' => 'X',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero_documento', 'genero']);
    }

    public function test_las_citas_se_listan_con_filtros(): void
    {
        Mail::fake();

        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita())->assertCreated();
        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita(['hora' => '10:00', 'fecha' => $this->proximaFecha(5)]))->assertCreated();

        $this->comoAdmin()->getJson('/api/v1/citas')->assertOk()->assertJsonCount(2, 'data');

        $this->comoAdmin()->getJson('/api/v1/citas?fecha='.$this->proximaFecha())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.hora', '09:00');

        $this->comoAdmin()->getJson('/api/v1/citas?desde='.$this->proximaFecha(4).'&hasta='.$this->proximaFecha(6))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.hora', '10:00');

        $this->comoAdmin()->getJson('/api/v1/citas?doctor_id='.$this->doctor->id.'&paciente_id='.$this->paciente->id.'&estado=PENDIENTE')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->comoAdmin()->getJson('/api/v1/citas?estado=CANCELADA')->assertOk()->assertJsonCount(0, 'data');

        $this->comoAdmin()->getJson('/api/v1/citas?estado=INVENTADO')->assertStatus(422);
    }

    public function test_post_citas_crea_la_cita_y_avisa_al_paciente(): void
    {
        Mail::fake();

        $respuesta = $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita())
            ->assertCreated()
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.hora', '09:00')
            ->assertJsonPath('data.duracion_minutos', 30)
            ->assertJsonPath('data.paciente.id', $this->paciente->id)
            ->assertJsonPath('data.doctor.id', $this->doctor->id)
            ->assertJsonPath('data.tratamiento.id', $this->tratamiento->id);

        $cita = Cita::find($respuesta->json('data.id'));

        $this->assertNotNull($cita);
        $this->assertSame(12, strlen($cita->token));
        $this->assertSame('Control semestral', $cita->motivo);
        Mail::assertQueued(CitaConfirmacionMail::class);

        $this->comoAdmin()->getJson("/api/v1/citas/{$cita->id}")
            ->assertOk()
            ->assertJsonPath('data.token', $cita->token);
    }

    public function test_post_citas_respeta_el_conflicto_de_cupo(): void
    {
        Mail::fake();

        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita())->assertCreated();

        // Misma hora exacta y una hora que se solapa con la duración (30 min).
        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hora']);

        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita(['hora' => '09:15']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hora']);

        $this->assertSame(1, Cita::count());
    }

    public function test_post_citas_rechaza_una_hora_fuera_del_turno(): void
    {
        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita(['hora' => '21:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hora']);

        // 11:45 + 30 min no cabe en un turno que termina a las 12:00.
        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita(['hora' => '11:45']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hora']);

        $this->assertSame(0, Cita::count());
    }

    public function test_post_citas_exige_los_campos_obligatorios(): void
    {
        $this->comoAdmin()->postJson('/api/v1/citas', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paciente_id', 'doctor_id', 'tratamiento_id', 'fecha', 'hora']);
    }

    public function test_agenda_horas_devuelve_los_cupos_libres(): void
    {
        Mail::fake();

        $this->comoAdmin()->getJson('/api/v1/agenda/horas?'.http_build_query([
            'doctor_id' => $this->doctor->id,
            'fecha' => $this->proximaFecha(),
            'tratamiento_id' => $this->tratamiento->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['dia', 'horas'])
            ->assertJsonFragment(['09:00'])
            ->assertJsonFragment(['08:00']);

        $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita())->assertCreated();

        $horas = $this->comoAdmin()->getJson('/api/v1/agenda/horas?'.http_build_query([
            'doctor_id' => $this->doctor->id,
            'fecha' => $this->proximaFecha(),
        ]))->assertOk()->json('horas');

        $this->assertNotContains('09:00', $horas);
        $this->assertContains('09:30', $horas);

        $this->comoAdmin()->getJson('/api/v1/agenda/horas?fecha='.$this->proximaFecha())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['doctor_id']);
    }

    public function test_patch_estado_avanza_la_cita_y_bloquea_salir_de_completada(): void
    {
        Mail::fake();

        $id = $this->comoAdmin()->postJson('/api/v1/citas', $this->datosCita())->json('data.id');

        $this->comoAdmin()->patchJson("/api/v1/citas/{$id}/estado", ['estado' => 'CONFIRMADA'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'CONFIRMADA');

        $this->comoAdmin()->patchJson("/api/v1/citas/{$id}/estado", ['estado' => 'INVENTADO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        $this->comoAdmin()->patchJson("/api/v1/citas/{$id}/estado", ['estado' => 'COMPLETADA'])->assertOk();

        $this->comoAdmin()->patchJson("/api/v1/citas/{$id}/estado", ['estado' => 'CANCELADA'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        $this->assertSame('COMPLETADA', Cita::find($id)->estado);
    }

    public function test_los_catalogos_se_listan(): void
    {
        $this->comoAdmin()->getJson('/api/v1/doctores')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->doctor->id)
            ->assertJsonPath('data.0.especialidad.nombre', 'ODONTOLOGÍA GENERAL');

        $this->comoAdmin()->getJson('/api/v1/especialidades')->assertOk()->assertJsonCount(1, 'data');

        $this->comoAdmin()->getJson('/api/v1/tratamientos?q=profilaxis')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->tratamiento->id)
            ->assertJsonPath('data.0.duracion', 30);

        $this->comoAdmin()->getJson('/api/v1/presupuestos')->assertOk()->assertJsonCount(0, 'data');
        $this->comoAdmin()->getJson('/api/v1/pagos')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_un_usuario_sin_el_permiso_recibe_403(): void
    {
        Role::findByName('RECEPCION')->revokePermissionTo('pagos.ver');

        $recepcion = Usuario::create([
            'nombre' => 'Recepción de Pruebas',
            'email' => 'recepcion@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $recepcion->assignRole('RECEPCION');

        $token = $this->tokenDe($recepcion);

        $this->withToken($token)->getJson('/api/v1/pagos')
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->withToken($token)->getJson('/api/v1/pacientes')->assertOk();
    }

    public function test_el_token_no_puede_mas_que_sus_capacidades(): void
    {
        // El administrador tiene pagos.ver, pero este token se creó sin esa capacidad.
        $token = $this->tokenDe($this->admin, ['pacientes.ver', 'citas.ver']);

        $this->withToken($token)->getJson('/api/v1/pagos')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/pacientes', [])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/pacientes')->assertOk();
    }

    public function test_un_usuario_inactivo_recibe_403_aunque_tenga_token(): void
    {
        $token = $this->tokenDe($this->admin);
        $this->admin->forceFill(['estado' => 'inactivo'])->save();

        $this->withToken($token)->getJson('/api/v1/pacientes')->assertForbidden();
    }

    public function test_desde_el_perfil_se_crea_y_revoca_un_token(): void
    {
        $this->actingAs($this->admin)
            ->from('/perfil')
            ->post('/perfil/tokens', ['nombre_token' => 'Central telefónica', 'expira_en' => now()->addMonth()->toDateString()])
            ->assertRedirect('/perfil')
            ->assertSessionHas('token_plano')
            ->assertSessionHas('exito');

        $plano = session('token_plano');
        $registro = $this->admin->tokens()->where('name', 'Central telefónica')->first();

        $this->assertNotNull($registro);
        $this->assertNotNull($registro->expires_at);
        $this->assertContains('pacientes.ver', $registro->abilities);
        $this->assertDatabaseHas('auditorias', ['accion' => 'CREAR', 'descripcion' => 'Creó el token de API Central telefónica']);

        // El token recién creado funciona y se muestra en el perfil.
        $this->soloConToken($plano)->getJson('/api/v1/yo')->assertOk()->assertJsonPath('id', $this->admin->id);

        $this->actingAs($this->admin)->get('/perfil')
            ->assertOk()
            ->assertSee('Tokens de API')
            ->assertSee('Central telefónica');

        // Revocarlo lo deja fuera.
        $this->actingAs($this->admin)
            ->from('/perfil')
            ->delete("/perfil/tokens/{$registro->id}")
            ->assertRedirect('/perfil')
            ->assertSessionHas('exito');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $registro->id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'ELIMINAR', 'descripcion' => 'Revocó el token de API Central telefónica']);

        $this->soloConToken($plano)->getJson('/api/v1/yo')->assertStatus(401);
    }

    public function test_el_perfil_valida_el_nombre_del_token(): void
    {
        $this->admin->createToken('Repetido', ['pacientes.ver']);

        $this->actingAs($this->admin)
            ->from('/perfil')
            ->post('/perfil/tokens', ['nombre_token' => 'Repetido'])
            ->assertRedirect('/perfil')
            ->assertSessionHasErrors('nombre_token');

        $this->actingAs($this->admin)
            ->from('/perfil')
            ->post('/perfil/tokens', ['nombre_token' => 'Vencido', 'expira_en' => now()->toDateString()])
            ->assertSessionHasErrors('expira_en');

        $this->assertSame(1, $this->admin->tokens()->count());
    }

    public function test_nadie_revoca_tokens_ajenos_ni_sin_permiso(): void
    {
        $otro = Usuario::create([
            'nombre' => 'Otro Admin',
            'email' => 'otro@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $otro->assignRole('ADMINISTRADOR');
        $ajeno = $otro->createToken('suyo', ['pacientes.ver'])->accessToken;

        $this->actingAs($this->admin)->delete("/perfil/tokens/{$ajeno->id}")->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $ajeno->id]);

        $doctor = Usuario::create([
            'nombre' => 'Doctor sin API',
            'email' => 'doctor@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $doctor->assignRole('DOCTOR');

        $this->actingAs($doctor)->post('/perfil/tokens', ['nombre_token' => 'Intento'])->assertForbidden();
        $this->actingAs($doctor)->get('/perfil')->assertOk()->assertDontSee('Tokens de API');
    }
}
