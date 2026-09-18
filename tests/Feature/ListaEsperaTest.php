<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\ListaEspera;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class ListaEsperaTest extends CasoClinico
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
            'fecha_desde' => now()->toDateString(),
            'preferencia_turno' => 'MAÑANA',
            'prioridad' => 'NORMAL',
            'estado' => 'ESPERANDO',
            'notas' => 'Quiere turno antes de fin de mes.',
        ], $extra);
    }

    private function entrada(): ListaEspera
    {
        $this->post('/admin/lista-espera', $this->datos())->assertRedirect(route('admin.lista-espera.index'));

        return ListaEspera::firstOrFail();
    }

    public function test_una_entrada_nueva_queda_esperando(): void
    {
        $entrada = $this->entrada();

        $this->assertSame('ESPERANDO', $entrada->estado);
        $this->assertSame($this->admin->id, $entrada->usuario_id);
        $this->assertNull($entrada->cita_id);
    }

    public function test_se_marca_como_contactado_desde_el_listado(): void
    {
        $entrada = $this->entrada();

        $this->patch("/admin/lista-espera/{$entrada->id}/estado", ['estado' => 'CONTACTADO'])->assertRedirect();

        $this->assertSame('CONTACTADO', $entrada->fresh()->estado);
    }

    public function test_agendar_desde_la_lista_cierra_la_entrada_con_su_cita(): void
    {
        $entrada = $this->entrada();

        $this->post('/admin/citas', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => $this->proximaFecha(),
            'hora' => '09:00',
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
            'lista_espera_id' => $entrada->id,
        ])->assertRedirect();

        $cita = Cita::firstOrFail();

        $this->assertSame('AGENDADO', $entrada->fresh()->estado);
        $this->assertSame($cita->id, $entrada->fresh()->cita_id);
    }

    public function test_no_se_marca_agendado_sin_una_cita(): void
    {
        $entrada = $this->entrada();

        $this->patch("/admin/lista-espera/{$entrada->id}/estado", ['estado' => 'AGENDADO'])
            ->assertSessionHas('error');

        $this->assertSame('ESPERANDO', $entrada->fresh()->estado);
    }
}
