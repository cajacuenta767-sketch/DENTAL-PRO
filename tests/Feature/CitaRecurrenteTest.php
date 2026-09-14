<?php

namespace Tests\Feature;

use App\Models\Cita;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class CitaRecurrenteTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAs($this->admin);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ], $extra);
    }

    public function test_una_serie_semanal_crea_todas_las_citas_con_el_mismo_serie_id(): void
    {
        $this->post('/admin/citas', $this->datos(['repetir' => 'semanal', 'repeticiones' => 3]))
            ->assertRedirect();

        $citas = Cita::orderBy('fecha')->get();

        $this->assertCount(4, $citas);
        $this->assertNotNull($citas->first()->serie_id);
        $this->assertSame(1, $citas->pluck('serie_id')->unique()->count());

        $base = now()->addDays(3);
        $this->assertSame($base->toDateString(), $citas[0]->fecha->toDateString());
        $this->assertSame($base->copy()->addWeek()->toDateString(), $citas[1]->fecha->toDateString());
        $this->assertSame($base->copy()->addWeeks(2)->toDateString(), $citas[2]->fecha->toDateString());
        $this->assertSame($base->copy()->addWeeks(3)->toDateString(), $citas[3]->fecha->toDateString());
        $this->assertTrue($citas->every(fn (Cita $c) => substr($c->hora, 0, 5) === '09:00'));
    }

    public function test_la_serie_omite_las_citas_que_chocan_con_una_existente(): void
    {
        $ocupada = Cita::create($this->datos(['fecha' => now()->addDays(3)->addWeeks(2)->toDateString()]));

        $respuesta = $this->post('/admin/citas', $this->datos(['repetir' => 'semanal', 'repeticiones' => 3]));
        $respuesta->assertRedirect();

        $serie = Cita::whereNotNull('serie_id')->get();

        $this->assertCount(3, $serie);
        $this->assertNull($ocupada->fresh()->serie_id);
        $this->assertFalse($serie->contains(fn (Cita $c) => $c->fecha->toDateString() === $ocupada->fecha->toDateString()));

        $aviso = session('aviso');
        $this->assertStringContainsString('No se pudieron crear 1 cita', $aviso);
        $this->assertStringContainsString($ocupada->fecha->format('d/m').' (cupo ocupado)', $aviso);
    }

    public function test_sin_repeticion_no_se_asigna_serie(): void
    {
        $this->post('/admin/citas', $this->datos())->assertRedirect();

        $this->assertSame(1, Cita::count());
        $this->assertNull(Cita::first()->serie_id);
    }

    public function test_el_detalle_muestra_la_serie(): void
    {
        $this->post('/admin/citas', $this->datos(['repetir' => 'quincenal', 'repeticiones' => 2]));

        $this->get('/admin/citas/'.Cita::first()->id)
            ->assertOk()
            ->assertSee('Serie de 3 citas');
    }
}
