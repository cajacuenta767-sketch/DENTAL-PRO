<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Paciente;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class ReservaOnlineTest extends CasoClinico
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $ajustes = Ajuste::actual();
        $ajustes->update([
            'reservas_online' => true,
            'reservas_token' => 'token-de-pruebas-abcdefghijklmnop',
            'reservas_anticipacion_dias' => 30,
            'reservas_minimo_horas' => 4,
        ]);

        $this->token = $ajustes->reservas_token;
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombres' => 'Lucía',
            'apellidos' => 'Web',
            'tipo_documento' => 'CI',
            'numero_documento' => '55443322',
            'telefono' => '70099887',
            'email' => 'lucia.web@pruebas.test',
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
        ], $extra);
    }

    public function test_la_pagina_publica_responde_con_el_token_correcto(): void
    {
        $this->get("/reservar/{$this->token}")
            ->assertOk()
            ->assertSee('Agenda tu cita', false);
    }

    public function test_un_token_invalido_no_abre_la_pagina(): void
    {
        $this->get('/reservar/token-que-no-existe')->assertNotFound();
    }

    public function test_con_las_reservas_apagadas_la_pagina_no_existe(): void
    {
        Ajuste::actual()->update(['reservas_online' => false]);

        $this->get("/reservar/{$this->token}")->assertNotFound();
    }

    public function test_reservar_crea_el_paciente_y_la_cita_pendiente(): void
    {
        $this->post("/reservar/{$this->token}", $this->datos())->assertRedirect();

        $cita = Cita::first();

        $this->assertNotNull($cita);
        $this->assertSame('ONLINE', $cita->origen);
        $this->assertSame('PENDIENTE', $cita->estado, 'Una reserva pública entra sin confirmar.');
        $this->assertSame('55443322', $cita->paciente->numero_documento);
    }

    public function test_un_paciente_existente_no_se_duplica(): void
    {
        $this->post("/reservar/{$this->token}", $this->datos([
            'numero_documento' => $this->paciente->numero_documento,
            'nombres' => 'Nombre Distinto',
        ]));

        $this->assertSame(1, Paciente::where('numero_documento', $this->paciente->numero_documento)->count());
        $this->assertSame('Juan', $this->paciente->fresh()->nombres, 'No se pisan los datos ya registrados.');
    }

    public function test_no_se_puede_tomar_un_cupo_ya_ocupado(): void
    {
        $this->post("/reservar/{$this->token}", $this->datos());

        $this->post("/reservar/{$this->token}", $this->datos(['numero_documento' => '11112222']))
            ->assertSessionHasErrors('hora');

        $this->assertSame(1, Cita::count());
    }

    public function test_se_respeta_la_anticipacion_minima(): void
    {
        $this->post("/reservar/{$this->token}", $this->datos([
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i'),
        ]))->assertSessionHasErrors('fecha');

        $this->assertSame(0, Cita::count());
    }

    public function test_se_respeta_la_anticipacion_maxima(): void
    {
        $this->post("/reservar/{$this->token}", $this->datos([
            'fecha' => now()->addDays(90)->toDateString(),
        ]))->assertSessionHasErrors('fecha');
    }

    public function test_los_cupos_publicos_descartan_los_que_estan_dentro_del_minimo(): void
    {
        $respuesta = $this->getJson("/reservar/{$this->token}/horas?".http_build_query([
            'doctor_id' => $this->doctor->id,
            'fecha' => now()->toDateString(),
        ]))->assertOk();

        foreach ($respuesta->json('horas') as $hora) {
            $this->assertTrue(
                now()->setTimeFromTimeString($hora)->greaterThanOrEqualTo(now()->addHours(4)),
                "El cupo {$hora} no respeta la anticipación mínima."
            );
        }
    }

    public function test_el_administrador_puede_regenerar_el_enlace(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/reservas-online/regenerar')
            ->assertRedirect();

        $this->assertNotSame($this->token, Ajuste::actual()->fresh()->reservas_token);
        $this->get("/reservar/{$this->token}")->assertNotFound();
    }

    public function test_el_qr_se_descarga_como_svg(): void
    {
        $respuesta = $this->actingAs($this->admin)->get('/admin/reservas-online/qr')->assertOk();

        $this->assertSame('image/svg+xml', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('<svg', $respuesta->getContent());
    }
}
